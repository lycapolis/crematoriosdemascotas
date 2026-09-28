<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BLOG — índice + archivos de taxonomía
 * ═══════════════════════════════════════════════════════════
 * /blog/                     → todos los artículos (paginado ?pagina=N)
 * /blog/{taxonomia}/{termino} → archivo (categoria, etiqueta, libres públicas)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/blog.php';

$pagina  = max(1, (int) ($_GET['pagina'] ?? 1));
$taxSlug = trim($_GET['tax'] ?? '');
$terSlug = trim($_GET['termino'] ?? '');

$taxonomia = null;
$termino   = null;
if ($taxSlug !== '' || $terSlug !== '') {
    $taxonomia = blogTaxonomiaPorSlug($taxSlug);
    $termino   = ($taxonomia && $taxonomia['publica']) ? blogTerminoPorSlug((int) $taxonomia['id'], $terSlug) : null;
    if (!$termino) {
        http_response_code(404);
        include __DIR__ . '/404.php';
        exit;
    }
}

$opts = ['pagina' => $pagina, 'por_pagina' => BLOG_POR_PAGINA];
if ($termino) {
    // Las categorías incluyen los artículos de sus subcategorías
    $opts['terminos'] = $taxonomia['jerarquica'] ? blogTerminoConDescendientes((int) $termino['id']) : [(int) $termino['id']];
} else {
    $opts['destacados_primero'] = true;
}
$res       = blogListar($opts);
$articulos = $res['items'];
$total     = $res['total'];
$paginas   = max(1, (int) ceil($total / BLOG_POR_PAGINA));

if ($pagina > $paginas && $total > 0) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Navegación por categorías (solo las que tienen artículos)
$taxCategoria = blogTaxonomiaPorSlug('categoria');
$categoriasNav = $taxCategoria
    ? array_values(array_filter(blogTerminos((int) $taxCategoria['id'], true), fn($t) => $t['total'] > 0 && (int) $t['nivel'] === 0))
    : [];

// ─── SEO ──────────────────────────────────────────────────
$urlBase = $termino ? blogUrlTermino($taxonomia['slug'], $termino['slug']) : blogUrlIndice();
$urlPag  = fn(int $p) => $urlBase . ($p > 1 ? '?pagina=' . $p : '');

if ($termino) {
    $tituloH1 = $termino['nombre'];
    $kicker   = $taxonomia['nombre_singular'] ?: $taxonomia['nombre'];
    $intro    = $termino['descripcion'] ?: 'Artículos de ' . mb_strtolower($termino['nombre']) . ' sobre la cremación y el cuidado de tus mascotas.';
    $titulo_pagina    = ($termino['meta_title'] ?: $termino['nombre'] . ' — Blog de Crematorios de Mascotas') . ($pagina > 1 ? " (página $pagina)" : '');
    $meta_descripcion = $termino['meta_description'] ?: mb_substr(blogTextoPlano($intro), 0, 158);
} else {
    $tituloH1 = 'Blog de Crematorios de Mascotas';
    $kicker   = 'Guías y consejos';
    $intro    = 'Guías prácticas, consejos y respuestas claras para acompañarte en la despedida de tu mascota: cremación, trámites, recuerdos y duelo.';
    $titulo_pagina    = 'Blog: guías sobre cremación de mascotas y duelo' . ($pagina > 1 ? " (página $pagina)" : '');
    $meta_descripcion = $intro;
}

$meta_canonical = blogUrlAbsoluta($urlPag($pagina));
// Etiquetas con pocos artículos → noindex (evita contenido pobre). Todo el
// blog en noindex mientras noindex_global esté activo (artículos de ejemplo).
$meta_robots = 'index, follow';
if (blogNoindexGlobal()) $meta_robots = 'noindex, follow';
elseif ($termino && !$taxonomia['jerarquica'] && $total < 3) $meta_robots = 'noindex, follow';
elseif ($total === 0) $meta_robots = 'noindex, follow';

$head_extra  = '<link rel="stylesheet" href="' . assetUrl('assets/css/blog.css') . '">' . "\n";
if ($pagina > 1)        $head_extra .= '<link rel="prev" href="' . htmlspecialchars(blogUrlAbsoluta($urlPag($pagina - 1))) . '">' . "\n";
if ($pagina < $paginas) $head_extra .= '<link rel="next" href="' . htmlspecialchars(blogUrlAbsoluta($urlPag($pagina + 1))) . '">' . "\n";
$GLOBALS['__blog_css_cargado'] = true;

$migas = [['Inicio', BASE_URL . '/'], ['Blog', $termino ? blogUrlIndice() : null]];
if ($termino) $migas[] = [$termino['nombre'], null];

$itemsSchema = [];
foreach ($migas as $i => [$label, $url]) {
    $itemsSchema[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $label, 'item' => $url ? blogUrlAbsoluta($url) : $meta_canonical];
}
$schema_data = [
    '@context' => 'https://schema.org',
    '@graph'   => [
        [
            '@type' => $termino ? 'CollectionPage' : 'Blog',
            'name'  => $tituloH1,
            'url'   => $meta_canonical,
            'description' => $meta_descripcion,
            'inLanguage'  => 'es-ES',
            'blogPost' => $termino ? null : array_map(fn($a) => [
                '@type' => 'BlogPosting', 'headline' => $a['titulo'], 'url' => blogUrlAbsoluta(blogUrlArticulo($a['slug'])),
                'datePublished' => blogFechaIso($a['publicado_at']),
            ], $articulos),
        ],
        ['@type' => 'BreadcrumbList', 'itemListElement' => $itemsSchema],
    ],
];
$schema_data['@graph'][0] = array_filter($schema_data['@graph'][0]);

$pagina_actual = 'blog';
include __DIR__ . '/includes/header.php';
?>

<header class="blog-cabecera">
    <div class="contenedor">
        <?php include ROOT_PATH . '/includes/componentes/breadcrumb.php'; ?>
        <span class="blog-cabecera__kicker"><i data-lucide="book-open" class="icono"></i><?php echo limpiar($kicker); ?></span>
        <h1 class="blog-cabecera__titulo"><?php echo limpiar($tituloH1); ?></h1>
        <p class="blog-cabecera__texto"><?php echo limpiar($intro); ?></p>

        <?php if ($categoriasNav): ?>
        <nav class="blog-categorias" aria-label="Categorías del blog">
            <a class="blog-categorias__chip<?php echo !$termino ? ' blog-categorias__chip--activo' : ''; ?>" href="<?php echo blogUrlIndice(); ?>">Todos</a>
            <?php foreach ($categoriasNav as $cat): $activa = $termino && (int) $termino['id'] === (int) $cat['id']; ?>
            <a class="blog-categorias__chip<?php echo $activa ? ' blog-categorias__chip--activo' : ''; ?>" href="<?php echo blogUrlTermino('categoria', $cat['slug']); ?>">
                <?php echo limpiar($cat['nombre']); ?> <span class="blog-categorias__num"><?php echo (int) $cat['total']; ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
</header>

<section class="seccion blog-listado">
    <div class="contenedor">
        <?php if (empty($articulos)): ?>
        <div class="blog-vacio">
            <i data-lucide="book-open" class="icono"></i>
            <p>Todavía no hay artículos publicados<?php echo $termino ? ' en esta sección' : ''; ?>.</p>
            <?php if ($termino): ?><a class="boton dos" href="<?php echo blogUrlIndice(); ?>">Ver todos los artículos</a><?php endif; ?>
        </div>
        <?php else: ?>
        <div class="blog-grid">
            <?php foreach ($articulos as $i => $art):
                $tarjetaGrande = (!$termino && $pagina === 1 && $i === 0);
                $tarjetaNivelTitulo = 'h2';
                include ROOT_PATH . '/includes/componentes/blog-tarjeta.php';
            endforeach; ?>
        </div>

        <?php if ($paginas > 1): ?>
        <nav class="blog-paginacion" aria-label="Paginación">
            <?php if ($pagina > 1): ?>
            <a href="<?php echo $urlPag($pagina - 1); ?>" rel="prev"><i data-lucide="chevron-left" class="icono"></i> Anterior</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $paginas; $p++): ?>
                <?php if ($p === $pagina): ?><span class="actual" aria-current="page"><?php echo $p; ?></span>
                <?php else: ?><a href="<?php echo $urlPag($p); ?>"><?php echo $p; ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($pagina < $paginas): ?>
            <a href="<?php echo $urlPag($pagina + 1); ?>" rel="next">Siguiente <i data-lucide="chevron-right" class="icono"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
