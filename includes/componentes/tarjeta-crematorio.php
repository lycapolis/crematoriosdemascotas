<?php
/**
 * Componente compartido: tarjeta de crematorio.
 *
 * Usado por: directorio.php, cerca.php, comunidad.php, provincia.php,
 * ciudad.php, espana.php (todas las páginas de listado público).
 *
 * Variables esperadas:
 *   $crem (array) — datos del crematorio. Campos soportados:
 *     - slug, nombre, ciudad, provincia_nombre, direccion_completa
 *     - foto_local | foto_principal
 *     - descripcion_corta | descripcion
 *     - rating, reviews_total
 *     - id, destacado (las insignias se leen de la BD con insigniasDeFicha)
 *     - distancia_km (opcional; si está, muestra badge de km — solo cerca.php)
 *
 * Robusto: campos opcionales degradan limpio.
 */

$slug         = $crem['slug'] ?? '';
$urlFicha     = generarUrl('crematorio', $slug);
$foto         = $crem['foto_local'] ?? $crem['foto_principal'] ?? null;
$nombre       = $crem['nombre'] ?? '';
$ciudad       = $crem['ciudad'] ?? '';
$prov         = $crem['provincia_nombre'] ?? '';
$descCorta    = $crem['descripcion_corta'] ?? null;
$descLarga    = $crem['descripcion'] ?? null;
$rating       = (float) ($crem['rating'] ?? 0);
$nReviews     = (int)   ($crem['reviews_total'] ?? 0);
$esDestacado  = !empty($crem['destacado']);
$distanciaKm  = $crem['distancia_km'] ?? null;

// Ubicación: dirección completa si existe; si no, "Ciudad, Provincia"
$ubicacion = $crem['direccion_completa'] ?? '';
if ($ubicacion === '' || $ubicacion === null) {
    $ubicacion = trim($ciudad . ($prov !== '' ? ", $prov" : ''), ', ');
}

// Descripción: usar la corta si está, si no truncar la larga (UTF-8 safe)
if ($descCorta !== null && $descCorta !== '') {
    $descMostrar = $descCorta;
} elseif ($descLarga !== null && $descLarga !== '') {
    $descMostrar = mb_strlen($descLarga) > 150
        ? mb_substr($descLarga, 0, 150) . '…'
        : $descLarga;
} else {
    $descMostrar = 'Servicios de cremación de mascotas profesional y respetuoso.';
}

// Insignias (catálogo en BD). «Destacado» y «Promocionado» van en la cinta sobre la foto;
// el resto, como fila de iconos bajo el título.
require_once ROOT_PATH . '/includes/insignias.php';
// `_insignias` lo usa el laboratorio de insignias (admin) para pintar la tarjeta real con datos simulados
$insTodas   = isset($crem['_insignias']) ? $crem['_insignias']
            : (!empty($crem['id']) ? insigniasDeFicha(obtenerConexion(), (int) $crem['id'], 'tarjeta') : []);
$slugsCinta = ['promocionado', 'destacado'];
$textoCinta = '';
foreach ($insTodas as $in) {
    if ($in['slug'] === 'promocionado') { $textoCinta = $in['nombre']; break; }
    if ($in['slug'] === 'destacado')    { $textoCinta = $in['nombre']; }
}
$insFila = array_values(array_filter($insTodas, fn($i) => !in_array($i['slug'], $slugsCinta, true)));
?>
<article class="tarjeta<?php echo $esDestacado ? ' destacada' : ''; ?>">
    <a href="<?php echo $urlFicha; ?>" class="tarjeta__imagen" aria-label="Ver ficha de <?php echo limpiar($nombre); ?>" style="display:block;text-decoration:none;">
        <?php if (!empty($foto)): ?>
        <img
            src="<?php echo limpiar($foto); ?>"
            alt="<?php echo limpiar($nombre); ?>"
            loading="lazy"
            onerror="this.parentElement.innerHTML='<div class=\'tarjeta__imagen--placeholder\'><i data-lucide=\'heart\' class=\'icono\'></i></div><?php if ($textoCinta !== ''): ?><span class=\'tarjeta__destacado\'><i data-lucide=\'bookmark\' class=\'icono\'></i><?php echo limpiar($textoCinta); ?></span><?php endif; ?>'; lucide.createIcons();"
        >
        <?php else: ?>
        <div class="tarjeta__imagen--placeholder">
            <i data-lucide="heart" class="icono"></i>
        </div>
        <?php endif; ?>
        <?php if ($textoCinta !== ''): ?>
        <span class="tarjeta__destacado">
            <i data-lucide="bookmark" class="icono"></i>
            <?php echo limpiar($textoCinta); ?>
        </span>
        <?php endif; ?>
        <?php if ($distanciaKm !== null): ?>
        <span class="tarjeta__distancia"><?php echo number_format((float)$distanciaKm, 1); ?> km</span>
        <?php endif; ?>
    </a>

    <div class="tarjeta__contenido">
        <h3 class="tarjeta__titulo">
            <a href="<?php echo $urlFicha; ?>"><?php echo limpiar($nombre); ?></a>
        </h3>

        <?php $insigniasFila = $insFila; $insigniasMax = 4; $insigniasTam = ''; include ROOT_PATH . '/includes/componentes/insignias-fila.php'; ?>

        <div class="tarjeta__ubicacion">
            <i data-lucide="map-pin" class="icono"></i>
            <span><?php echo limpiar($ubicacion); ?></span>
        </div>

        <p class="tarjeta__descripcion">
            <?php echo limpiar($descMostrar); ?>
        </p>

        <div class="tarjeta__footer">
            <?php if ($rating > 0): ?>
            <div class="tarjeta__valoracion">
                <?php echo generarEstrellas((int) round($rating), 16); ?>
                <span><?php echo number_format($rating, 1); ?></span>
            </div>
            <span style="font-size:var(--fs-uno); color:var(--color-seis-claro);">(<?php echo $nReviews; ?> reseña<?php echo $nReviews === 1 ? '' : 's'; ?>)</span>
            <?php else: ?>
            <span style="font-size:var(--fs-uno); color:var(--color-seis-claro);">Sin valoraciones</span>
            <?php endif; ?>
        </div>

        <a href="<?php echo $urlFicha; ?>" class="boton uno" style="width:100%; margin-top:var(--espacio-tres);">
            Ver crematorio
        </a>
    </div>
</article>
