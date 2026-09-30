<?php
/**
 * Arranque común de las páginas admin del blog:
 * auth + permiso 'blog' + núcleo del blog + sub-navegación.
 */

require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/blog.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();
requierePermiso('blog');

/** Responde JSON y termina (endpoints AJAX). */
function blogJson(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Valida el token CSRF de un POST (campo csrf_token o header X-CSRF-Token). */
function blogValidarCsrf(): void
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validarTokenCSRF((string) $token)) {
        blogJson(['ok' => false, 'mensaje' => 'La sesión expiró. Recargá la página e intentá de nuevo.'], 403);
    }
}

/** Slug único dentro de una tabla (con sufijo -2, -3…). */
function blogSlugUnico(string $tabla, string $base, int $excluirId = 0, array $extraWhere = []): string
{
    $pdo  = obtenerConexion();
    $base = slugificar($base) ?: 'sin-titulo';
    $base = mb_substr($base, 0, 180);
    $slug = $base;
    $n = 2;
    while (true) {
        $sql = "SELECT COUNT(*) FROM $tabla WHERE slug = :s AND id <> :id";
        $params = [':s' => $slug, ':id' => $excluirId];
        foreach ($extraWhere as $col => $val) { $sql .= " AND $col = :w_$col"; $params[":w_$col"] = $val; }
        $st = $pdo->prepare($sql);
        $st->execute($params);
        if ((int) $st->fetchColumn() === 0) return $slug;
        $slug = $base . '-' . $n++;
    }
}

/**
 * Procesa una imagen (archivo temporal) → WebP en uploads/blog/AAAA/MM/.
 * Genera versión grande (máx 1600px) y media (800px) para srcset.
 * @return array{ruta: string, media: string, ancho: int, alto: int}
 */
function blogProcesarImagen(string $tmp, string $nombreBase, int $maxAncho = 1600): array
{
    $dirRel = BLOG_DIR_UPLOADS . '/' . date('Y') . '/' . date('m');
    $dirAbs = ROOT_PATH . '/' . $dirRel;
    if (!is_dir($dirAbs)) mkdir($dirAbs, 0755, true);

    $base = mb_substr(slugificar($nombreBase) ?: 'imagen', 0, 60) . '-' . substr(bin2hex(random_bytes(4)), 0, 6);
    $grande = "$dirRel/$base.webp";
    $media  = "$dirRel/$base-800.webp";

    if (!ImagenHelper::convertirWebP($tmp, ROOT_PATH . '/' . $grande, $maxAncho)) {
        throw new Exception('No se pudo convertir la imagen.');
    }
    [$ancho, $alto] = getimagesize(ROOT_PATH . '/' . $grande) ?: [0, 0];
    $rutaMedia = '';
    if ($ancho > 900 && ImagenHelper::convertirWebP($tmp, ROOT_PATH . '/' . $media, 800)) {
        $rutaMedia = $media;
    }
    return ['ruta' => $grande, 'media' => $rutaMedia, 'ancho' => (int) $ancho, 'alto' => (int) $alto];
}

/** Logo que se estampa (opcional) en imágenes importadas/generadas desde el editor. */
const BLOG_LOGO_MARCA = 'assets/img/marca/logo-marca-agua.png';
/** Versión para fondos oscuros (patita terracota + texto claro); opcional. */
const BLOG_LOGO_MARCA_OSCURO = 'assets/img/marca/logo-marca-agua-fondo-oscuro.png';

/** Hosts desde los que se permite importar fotos de banco. */
const BLOG_HOSTS_BANCO = ['images.pexels.com', 'pixabay.com', 'cdn.pixabay.com'];

/** Guarda un binario en un archivo temporal y valida que sea una imagen admitida. */
function blogGuardarTemp(string $bin): string
{
    if (strlen($bin) > ImagenHelper::MAX_SIZE_MB * 1024 * 1024 * 3) throw new Exception('La imagen es demasiado grande.');
    $tmp = tempnam(sys_get_temp_dir(), 'blg');
    file_put_contents($tmp, $bin);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (!in_array($finfo->file($tmp), ImagenHelper::TIPOS_PERMITIDOS, true) || !@getimagesize($tmp)) {
        @unlink($tmp);
        throw new Exception('El archivo no es una imagen válida (JPG, PNG, GIF o WebP).');
    }
    return $tmp;
}

/**
 * Descarga una imagen remota a un archivo temporal (protección SSRF: solo
 * http/https hacia IPs públicas; opcionalmente, solo hosts de una lista).
 */
function blogDescargarATemp(string $url, array $hostsPermitidos = []): string
{
    if (!preg_match('#^https?://#i', $url)) throw new Exception('URL de imagen no válida.');
    $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
    if ($hostsPermitidos && !in_array($host, $hostsPermitidos, true)) throw new Exception('Origen de imagen no permitido.');
    $ip = gethostbyname($host);
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        throw new Exception('No se pueden descargar imágenes de esa dirección.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BlogImageFetcher/1.0)',
    ]);
    $bin = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($bin === false || $codigo >= 400) throw new Exception('No se pudo descargar la imagen desde la URL.');
    return blogGuardarTemp($bin);
}

/**
 * Registra una imagen procesada en blog_imagenes.
 * $meta admite: alt_text, titulo, caption, credito_nombre, credito_url, fuente_id,
 * fuente_url, prompt_ia, etiqueta_ia, con_logo (solo se insertan las presentes,
 * así la subida clásica sigue funcionando aunque falte la migración de columnas).
 */
function blogRegistrarImagen(?int $articuloId, array $res, string $origen, array $meta = []): int
{
    $cols = ['articulo_id' => $articuloId, 'ruta' => $res['ruta'], 'ruta_media' => $res['media'] ?: null,
             'ancho' => $res['ancho'], 'alto' => $res['alto'], 'origen' => $origen];
    $permitidas = ['alt_text', 'titulo', 'caption', 'credito_nombre', 'credito_url', 'fuente_id', 'fuente_url', 'prompt_ia', 'etiqueta_ia', 'con_logo'];
    foreach ($permitidas as $c) if (array_key_exists($c, $meta)) $cols[$c] = $meta[$c];

    $pdo = obtenerConexion();
    $pdo->prepare('INSERT INTO blog_imagenes (' . implode(', ', array_keys($cols)) . ') VALUES (:' . implode(', :', array_keys($cols)) . ')')
        ->execute(array_combine(array_map(fn($c) => ":$c", array_keys($cols)), array_values($cols)));
    return (int) $pdo->lastInsertId();
}

/**
 * Recorta un texto sin dejar frases a medias: si se pasa de $max, se queda con
 * la primera frase si es suficientemente larga; si no, corta en la última
 * palabra completa y quita la puntuación colgante.
 */
function blogRecortarTexto(string $texto, int $max): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    if (mb_strlen($texto) <= $max) return $texto;
    if (preg_match('/^(.{40,' . $max . '}?[.!?])\s/u', $texto, $m)) return rtrim($m[1], '.');
    $corte = mb_substr($texto, 0, $max);
    // Preferir cortar en la última coma (cláusula completa); si no, en la última palabra
    $coma = mb_strrpos($corte, ',');
    if ($coma !== false && $coma >= $max * 0.45) return rtrim(mb_substr($corte, 0, $coma));
    $esp = mb_strrpos($corte, ' ');
    if ($esp !== false) $corte = mb_substr($corte, 0, $esp);
    // No terminar en preposición/artículo suelto
    $corte = preg_replace('/\s+(de|del|la|las|el|los|y|e|o|u|a|en|con|por|para|que|un|una|su|sus|al)$/iu', '', $corte);
    return rtrim($corte, " ,;:-—");
}

/**
 * Pide a la IA (visión: ve la imagen + lee el artículo) el alt, el nombre de
 * archivo, el title y un pie de foto. Sección 'blog_imagen_seo'.
 * Si falla, devuelve un slug derivado del título y alt vacío (se revisa a mano).
 *
 * @param array $ctx  titulo, extracto, keyword, seccion (subtítulo cercano), uso (portada|cuerpo)
 * @return array{alt:string, slug:string, titulo:string, caption:string, ok:bool}
 */
function blogImagenSeo(PDO $pdo, string $tmp, array $ctx, array $altsUsados = []): array
{
    $fallback = ['alt' => '', 'slug' => slugificar($ctx['keyword'] ?: $ctx['titulo'] ?: 'imagen-blog'), 'titulo' => '', 'caption' => '', 'foco' => null, 'ok' => false];

    // Versión reducida para el modelo (más barata y rápida)
    $mini = sys_get_temp_dir() . '/blgseo_' . bin2hex(random_bytes(4)) . '.webp';
    try { ImagenHelper::convertirWebP($tmp, $mini, 768); } catch (Exception $e) { return $fallback; }
    $base64 = base64_encode((string) file_get_contents($mini));
    @unlink($mini);

    $prohibidos = '';
    if ($altsUsados) {
        $prohibidos = "\nAlt texts YA USADOS en este artículo (el tuyo debe ser claramente distinto):\n";
        foreach (array_slice($altsUsados, 0, 20) as $a) $prohibidos .= '- "' . str_replace('"', "'", $a) . "\"\n";
    }
    $uso = ($ctx['uso'] ?? '') === 'portada' ? 'imagen de portada (destacada) del artículo' : 'imagen dentro del cuerpo del artículo';
    $titulo   = $ctx['titulo'] ?? '';
    $extracto = mb_substr($ctx['extracto'] ?? '', 0, 400);
    $keyword  = $ctx['keyword'] ?? '';
    $seccion  = $ctx['seccion'] ?? '';

    $prompt = <<<PROMPT
Eres un experto en SEO y accesibilidad para un blog sobre cremación de mascotas y duelo en España.
Mira la imagen y genera sus metadatos. Uso: $uso.

Artículo: "$titulo"
Entradilla: $extracto
Palabra clave principal: $keyword
Sección donde va la imagen: $seccion
$prohibidos
Devuelve SOLO un JSON con estas claves:
- "alt": UNA sola frase completa de 60 a 120 caracteres. Describe de forma concreta lo que SE VE en la imagen (no inventes nada que no aparezca) y conéctalo con el tema del artículo usando la palabra clave solo si encaja de forma natural. Español de España, sin "imagen de" ni "foto de" al inicio.
- "slug": nombre de archivo de 3 a 6 palabras, minúsculas, separadas por guiones, sin acentos, sin artículos ni fechas. Ej.: "perro-mayor-jardin-atardecer".
- "titulo": título corto de la imagen (máx. 70 caracteres).
- "caption": pie de foto opcional de UNA frase de 140 caracteres como máximo que aporte contexto útil al lector sobre el tema del artículo; en tono cálido y respetuoso. Cadena vacía si no aporta nada.
PROMPT;
    $esPortada = ($ctx['uso'] ?? '') === 'portada';
    if ($esPortada) {
        $prompt .= <<<'FOCO'

- "foco": cajas que delimitan lo importante de la imagen, para decidir un recorte panorámico 16:9 sin esconder nada esencial. Objeto con dos cajas, cada una {"x_min":..,"y_min":..,"x_max":..,"y_max":..} con enteros de 0 a 1000 relativos al ancho y alto de la imagen (0,0 = esquina superior izquierda):
  · "completo": todo lo esencial de la escena (sujeto principal entero y lo que le da sentido, p. ej. la persona y las flores que toca). No incluyas fondo ni elementos decorativos sueltos.
  · "principal": solo el motivo protagonista, lo mínimo que nunca debe cortarse (en una persona, cabeza y manos y el objeto con el que interactúa; en un animal, el animal entero; en un objeto, el objeto). Ajustada, sin margen extra.
FOCO;
    }
    // Imágenes subidas a mano: puede que ya lleven nuestra marca de agua (p. ej. una portada
    // descargada y vuelta a subir). Se pregunta en la misma llamada para no duplicar el logo.
    $detectarMarca = !empty($ctx['detectar_marca']);
    if ($detectarMarca) {
        $prompt .= <<<'MARCA'

- "marca_previa": true si la imagen YA muestra en alguna esquina la marca de agua o logotipo de "Crematorios de Mascotas" (texto "Crematorios de Mascotas" con una pequeña huella de pata); false en cualquier otro caso (otros logos no cuentan).
MARCA;
    }

    $resp = llamarLLM($pdo, 'blog_imagen_seo', $prompt, $base64, 'image/webp');
    if (!$resp['ok']) return $fallback + ['error' => $resp['error']];
    $j = extraerJsonDeRespuesta((string) $resp['texto']);
    if (!$j) return $fallback;

    $alt  = blogRecortarTexto(trim((string) ($j['alt'] ?? ''), " \"'"), 125);
    $slug = slugificar((string) ($j['slug'] ?? '')) ?: $fallback['slug'];
    return [
        'alt'     => mb_strlen($alt) >= 15 ? $alt : '',
        'slug'    => mb_substr($slug, 0, 60),
        'titulo'  => mb_substr(trim((string) ($j['titulo'] ?? '')), 0, 120),
        'caption' => blogRecortarTexto(trim((string) ($j['caption'] ?? '')), 160),
        'foco'    => $esPortada ? blogNormalizarFoco($j['foco'] ?? null) : null,
        'marca_previa' => $detectarMarca && filter_var($j['marca_previa'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'ok'      => true,
    ];
}

/**
 * Valida la caja que devuelve la IA (enteros 0-1000) y la pasa a 0..1
 * ['x0','y0','x1','y1']. Null si falta, está invertida o es absurdamente pequeña.
 */
function blogNormalizarFoco($foco): ?array
{
    if (!is_array($foco)) return null;
    $caja = function ($c): ?array {
        if (!is_array($c)) return null;
        $v = fn($k) => isset($c[$k]) && is_numeric($c[$k]) ? max(0.0, min(1000.0, (float) $c[$k])) / 1000 : null;
        [$x0, $y0, $x1, $y1] = [$v('x_min'), $v('y_min'), $v('x_max'), $v('y_max')];
        if (in_array(null, [$x0, $y0, $x1, $y1], true) || $x1 - $x0 < 0.05 || $y1 - $y0 < 0.05) return null;
        return ['x0' => $x0, 'y0' => $y0, 'x1' => $x1, 'y1' => $y1];
    };
    // Compatibilidad: si el modelo devuelve una sola caja plana, se toma como "completo"
    $completo  = $caja($foco['completo'] ?? (isset($foco['x_min']) ? $foco : null));
    $principal = $caja($foco['principal'] ?? null);
    $res = $completo ?? $principal;
    if (!$res) return null;
    if ($principal && $completo) $res['min'] = $principal;
    return $res;
}

/**
 * Encuadra a 16:9 una imagen de portada (según la caja de la IA) y devuelve la ruta
 * del PNG resultante (para pasarlo luego por el logo y el WebP), o null si falla.
 * $modo: 'igual' | 'recorte' | 'recorte-centrado' | 'fondo'
 */
function blogEncuadrarPortada(string $tmp, ?array $foco, ?string &$modo = null, ?array &$ventana = null): ?string
{
    $dest = sys_get_temp_dir() . '/blgenc_' . bin2hex(random_bytes(4)) . '.png';
    $r = ImagenHelper::encuadrar16x9($tmp, $dest, $foco, 1600, $ventana);
    if ($r === false) return null;
    $modo = $r;
    return $dest;
}

/**
 * Busca fotos en Pexels o Pixabay y devuelve resultados normalizados.
 * Resultados cacheados 24 h (Pixabay lo exige; a Pexels le ahorra cupo).
 *
 * @param string $orientacion horizontal | vertical | todas
 * @return array{items: array, total: int}
 */
function blogBancoBuscar(string $fuente, string $q, string $orientacion = 'horizontal', int $pagina = 1): array
{
    $q = mb_substr(trim($q), 0, 100);
    if ($q === '') return ['items' => [], 'total' => 0];
    $pagina = max(1, min(50, $pagina));
    $porPagina = 24;

    $dirCache = sys_get_temp_dir() . '/blog-banco-cache';
    if (!is_dir($dirCache)) @mkdir($dirCache, 0755, true);
    $archivoCache = $dirCache . '/' . md5("$fuente|$q|$orientacion|$pagina") . '.json';
    if (is_file($archivoCache) && filemtime($archivoCache) > time() - 86400) {
        $cache = json_decode((string) file_get_contents($archivoCache), true);
        if (is_array($cache)) return $cache;
    }

    if ($fuente === 'pexels') {
        $clave = defined('PEXELS_API_KEY') ? (string) PEXELS_API_KEY : '';
        if ($clave === '') throw new Exception('Falta PEXELS_API_KEY en el .env del servidor.');
        $params = ['query' => $q, 'per_page' => $porPagina, 'page' => $pagina, 'locale' => 'es-ES'];
        if ($orientacion === 'horizontal') $params['orientation'] = 'landscape';
        if ($orientacion === 'vertical')   $params['orientation'] = 'portrait';
        $url = 'https://api.pexels.com/v1/search?' . http_build_query($params);
        $cabeceras = ['Authorization: ' . $clave];
    } elseif ($fuente === 'pixabay') {
        $clave = defined('PIXABAY_API_KEY') ? (string) PIXABAY_API_KEY : '';
        if ($clave === '') throw new Exception('Falta PIXABAY_API_KEY en el .env del servidor.');
        $params = ['key' => $clave, 'q' => $q, 'lang' => 'es', 'image_type' => 'photo', 'safesearch' => 'true',
                   'per_page' => $porPagina, 'page' => $pagina,
                   'orientation' => ['horizontal' => 'horizontal', 'vertical' => 'vertical'][$orientacion] ?? 'all'];
        $url = 'https://pixabay.com/api/?' . http_build_query($params);
        $cabeceras = [];
    } else {
        throw new Exception('Banco de imágenes no válido.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $cabeceras]);
    $resp = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $codigo !== 200) throw new Exception(ucfirst($fuente) . " no respondió (HTTP $codigo).");
    $data = json_decode((string) $resp, true) ?: [];

    $items = [];
    if ($fuente === 'pexels') {
        foreach ($data['photos'] ?? [] as $f) {
            $items[] = [
                'fuente' => 'pexels', 'id' => (string) $f['id'],
                'miniatura' => $f['src']['medium'] ?? '', 'descarga' => ($f['src']['original'] ?? '') . '?auto=compress&cs=tinysrgb&w=2000',
                'ancho' => (int) $f['width'], 'alto' => (int) $f['height'], 'descripcion' => (string) ($f['alt'] ?? ''),
                'autor' => (string) ($f['photographer'] ?? ''), 'autor_url' => (string) ($f['photographer_url'] ?? ''),
                'pagina_url' => (string) ($f['url'] ?? ''),
            ];
        }
        $total = (int) ($data['total_results'] ?? 0);
    } else {
        foreach ($data['hits'] ?? [] as $f) {
            $items[] = [
                'fuente' => 'pixabay', 'id' => (string) $f['id'],
                'miniatura' => $f['webformatURL'] ?? '', 'descarga' => $f['largeImageURL'] ?? '',
                'ancho' => (int) $f['imageWidth'], 'alto' => (int) $f['imageHeight'], 'descripcion' => (string) ($f['tags'] ?? ''),
                'autor' => (string) ($f['user'] ?? ''), 'autor_url' => 'https://pixabay.com/users/' . rawurlencode((string) ($f['user'] ?? '')) . '-' . (int) ($f['user_id'] ?? 0) . '/',
                'pagina_url' => (string) ($f['pageURL'] ?? ''),
            ];
        }
        $total = (int) ($data['totalHits'] ?? 0);
    }

    $resultado = ['items' => $items, 'total' => $total];
    @file_put_contents($archivoCache, json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $resultado;
}

/** Sub-navegación compartida de la sección Blog del admin. */
function blogSubnav(string $actual): void
{
    $items = [
        'articulos'  => ['blog-articulos.php',  'Artículos',                 'file-text'],
        'taxonomias' => ['blog-taxonomias.php', 'Categorías y etiquetas',    'tags'],
        'modulos'    => ['blog-modulos.php',    'Módulos especiales',        'layout-template'],
        'autores'    => ['blog-autores.php',    'Autores',                   'users'],
    ];
    echo '<nav class="blog-admin-subnav" aria-label="Secciones del blog">';
    foreach ($items as $clave => [$href, $label, $icono]) {
        $cls = 'blog-admin-subnav__link' . ($clave === $actual ? ' blog-admin-subnav__link--activo' : '');
        echo '<a class="' . $cls . '" href="' . $href . '"><i data-lucide="' . $icono . '" class="icono"></i>' . $label . '</a>';
    }
    echo '<a class="blog-admin-subnav__link blog-admin-subnav__link--externo" href="' . blogUrlIndice() . '" target="_blank"><i data-lucide="external-link" class="icono"></i>Ver blog</a>';
    echo '</nav>';
}

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

/**
 * Proceso común de una imagen nueva (banco, IA o subida):
 * visión (alt/slug/title/pie + foco) → [portada] encuadre 16:9 → [logo] → WebP → registro.
 * Borra los temporales de $tmpBorrar al terminar.
 *
 * @return array datos listos para el editor (ok, file, alt, titulo, caption, avisos)
 */
function importarPipeline(PDO $pdo, string $tmp, array $ctx, array $altsUsados, bool $conLogo, string $origen, array &$meta, ?int $articuloId, array $tmpBorrar): array
{
    $avisos = [];
    try {
        $ctx['detectar_marca'] = in_array($origen, ['subida', 'url', 'pegada'], true);
        $seo = blogImagenSeo($pdo, $tmp, $ctx, $altsUsados);
        if (!$seo['ok']) $avisos[] = 'La IA no pudo generar el texto alternativo: escríbelo a mano.';

        $origenFinal = $tmp;
        $ventana = null;

        // Portada: 16:9 sin esconder lo importante (antes del logo, para que el logo quede dentro)
        if ($ctx['uso'] === 'portada') {
            $enc = blogEncuadrarPortada($tmp, $seo['foco'], $modo, $ventana);
            if ($enc) {
                $origenFinal = $enc;
                $tmpBorrar[] = $enc;
                if ($modo === 'recorte-centrado') $avisos[] = 'No se pudo localizar el motivo principal: la portada se recortó centrada. Revisa cómo ha quedado.';
                if ($modo === 'fondo') $avisos[] = 'La foto no cabe en 16:9 sin cortar lo importante: se ha ajustado entera sobre un fondo desenfocado.';
            } else {
                $avisos[] = 'No se pudo encuadrar la portada a 16:9.';
            }
        }

        // Ya traía el logo: se omite el paso sin avisar... salvo que el encuadre de la portada
        // lo haya recortado (se conoce la zona conservada), en cuyo caso se estampa uno nuevo.
        if ($conLogo && !empty($seo['marca_previa']) && ImagenHelper::logoSobreviveAlRecorte($ventana)) {
            $conLogo = false;
            $meta['con_logo'] = 1;
        }
        if ($conLogo) {
            $logo = ROOT_PATH . '/' . BLOG_LOGO_MARCA;
            $conMarca = sys_get_temp_dir() . '/blglogo_' . bin2hex(random_bytes(4)) . '.png';
            if (ImagenHelper::aplicarLogo($origenFinal, $conMarca, $logo, 0.14, 90, ROOT_PATH . '/' . BLOG_LOGO_MARCA_OSCURO)) {
                $origenFinal = $conMarca;
                $tmpBorrar[] = $conMarca;
                $meta['con_logo'] = 1;
            } else {
                $avisos[] = 'No se pudo añadir el logo (falta ' . BLOG_LOGO_MARCA . ').';
            }
        }

        $res = blogProcesarImagen($origenFinal, $seo['slug'], 1600);
        $meta += ['alt_text' => $seo['alt'] ?: null, 'titulo' => $seo['titulo'] ?: null, 'caption' => $seo['caption'] ?: null];
        blogRegistrarImagen($articuloId, $res, $origen, $meta);
    } finally {
        foreach ($tmpBorrar as $t) if (is_file($t)) @unlink($t);
    }

    return [
        'ok'      => true,
        'file'    => ['url' => blogUrlArchivo($res['ruta']), 'ruta' => $res['ruta'], 'media' => $res['media'], 'ancho' => $res['ancho'], 'alto' => $res['alto']],
        'alt'     => $seo['alt'],
        'titulo'  => $seo['titulo'],
        'caption' => $seo['caption'],
        'avisos'  => $avisos,
    ];
}
