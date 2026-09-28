<?php
/**
 * Componente: tarjeta de artículo del blog.
 *
 * Usado por: blog.php (índice/archivos), articulo.php (relacionados),
 * blog-relacionados.php (home + fichas).
 *
 * Variables esperadas:
 *   $art           (array) fila de blogSqlBaseArticulos()
 *   $tarjetaGrande (bool, opcional) versión horizontal destacada del índice
 *   $tarjetaNivelTitulo (string, opcional) 'h2' | 'h3' (default h3)
 */

$tg        = !empty($tarjetaGrande);
$hN        = in_array($tarjetaNivelTitulo ?? 'h3', ['h2', 'h3'], true) ? ($tarjetaNivelTitulo ?? 'h3') : 'h3';
$urlArt    = blogUrlArticulo($art['slug']);
$portada   = blogUrlArchivo($art['portada_ruta'] ?? '');
?>
<article class="blog-tarjeta<?php echo $tg ? ' blog-tarjeta--grande' : ''; ?>">
    <div class="blog-tarjeta__imagen">
        <?php if ($portada): ?>
        <img src="<?php echo limpiar($portada); ?>" alt="<?php echo limpiar($art['portada_alt'] ?: $art['titulo']); ?>" loading="lazy" decoding="async">
        <?php else: ?>
        <div class="blog-portada-vacia"><i data-lucide="paw-print" class="icono"></i></div>
        <?php endif; ?>
    </div>
    <div class="blog-tarjeta__cuerpo">
        <?php if (!empty($art['categoria_slug'])): ?>
        <a class="blog-tarjeta__categoria" href="<?php echo blogUrlTermino('categoria', $art['categoria_slug']); ?>"><?php echo limpiar($art['categoria_nombre']); ?></a>
        <?php endif; ?>
        <<?php echo $hN; ?> class="blog-tarjeta__titulo"><a href="<?php echo $urlArt; ?>"><?php echo limpiar($art['titulo']); ?></a></<?php echo $hN; ?>>
        <?php if (!empty($art['extracto'])): ?>
        <p class="blog-tarjeta__extracto"><?php echo limpiar($art['extracto']); ?></p>
        <?php endif; ?>
        <div class="blog-tarjeta__meta">
            <span><i data-lucide="calendar" class="icono"></i><time datetime="<?php echo blogFechaIso($art['publicado_at']); ?>"><?php echo blogFecha($art['publicado_at']); ?></time></span>
            <span><i data-lucide="clock" class="icono"></i><?php echo (int) $art['lectura_min']; ?> min de lectura</span>
        </div>
    </div>
</article>
<?php unset($tarjetaGrande, $tarjetaNivelTitulo);
