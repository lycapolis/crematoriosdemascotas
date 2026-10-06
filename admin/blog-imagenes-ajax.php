<?php
/**
 * Imágenes del blog desde banco de fotos (Pexels/Pixabay) o generadas con IA (AJAX).
 *
 * POST JSON { accion, ... } + cabecera X-CSRF-Token:
 *   - proponer : lee el artículo → { consulta (búsqueda de banco), prompt (escena para IA) }
 *   - buscar   : { fuente: pexels|pixabay|ambos, q, orientacion, pagina } → resultados normalizados
 *   - generar  : { prompt, estilo, aspecto } → vista previa (base64) + token (el binario queda en tmp)
 *   - importar : { fuente, …foto | token, contexto, alts_usados, con_logo, etiqueta_ia }
 *                → IA (visión) genera alt/slug/title/caption → blogProcesarImagen() (WebP 1600+800,
 *                  sin metadatos) → logo opcional → blog_imagenes → datos listos para el editor.
 *
 * Proveedor/modelo de cada paso: admin/configuracion-ia.php (secciones blog_imagen_*).
 */

require_once __DIR__ . '/_blog-comun.php';
require_once dirname(__DIR__) . '/includes/ia-prompts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') blogJson(['ok' => false, 'mensaje' => 'Método no permitido'], 405);

if (!empty($_FILES['image'])) {
    // Subida de archivo (multipart): el contexto llega como JSON dentro de un campo
    $in = $_POST;
    foreach (['contexto', 'alts_usados'] as $k) if (isset($in[$k]) && is_string($in[$k])) $in[$k] = json_decode($in[$k], true) ?: [];
} else {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
}
$token = $in['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validarTokenCSRF((string) $token)) blogJson(['ok' => false, 'mensaje' => 'La sesión expiró. Recargá la página.'], 403);

$pdo = obtenerConexion();
$accion = (string) ($in['accion'] ?? '');

// Estilos visuales para la IA (se añaden al prompt de escena). Textos editables en
// admin/prompts-ia.php (claves blog_estilo_*); salvaguardas fijas en BLOG_IA_REGLAS.
const BLOG_IA_ESTILOS = ['realista', 'acuarela', 'minimalista'];

/** Tokens de imágenes IA generadas y aún no importadas (en sesión, caducan a las 2 h). */
function iaTemporales(): array
{
    $lista = $_SESSION['blog_ia_tmp'] ?? [];
    foreach ($lista as $t => $d) {
        if ($d['t'] < time() - 7200 || !is_file($d['ruta'])) { @unlink($d['ruta']); unset($lista[$t]); }
    }
    $_SESSION['blog_ia_tmp'] = $lista;
    return $lista;
}

function urlSegura(string $url, array $hosts): string
{
    $url = trim($url);
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
    $hostOk = false;
    foreach ($hosts as $h) if ($host === $h || str_ends_with($host, '.' . $h)) $hostOk = true;
    return (preg_match('#^https://#i', $url) && $hostOk) ? mb_substr($url, 0, 500) : '';
}

try {
    switch ($accion) {

        // ── Proponer búsqueda de banco + escena para IA a partir del artículo ──
        case 'proponer':
            $ctx = ctxArticulo($in);
            $base = $ctx['keyword'] ?: $ctx['titulo'];
            if ($base === '') blogJson(['ok' => false, 'mensaje' => 'Escribe primero el título del artículo.']);

            // Nivel de contexto (lo decide el código, no la IA):
            //   completo = título + entradilla o sección · parcial = solo título o palabra clave
            $completo = $ctx['titulo'] !== '' && ($ctx['extracto'] !== '' || $ctx['seccion'] !== '');

            // Propuestas previas de esta misma sesión del modal: la IA debe proponer algo distinto
            $anteriores = [];
            foreach ((array) ($in['anteriores'] ?? []) as $a) {
                $a = trim(mb_substr((string) $a, 0, 160));
                if ($a !== '') $anteriores[] = $a;
            }
            $anteriores = array_slice($anteriores, -10);
            $yaPropuestas = $anteriores
                ? "
Ya propuestas antes para este artículo (propón algo claramente diferente):\n- " . implode("\n- ", $anteriores)
                : '';

            $prompt = iaPromptRender($pdo, 'blog_imagen_prompt', [
                'titulo'        => $ctx['titulo'],
                'extracto'      => $ctx['extracto'],
                'keyword'       => $ctx['keyword'],
                'seccion'       => $ctx['seccion'],
                'uso'           => iaPromptUsoFrase($ctx['uso']),
                'ya_propuestas' => $yaPropuestas,
            ]);
            $r = llamarLLM($pdo, 'blog_imagen_prompt', $prompt);
            $j = $r['ok'] ? extraerJsonDeRespuesta((string) $r['texto']) : null;

            // Consultas de banco: admite el formato nuevo ("consultas") y el antiguo ("consulta")
            $consultas = [];
            foreach ((array) ($j['consultas'] ?? [$j['consulta'] ?? '']) as $q) {
                $q = trim(mb_substr((string) $q, 0, 80));
                if ($q !== '' && !in_array(mb_strtolower($q), array_map('mb_strtolower', $consultas), true)) $consultas[] = $q;
            }

            $aviso = null; $tipoAviso = 'info';
            if (!$j) {
                $aviso = 'La IA no pudo proponer nada' . ($r['ok'] ? '' : ' (' . $r['error'] . ')') . '. Escribe la búsqueda a mano.';
                $tipoAviso = 'error';
            } elseif (!$completo) {
                $aviso = 'Propuesta orientativa: solo hay título o palabra clave. Mejorará si añades la entradilla o la sección del artículo.';
            } elseif (($j['contexto_suficiente'] ?? true) === false) {
                $aviso = trim((string) ($j['aviso'] ?? '')) ?: 'La IA indica que le falta contexto: revisa la propuesta antes de usarla.';
            }

            blogJson([
                'ok'           => true,
                'consulta'     => $consultas[0] ?? $base,
                'alternativas' => array_slice($consultas, 1, 2),
                'prompt'       => trim((string) ($j['prompt'] ?? '')),
                'aviso'        => $aviso,
                'tipo_aviso'   => $tipoAviso,
            ]);

        // ── Buscar en bancos de fotos ──
        case 'buscar':
            $q = (string) ($in['q'] ?? '');
            $orient = in_array($in['orientacion'] ?? '', ['horizontal', 'vertical', 'todas'], true) ? $in['orientacion'] : 'horizontal';
            $pagina = (int) ($in['pagina'] ?? 1);
            $fuentes = ($in['fuente'] ?? 'ambos') === 'ambos' ? ['pexels', 'pixabay'] : [(string) $in['fuente']];

            $porFuente = []; $errores = []; $total = 0;
            foreach ($fuentes as $f) {
                try { $res = blogBancoBuscar($f, $q, $orient, $pagina); $porFuente[] = $res['items']; $total += $res['total']; }
                catch (Throwable $e) { $errores[] = $e->getMessage(); }
            }
            // Intercalar resultados de ambos bancos
            $items = [];
            for ($i = 0, $max = max(array_map('count', $porFuente ?: [[]])); $i < $max; $i++) {
                foreach ($porFuente as $lista) if (isset($lista[$i])) $items[] = $lista[$i];
            }
            if (!$items && $errores) blogJson(['ok' => false, 'mensaje' => implode(' ', $errores)]);
            blogJson(['ok' => true, 'items' => $items, 'total' => $total, 'avisos' => $errores]);

        // ── Generar con IA (vista previa; se paga una vez y se importa con el token) ──
        case 'generar':
            @set_time_limit(200);
            $escena = mb_substr(trim((string) ($in['prompt'] ?? '')), 0, 1500);
            if (mb_strlen($escena) < 10) blogJson(['ok' => false, 'mensaje' => 'Describe la escena que quieres generar.']);
            $claveEstilo = in_array($in['estilo'] ?? '', BLOG_IA_ESTILOS, true) ? $in['estilo'] : 'realista';
            $aspecto = in_array($in['aspecto'] ?? '', ['16:9', '4:3', '3:2', '1:1'], true) ? $in['aspecto'] : '16:9';
            // estilo (editable) + salvaguardas fijas, unidos por el catálogo
            $promptFinal = $escena . "\n\n" . iaPromptRender($pdo, 'blog_estilo_' . $claveEstilo);

            $r = llamarLLMImagen($pdo, 'blog_imagen_generar', $promptFinal, $aspecto);
            if (!$r['ok']) blogJson(['ok' => false, 'mensaje' => 'No se pudo generar la imagen: ' . $r['error']]);

            $tmp = blogGuardarTemp($r['bin']);
            $lista = iaTemporales();
            $tokenIa = bin2hex(random_bytes(12));
            $lista[$tokenIa] = ['ruta' => $tmp, 'prompt' => $promptFinal, 'modelo' => $r['modelo'], 't' => time()];
            $_SESSION['blog_ia_tmp'] = $lista;

            // Vista previa liviana para el modal
            $prev = sys_get_temp_dir() . '/blgprev_' . $tokenIa . '.webp';
            ImagenHelper::convertirWebP($tmp, $prev, 900);
            $b64 = base64_encode((string) file_get_contents($prev));
            @unlink($prev);
            blogJson(['ok' => true, 'token' => $tokenIa, 'preview' => 'data:image/webp;base64,' . $b64, 'modelo' => $r['modelo']]);

        // ── Importar: SEO con IA + optimización existente + registro ──
        case 'importar':
            @set_time_limit(120);
            $fuente = (string) ($in['fuente'] ?? '');
            $ctx = ctxArticulo($in);
            $articuloId = (int) ($in['articulo_id'] ?? 0) ?: null;
            $altsUsados = array_values(array_filter(array_map(fn($a) => mb_substr(trim((string) $a), 0, 200), (array) ($in['alts_usados'] ?? []))));
            $conLogo = !empty($in['con_logo']);
            $meta = ['con_logo' => 0, 'etiqueta_ia' => 0];
            $credito = null;
            $tmpBorrar = [];

            if ($fuente === 'ia') {
                $lista = iaTemporales();
                $t = (string) ($in['token'] ?? '');
                if (!isset($lista[$t])) blogJson(['ok' => false, 'mensaje' => 'La imagen generada caducó. Vuelve a generarla.']);
                $tmp = $lista[$t]['ruta'];
                $meta['prompt_ia']   = $lista[$t]['prompt'];
                $meta['fuente_id']   = mb_substr($lista[$t]['modelo'], 0, 60);
                $meta['etiqueta_ia'] = !empty($in['etiqueta_ia']) ? 1 : 0;
                unset($lista[$t]);
                $_SESSION['blog_ia_tmp'] = $lista;
                $tmpBorrar[] = $tmp;
            } elseif (in_array($fuente, ['pexels', 'pixabay'], true)) {
                $f = is_array($in['foto'] ?? null) ? $in['foto'] : [];
                $tmp = blogDescargarATemp((string) ($f['descarga'] ?? ''), BLOG_HOSTS_BANCO);
                $tmpBorrar[] = $tmp;
                $dominio = $fuente === 'pexels' ? 'pexels.com' : 'pixabay.com';
                $credito = [
                    'fuente'     => $fuente,
                    'nombre'     => mb_substr(trim(strip_tags((string) ($f['autor'] ?? ''))), 0, 150),
                    'url'        => urlSegura((string) ($f['autor_url'] ?? ''), [$dominio]),
                    'fuente_url' => urlSegura((string) ($f['pagina_url'] ?? ''), [$dominio]),
                ];
                $meta['credito_nombre'] = $credito['nombre'] ?: null;
                $meta['credito_url']    = $credito['url'] ?: null;
                $meta['fuente_url']     = $credito['fuente_url'] ?: null;
                $meta['fuente_id']      = mb_substr((string) ($f['id'] ?? ''), 0, 60);
            } else {
                blogJson(['ok' => false, 'mensaje' => 'Fuente de imagen no válida.']);
            }

            $out = importarPipeline($pdo, $tmp, $ctx, $altsUsados, $conLogo, $fuente, $meta, $articuloId, $tmpBorrar);
            blogJson($out + ['origen' => $fuente, 'credito' => $credito, 'etiqueta_ia' => (bool) $meta['etiqueta_ia']]);

        // ── Subir archivo propio: mismo proceso que banco/IA (visión + encuadre + optimización) ──
        case 'subir':
            @set_time_limit(120);
            if (empty($_FILES['image']['tmp_name'])) blogJson(['ok' => false, 'mensaje' => 'No se recibió ninguna imagen.']);
            $val = ImagenHelper::validar($_FILES['image']);
            if (!$val['ok']) blogJson(['ok' => false, 'mensaje' => $val['error']]);
            $ctx = ctxArticulo($in);
            $altsUsados = array_values(array_filter(array_map(fn($a) => mb_substr(trim((string) $a), 0, 200), (array) ($in['alts_usados'] ?? []))));
            $meta = ['con_logo' => 0, 'etiqueta_ia' => 0];
            $tmpBorrar = [];
            // Copia temporal propia (el temporal de PHP se borra solo al terminar la petición)
            $tmp = blogGuardarTemp((string) file_get_contents($_FILES['image']['tmp_name']));
            $tmpBorrar[] = $tmp;
            $out = importarPipeline($pdo, $tmp, $ctx, $altsUsados, !empty($in['con_logo']), 'subida', $meta, (int) ($in['articulo_id'] ?? 0) ?: null, $tmpBorrar);
            blogJson($out + ['origen' => 'subida', 'credito' => null, 'etiqueta_ia' => false]);

        default:
            blogJson(['ok' => false, 'mensaje' => 'Acción no válida.'], 400);
    }
} catch (Throwable $e) {
    blogJson(['ok' => false, 'mensaje' => $e->getMessage()]);
}
