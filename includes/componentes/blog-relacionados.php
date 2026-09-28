<?php
/**
 * Componente: sección "Artículos" (home y fichas de negocio).
 *
 * Variables esperadas:
 *   $blogArticulos        (array) artículos a mostrar (blogArticulosHome / blogArticulosParaFicha)
 *   $blogSeccionTitulo    (string)
 *   $blogSeccionSubtitulo (string, opcional)
 *   $blogSeccionArena     (bool, opcional) fondo arena
 *   $blogSeccionContenedor (bool, opcional, default true) envolver en .contenedor
 *
 * No imprime nada si no hay artículos. Carga blog.css una sola vez.
 */

if (empty($blogArticulos)) return;
$conContenedor = $blogSeccionContenedor ?? true;
?>
<?php if (empty($GLOBALS['__blog_css_cargado'])): $GLOBALS['__blog_css_cargado'] = true; ?>
<link rel="stylesheet" href="<?php echo assetUrl('assets/css/blog.css'); ?>">
<?php endif; ?>
<section class="blog-seccion<?php echo !empty($blogSeccionArena) ? ' blog-seccion--arena' : ''; ?>" aria-labelledby="blog-seccion-titulo">
    <?php if ($conContenedor): ?><div class="contenedor"><?php endif; ?>
        <div class="blog-seccion__encabezado">
            <div>
                <h2 class="blog-seccion__titulo" id="blog-seccion-titulo"><?php echo limpiar($blogSeccionTitulo ?? 'Artículos'); ?></h2>
                <?php if (!empty($blogSeccionSubtitulo)): ?>
                <p class="blog-seccion__subtitulo"><?php echo limpiar($blogSeccionSubtitulo); ?></p>
                <?php endif; ?>
            </div>
            <a class="blog-seccion__enlace" href="<?php echo blogUrlIndice(); ?>">
                Ver todos los artículos <i data-lucide="arrow-right" class="icono"></i>
            </a>
        </div>
        <div class="blog-grid">
            <?php foreach ($blogArticulos as $art): ?>
                <?php include __DIR__ . '/blog-tarjeta.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php if ($conContenedor): ?></div><?php endif; ?>
</section>
<?php unset($blogArticulos, $blogSeccionTitulo, $blogSeccionSubtitulo, $blogSeccionArena, $blogSeccionContenedor);
