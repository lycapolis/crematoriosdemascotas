<?php
/**
 * Componente: Tarjeta de Crematorio
 *
 * Variables esperadas:
 * - $crem: array con datos del crematorio
 */
?>
<article class="tarjeta">
    <div class="tarjeta__imagen">
        <?php if (!empty($crem['foto_principal'])): ?>
        <img
            src="<?php echo limpiar($crem['foto_principal']); ?>"
            alt="<?php echo limpiar($crem['nombre']); ?>"
            loading="lazy"
            onerror="this.parentElement.innerHTML='<div class=\'tarjeta__imagen--placeholder\'><i data-lucide=\'heart\' class=\'icono\'></i></div><?php if (!empty($crem['destacado'])): ?><span class=\'tarjeta__destacado\'>Destacado</span><?php endif; ?>'; lucide.createIcons();"
        >
        <?php else: ?>
        <div class="tarjeta__imagen--placeholder">
            <i data-lucide="heart" class="icono"></i>
        </div>
        <?php endif; ?>
        <?php if (!empty($crem['destacado'])): ?>
        <span class="tarjeta__destacado">Destacado</span>
        <?php endif; ?>
    </div>

    <div class="tarjeta__contenido">
        <h3 class="tarjeta__titulo">
            <a href="<?php echo generarUrl('crematorio', $crem['slug']); ?>"><?php echo limpiar($crem['nombre']); ?></a>
        </h3>

        <div class="tarjeta__ubicacion">
            <i data-lucide="map-pin" class="icono"></i>
            <span><?php echo limpiar($crem['direccion_completa'] ?? $crem['ciudad'] ?? ''); ?></span>
        </div>

        <?php if (!empty($crem['descripcion'])): ?>
        <p class="tarjeta__descripcion">
            <?php echo limpiar(substr($crem['descripcion'], 0, 150)); ?><?php echo strlen($crem['descripcion']) > 150 ? '...' : ''; ?>
        </p>
        <?php else: ?>
        <p class="tarjeta__descripcion">
            Servicios de cremación profesional y respetuoso para tu mascota.
        </p>
        <?php endif; ?>

        <div class="tarjeta__footer">
            <?php if (($crem['rating'] ?? 0) > 0): ?>
            <div class="tarjeta__valoracion">
                <?php echo generarEstrellas(round($crem['rating']), 16); ?>
                <span><?php echo number_format($crem['rating'], 1); ?></span>
            </div>
            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">(<?php echo $crem['reviews_total'] ?? 0; ?> reseñas)</span>
            <?php else: ?>
            <span style="font-size: var(--fs-uno); color: var(--color-seis-claro);">Sin valoraciones</span>
            <?php endif; ?>
        </div>

        <a href="<?php echo generarUrl('crematorio', $crem['slug']); ?>" class="boton uno" style="width: 100%; margin-top: var(--espacio-tres);">
            Ver detalles
        </a>
    </div>
</article>
