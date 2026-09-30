<?php
/**
 * ═══════════════════════════════════════════════════════════
 * BLOG — plantilla única de artículo
 * ═══════════════════════════════════════════════════════════
 * /blog/{slug} → articulo.php?slug=
 *
 * También la usa admin/blog-preview.php para la vista previa: si viene
 * definido $BLOG_PREVIEW (array con el artículo sin guardar), se renderiza
 * ese contenido con noindex y un aviso arriba.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/blog.php';

$esPreview = isset($BLOG_PREVIEW) && is_array($BLOG_PREVIEW);

if ($esPreview) {
    $art        = $BLOG_PREVIEW['articulo'];
    $terminos   = $BLOG_PREVIEW['terminos'] ?? [];
    $contexto   = $BLOG_PREVIEW['contexto'] ?? [];
} else {
    $slug = trim($_GET['slug'] ?? '');
    $art  = $slug !== '' ? blogArticuloPorSlug($slug) : null;
    if (!$art) {
        http_response_code(404);
        include __DIR__ . '/404.php';
        exit;
    }
    $terminos = blogTerminosDeArticulo((int) $art['id']);
    $st = obtenerConexion()->prepare("SELECT tipo, valor FROM blog_articulo_contexto WHERE articulo_id = :id");
    $st->execute([':id' => $art['id']]);
    $contexto = $st->fetchAll(PDO::FETCH_ASSOC);
}

$terminoIds = [];
foreach ($terminos as $lista) foreach ($lista as $t) $terminoIds[] = (int) $t['id'];

$render = blogRenderContenido($art, ['contexto' => $contexto, 'terminos' => $terminoIds]);
$toc    = ($art['mostrar_indice'] ?? 1) && count($render['toc']) >= 3 ? $render['toc'] : [];

// ─── Datos de presentación ────────────────────────────────
$urlArticulo  = blogUrlArticulo($art['slug'] ?: 'vista-previa');
$urlAbsoluta  = blogUrlAbsoluta($urlArticulo);
$portadaUrl   = blogUrlArchivo($art['portada_ruta'] ?? '');
$fechaPub     = $art['publicado_at'] ?: blogAhora();
$fechaAct     = $art['actualizado_contenido_at'] ?? null;
$mostrarAct   = $fechaAct && substr($fechaAct, 0, 10) > substr($fechaPub, 0, 10);
$autorNombre  = $art['autor_nombre'] ?? '';
$iniciales    = $autorNombre !== '' ? mb_strtoupper(mb_substr($autorNombre, 0, 1)) : '';

$migas = [['Inicio', BASE_URL . '/'], ['Blog', blogUrlIndice()]];
if (!empty($art['categoria_slug'])) $migas[] = [$art['categoria_nombre'], blogUrlTermino('categoria', $art['categoria_slug'])];
$migas[] = [$art['titulo'], null];

// ─── SEO ──────────────────────────────────────────────────
$titulo_pagina    = trim($art['meta_title'] ?? '') ?: $art['titulo'];
$meta_descripcion = trim($art['meta_description'] ?? '') ?: ($art['extracto'] ?: SITIO_DESCRIPCION);
$meta_canonical   = trim($art['canonical_url'] ?? '') ?: $urlAbsoluta;
$meta_robots      = ($esPreview || !empty($art['noindex']) || blogNoindexGlobal()) ? 'noindex, follow' : 'index, follow, max-image-preview:large';
$og_type          = 'article';
if ($portadaUrl) $og_image = blogUrlAbsoluta($portadaUrl);
$schema_data      = blogSchemaArticulo($art, $render, $migas, $meta_canonical);

$head_extra  = '<link rel="stylesheet" href="' . assetUrl('assets/css/blog.css') . '">' . "\n";
$head_extra .= '<meta property="article:published_time" content="' . blogFechaIso($fechaPub) . '">' . "\n";
if ($mostrarAct) $head_extra .= '<meta property="article:modified_time" content="' . blogFechaIso($fechaAct) . '">' . "\n";
if (!empty($art['categoria_nombre'])) $head_extra .= '<meta property="article:section" content="' . limpiar($art['categoria_nombre']) . '">' . "\n";
foreach ($terminos['etiqueta'] ?? [] as $t) $head_extra .= '<meta property="article:tag" content="' . limpiar($t['nombre']) . '">' . "\n";
if ($portadaUrl) $head_extra .= '<link rel="preload" as="image" href="' . limpiar($portadaUrl) . '">' . "\n";
$GLOBALS['__blog_css_cargado'] = true;

$relacionados = $esPreview ? blogListar(['por_pagina' => 3, 'excluir' => [(int) ($art['id'] ?? 0)]])['items'] : blogRelacionadosDeArticulo($art, 3);

$pagina_actual = 'blog';
include __DIR__ . '/includes/header.php';

$tocHtml = function (string $variante) use ($toc) {
    if (!$toc) return '';
    $abierto = $variante === 'desktop' ? ' open' : '';
    $h  = '<details class="articulo-indice articulo-indice--' . $variante . '"' . $abierto . '>';
    $h .= '<summary>En este artículo <i data-lucide="chevron-down" class="icono"></i></summary><ol>';
    foreach ($toc as $item) {
        $h .= '<li class="nivel-' . (int) $item['nivel'] . '"><a href="#' . limpiar($item['id']) . '">' . limpiar($item['texto']) . '</a></li>';
    }
    return $h . '</ol></details>';
};
?>

<?php if ($esPreview): ?>
<div class="articulo-preview-aviso"><strong>Vista previa</strong> — así se verá el artículo publicado. Esta página no es pública.</div>
<?php endif; ?>
<div class="articulo-progreso" id="articulo-progreso" aria-hidden="true"></div>

<article class="articulo">
    <div class="contenedor">
        <header class="articulo__cabecera">
            <?php include ROOT_PATH . '/includes/componentes/breadcrumb.php'; ?>

            <?php if (!empty($art['categoria_slug'])): ?>
            <a class="articulo__categoria" href="<?php echo blogUrlTermino('categoria', $art['categoria_slug']); ?>"><?php echo limpiar($art['categoria_nombre']); ?></a>
            <?php endif; ?>

            <h1 class="articulo__titulo"><?php echo limpiar($art['titulo']); ?></h1>

            <?php if (!empty($art['extracto'])): ?>
            <p class="articulo__extracto"><?php echo limpiar($art['extracto']); ?></p>
            <?php endif; ?>

            <div class="articulo__meta">
                <?php if ($autorNombre !== ''): ?>
                <div class="articulo__autor">
                    <span class="articulo__avatar">
                        <?php if (!empty($art['autor_foto'])): ?>
                        <img src="<?php echo limpiar(blogUrlArchivo($art['autor_foto'])); ?>" alt="<?php echo limpiar($autorNombre); ?>">
                        <?php else: ?><?php echo limpiar($iniciales); ?><?php endif; ?>
                    </span>
                    <span>
                        <span class="articulo__autor-nombre"><?php echo limpiar($autorNombre); ?></span>
                        <?php if (!empty($art['autor_cargo'])): ?><span class="articulo__autor-cargo"><?php echo limpiar($art['autor_cargo']); ?></span><?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                <span class="articulo__dato"><i data-lucide="calendar" class="icono"></i>
                    <?php if ($mostrarAct): ?>Actualizado el <time datetime="<?php echo blogFechaIso($fechaAct); ?>"><?php echo blogFecha($fechaAct); ?></time>
                    <?php else: ?><time datetime="<?php echo blogFechaIso($fechaPub); ?>"><?php echo blogFecha($fechaPub); ?></time><?php endif; ?>
                </span>
                <span class="articulo__dato"><i data-lucide="clock" class="icono"></i><?php echo (int) ($art['lectura_min'] ?? 1); ?> min de lectura</span>
                <?php if (!empty($art['revisor_nombre'])): ?>
                <span class="articulo__revisado"><i data-lucide="shield-check" class="icono"></i>Revisado por <?php echo limpiar($art['revisor_nombre']); ?></span>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($portadaUrl): ?>
        <figure class="articulo__portada">
            <img src="<?php echo limpiar($portadaUrl); ?>" alt="<?php echo limpiar($art['portada_alt'] ?: $art['titulo']); ?>" fetchpriority="high" decoding="async">
            <?php if ($creditoPortada = blogCreditoPortada($art['portada_ruta'] ?? '')): ?>
            <?php echo $creditoPortada; ?>
            <?php endif; ?>
        </figure>
        <?php endif; ?>

        <div class="articulo__layout">
            <div class="articulo__cuerpo">
                <?php echo $tocHtml('mobile'); ?>

                <div class="articulo-contenido">
                    <?php echo $render['html']; ?>
                </div>

                <footer class="articulo-pie">
                    <?php if (!empty($terminos['etiqueta'])): ?>
                    <div class="articulo-etiquetas">
                        <span class="articulo-etiquetas__label">Etiquetas:</span>
                        <?php foreach ($terminos['etiqueta'] as $t): ?>
                        <a href="<?php echo blogUrlTermino('etiqueta', $t['slug']); ?>">#<?php echo limpiar($t['nombre']); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="articulo-compartir">
                        <span class="articulo-compartir__label">Compartir:</span>
                        <a href="https://wa.me/?text=<?php echo rawurlencode($art['titulo'] . ' ' . $urlAbsoluta); ?>" target="_blank" rel="noopener" aria-label="Compartir por WhatsApp" title="WhatsApp"><i data-lucide="message-circle" class="icono"></i></a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($urlAbsoluta); ?>" target="_blank" rel="noopener" aria-label="Compartir en Facebook" title="Facebook"><i data-lucide="facebook" class="icono"></i></a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo rawurlencode($urlAbsoluta); ?>&amp;text=<?php echo rawurlencode($art['titulo']); ?>" target="_blank" rel="noopener" aria-label="Compartir en X" title="X"><i data-lucide="twitter" class="icono"></i></a>
                        <a href="mailto:?subject=<?php echo rawurlencode($art['titulo']); ?>&amp;body=<?php echo rawurlencode($urlAbsoluta); ?>" aria-label="Compartir por email" title="Email"><i data-lucide="mail" class="icono"></i></a>
                        <button type="button" data-copiar-enlace="<?php echo limpiar($urlAbsoluta); ?>" aria-label="Copiar enlace" title="Copiar enlace"><i data-lucide="link" class="icono"></i></button>
                    </div>

                    <?php if ($autorNombre !== ''): ?>
                    <section class="articulo-autor-caja" aria-label="Sobre el autor">
                        <span class="articulo__avatar">
                            <?php if (!empty($art['autor_foto'])): ?>
                            <img src="<?php echo limpiar(blogUrlArchivo($art['autor_foto'])); ?>" alt="<?php echo limpiar($autorNombre); ?>">
                            <?php else: ?><?php echo limpiar($iniciales); ?><?php endif; ?>
                        </span>
                        <div>
                            <span class="articulo-autor-caja__kicker">Escrito por</span>
                            <p class="articulo-autor-caja__nombre"><?php echo limpiar($autorNombre); ?></p>
                            <?php if (!empty($art['autor_cargo'])): ?><span class="articulo-autor-caja__cargo"><?php echo limpiar($art['autor_cargo']); ?></span><?php endif; ?>
                            <?php if (!empty($art['autor_bio'])): ?><p class="articulo-autor-caja__bio"><?php echo nl2br(limpiar($art['autor_bio'])); ?></p><?php endif; ?>
                            <?php if (!empty($art['autor_credenciales'])): ?>
                            <p class="articulo-autor-caja__cred"><i data-lucide="award" class="icono"></i><?php echo limpiar($art['autor_credenciales']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($art['revisor_nombre'])): ?>
                            <p class="articulo-autor-caja__cred"><i data-lucide="shield-check" class="icono"></i>Revisado por <?php echo limpiar($art['revisor_nombre']); ?><?php echo !empty($art['revisor_cargo']) ? ', ' . limpiar($art['revisor_cargo']) : ''; ?></p>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <p class="articulo-aviso-medico">
                        Este artículo es informativo y no sustituye el consejo de tu veterinario ni de un profesional del sector.
                        Los servicios, plazos y condiciones pueden variar según cada crematorio y cada comunidad autónoma.
                    </p>
                </footer>
            </div>

            <aside class="articulo__lateral">
                <?php echo $tocHtml('desktop'); ?>
                <div class="articulo-caja">
                    <p class="articulo-caja__titulo">¿Buscas un crematorio de mascotas?</p>
                    <p class="articulo-caja__texto">Compara servicios, reseñas y precios de crematorios cerca de ti.</p>
                    <a class="boton uno" href="<?php echo BASE_URL; ?>/directorio.php"><i data-lucide="search" class="icono"></i> Ver el directorio</a>
                </div>
            </aside>
        </div>
    </div>
</article>

<?php
$blogArticulos        = $relacionados;
$blogSeccionTitulo    = 'Sigue leyendo';
$blogSeccionSubtitulo = 'Otros artículos que pueden ayudarte';
$blogSeccionArena     = true;
include ROOT_PATH . '/includes/componentes/blog-relacionados.php';
?>

<script>
(function () {
    // Barra de progreso de lectura
    var barra = document.getElementById('articulo-progreso');
    var cuerpo = document.querySelector('.articulo-contenido');
    function progreso() {
        if (!barra || !cuerpo) return;
        var r = cuerpo.getBoundingClientRect();
        var total = r.height - window.innerHeight * 0.6;
        var hecho = Math.min(Math.max(-r.top + 120, 0), Math.max(total, 1));
        barra.style.width = (hecho / Math.max(total, 1) * 100) + '%';
    }
    window.addEventListener('scroll', progreso, { passive: true });
    progreso();

    // Resaltar la sección actual en el índice (desktop)
    var enlaces = document.querySelectorAll('.articulo-indice--desktop a');
    if (enlaces.length && 'IntersectionObserver' in window) {
        var porId = {};
        enlaces.forEach(function (a) { porId[a.getAttribute('href').slice(1)] = a; });
        var obs = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) {
                if (e.isIntersecting && porId[e.target.id]) {
                    enlaces.forEach(function (a) { a.classList.remove('activo'); });
                    porId[e.target.id].classList.add('activo');
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });
        Object.keys(porId).forEach(function (id) { var el = document.getElementById(id); if (el) obs.observe(el); });
    }
    // En mobile, cerrar el índice al tocar un enlace
    document.querySelectorAll('.articulo-indice--mobile a').forEach(function (a) {
        a.addEventListener('click', function () { a.closest('details').removeAttribute('open'); });
    });

    // Copiar enlace
    document.querySelectorAll('[data-copiar-enlace]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-copiar-enlace');
            var ok = function () { if (window.toast && toast.ok) toast.ok('Enlace copiado'); };
            if (navigator.clipboard) navigator.clipboard.writeText(url).then(ok);
        });
    });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
