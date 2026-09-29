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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') blogJson(['ok' => false, 'mensaje' => 'Método no permitido'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$token = $in['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validarTokenCSRF((string) $token)) blogJson(['ok' => false, 'mensaje' => 'La sesión expiró. Recargá la página.'], 403);

$pdo = obtenerConexion();
$accion = (string) ($in['accion'] ?? '');

// Estilos visuales para la IA (se añaden al prompt de escena)
const BLOG_IA_ESTILOS = [
    'realista'    => 'Photorealistic editorial photograph, natural soft warm light, shallow depth of field, full-frame camera, 35mm lens, authentic and candid, calm and respectful mood.',
    'acuarela'    => 'Soft watercolor illustration, gentle warm pastel palette, delicate brush strokes, visible paper texture, comforting and tender.',
    'minimalista' => 'Minimalist flat illustration, clean simple composition, soft muted palette, generous negative space, serene and calm.',
];
// Salvaguardas fijas: nada de texto/marcas, ni negocios o personas reales, ni escenas morbosas
const BLOG_IA_REGLAS = 'No text, no letters, no words, no logos, no watermarks, no signage, no brand names. No recognizable real people, no real businesses or facilities. Nothing graphic, morbid or medical: no visible remains, no spilled ashes, no cremation equipment.';

/** Contexto del artículo enviado por el editor, recortado. */
function ctxArticulo(array $in): array
{
    $c = is_array($in['contexto'] ?? null) ? $in['contexto'] : [];
    return [
        'titulo'   => mb_substr(trim((string) ($c['titulo'] ?? '')), 0, 255),
        'extracto' => mb_substr(trim((string) ($c['extracto'] ?? '')), 0, 600),
        'keyword'  => mb_substr(trim((string) ($c['keyword'] ?? '')), 0, 150),
        'seccion'  => mb_substr(trim((string) ($c['seccion'] ?? '')), 0, 255),
        'uso'      => ($c['uso'] ?? '') === 'portada' ? 'portada' : 'cuerpo',
    ];
}

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

            $prompt = <<<PROMPT
Eres editor gráfico de un blog en España sobre cremación de mascotas y el duelo por su pérdida.
Propón la imagen ideal para este artículo ({$ctx['uso']}).

Título: {$ctx['titulo']}
Entradilla: {$ctx['extracto']}
Palabra clave: {$ctx['keyword']}
Sección concreta donde irá: {$ctx['seccion']}

Devuelve SOLO un JSON con:
- "consulta": 2-4 palabras EN INGLÉS para buscar en un banco de fotos (Pexels/Pixabay). Concreta y visual (ej.: "old dog garden sunset", "cat urn memorial candle").
- "prompt": descripción EN INGLÉS (40-70 palabras) de una escena conceptual, cálida y respetuosa para generar con IA: sujeto, entorno, luz y composición. Sin texto, sin logotipos, sin personas reconocibles, sin nada morboso. No incluyas el estilo artístico.
PROMPT;
            $r = llamarLLM($pdo, 'blog_imagen_prompt', $prompt);
            $j = $r['ok'] ? extraerJsonDeRespuesta((string) $r['texto']) : null;
            blogJson([
                'ok'       => true,
                'consulta' => trim((string) ($j['consulta'] ?? '')) ?: $base,
                'prompt'   => trim((string) ($j['prompt'] ?? '')),
                'aviso'    => $j ? null : 'La IA no pudo proponer nada' . ($r['ok'] ? '' : ' (' . $r['error'] . ')') . '. Escribe la búsqueda a mano.',
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
            $estilo  = BLOG_IA_ESTILOS[$in['estilo'] ?? ''] ?? BLOG_IA_ESTILOS['realista'];
            $aspecto = in_array($in['aspecto'] ?? '', ['16:9', '4:3', '3:2', '1:1'], true) ? $in['aspecto'] : '16:9';
            $promptFinal = $escena . "\n\n" . $estilo . "\n" . BLOG_IA_REGLAS;

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

            try {
                $seo = blogImagenSeo($pdo, $tmp, $ctx, $altsUsados);

                $origenFinal = $tmp;
                $avisoLogo = null;
                if ($conLogo) {
                    $logo = ROOT_PATH . '/' . BLOG_LOGO_MARCA;
                    $conMarca = sys_get_temp_dir() . '/blglogo_' . bin2hex(random_bytes(4)) . '.png';
                    if (ImagenHelper::aplicarLogo($tmp, $conMarca, $logo)) {
                        $origenFinal = $conMarca;
                        $tmpBorrar[] = $conMarca;
                        $meta['con_logo'] = 1;
                    } else {
                        $avisoLogo = 'No se pudo añadir el logo (falta ' . BLOG_LOGO_MARCA . ').';
                    }
                }

                $res = blogProcesarImagen($origenFinal, $seo['slug'], 1600);
                $meta += ['alt_text' => $seo['alt'] ?: null, 'titulo' => $seo['titulo'] ?: null, 'caption' => $seo['caption'] ?: null];
                blogRegistrarImagen($articuloId, $res, $fuente, $meta);
            } finally {
                foreach ($tmpBorrar as $t) if (is_file($t)) @unlink($t);
            }

            $avisos = array_filter([$avisoLogo, $seo['ok'] ? null : 'La IA no pudo generar el texto alternativo: escríbelo a mano.']);
            blogJson([
                'ok'      => true,
                'file'    => ['url' => blogUrlArchivo($res['ruta']), 'ruta' => $res['ruta'], 'media' => $res['media'], 'ancho' => $res['ancho'], 'alto' => $res['alto']],
                'alt'     => $seo['alt'],
                'titulo'  => $seo['titulo'],
                'caption' => $seo['caption'],
                'origen'  => $fuente,
                'credito' => $credito,
                'etiqueta_ia' => (bool) $meta['etiqueta_ia'],
                'avisos'  => array_values($avisos),
            ]);

        default:
            blogJson(['ok' => false, 'mensaje' => 'Acción no válida.'], 400);
    }
} catch (Throwable $e) {
    blogJson(['ok' => false, 'mensaje' => $e->getMessage()]);
}
