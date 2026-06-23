<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ENCABEZADO GEO — partial reusable
 * ═══════════════════════════════════════════════════════════
 *
 * Encabezado compacto estilo Amazon/PCComponentes para las
 * páginas geográficas (espana, comunidad, provincia, ciudad,
 * cerca, paises). Mismo patrón que directorio.php pero sin
 * el dropdown de orden (las geo no lo tienen por ahora).
 *
 * Uso:
 *   $migas      = [...];               // breadcrumb (ver breadcrumb.php)
 *   $tituloH1   = 'Crematorios en Madrid';
 *   $badgeTotal = '25 crematorios encontrados';  // opcional
 *   $descripcion = 'Encuentra ...';              // opcional
 *   include ROOT_PATH . '/includes/componentes/encabezado-geo.php';
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 */

if (!isset($tituloH1)) return;
?>
<div class="contenedor directorio-encabezado encabezado-geo">
    <?php include __DIR__ . '/breadcrumb.php'; ?>
    <div class="directorio-encabezado__fila">
        <h1 class="directorio-encabezado__titulo"><?php echo limpiar($tituloH1); ?></h1>
        <?php if (!empty($badgeTotal)): ?>
        <span class="directorio-encabezado__badge"><?php echo limpiar($badgeTotal); ?></span>
        <?php endif; ?>

        <?php if (!empty($mapaRegionUrl)): ?>
        <a class="encabezado-geo__btn-mapa" href="<?php echo htmlspecialchars($mapaRegionUrl); ?>">
            <i data-lucide="map" class="icono"></i>
            Ver con mapa
            <i data-lucide="arrow-right" class="icono encabezado-geo__btn-mapa-arrow"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php if (!empty($descripcion)): ?>
    <p class="directorio-encabezado__descripcion"><?php echo limpiar($descripcion); ?></p>
    <?php endif; ?>
</div>
<?php
// Limpiar variables del scope local
unset($tituloH1, $badgeTotal, $descripcion, $migas, $mapaRegionUrl);
?>
