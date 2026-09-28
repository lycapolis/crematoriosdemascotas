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
