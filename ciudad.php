<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CIUDAD - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 05 — refresh Fase 6 (encabezado compacto + partials)
 *
 * URL: /espana/{provincia}/{ciudad}/
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

$provincia_slug = trim($_GET['provincia'] ?? '');
$ciudad_slug    = trim($_GET['ciudad'] ?? '');

$provincia = obtenerProvinciaSlug($provincia_slug);
if (!$provincia) {
    http_response_code(404);
    $titulo_pagina = 'Ciudad no encontrada';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ciudad no encontrada</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">La ciudad que buscas no existe o no está disponible.</p>
            <a href="<?php echo BASE_URL; ?>/espana/" class="boton uno">Ver todas las provincias</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

$crematorios = obtenerCrematoriosCiudad($ciudad_slug, $provincia_slug);

if (empty($crematorios)) {
    http_response_code(404);
    $titulo_pagina = 'Ciudad no encontrada';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ciudad no encontrada</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">No hay crematorios registrados en esta ciudad.</p>
            <a href="<?php echo generarUrl('provincia', $provincia_slug); ?>" class="boton uno">Ver ciudades en <?php echo limpiar($provincia['nombre']); ?></a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

$ciudad_nombre    = $crematorios[0]['ciudad'];
$provincia_nombre = $crematorios[0]['provincia_nombre'];
$comunidad_nombre = $crematorios[0]['comunidad_nombre'] ?? '';
$comunidad_slug   = $crematorios[0]['comunidad_slug'] ?? '';
$total_crematorios = count($crematorios);

// Coordenadas para el mapa (debe estar ANTES del include header para que se cargue Leaflet)
$coords_mapa       = obtenerCoordenadasCiudad($ciudad_slug, $provincia_slug);
$usar_leaflet_mapa = count($coords_mapa) > 0;

$titulo_pagina = 'Crematorios de Mascotas en ' . $ciudad_nombre . ', ' . $provincia_nombre;
$pagina_actual = 'directorio';
include 'includes/header.php';
?>

<?php
// ─── Encabezado compacto ───
$migas = [['Inicio', BASE_URL . '/'], ['España', BASE_URL . '/espana/']];
if ($comunidad_nombre && $comunidad_nombre !== $provincia_nombre) {
    $migas[] = [$comunidad_nombre, generarUrl('comunidad', $comunidad_slug)];
}
$migas[] = [$provincia_nombre, generarUrl('provincia', $provincia_slug)];
$migas[] = [$ciudad_nombre, null];

$tituloH1   = 'Crematorios de mascotas en ' . $ciudad_nombre;
$badgeTotal = $total_crematorios . ' crematorio' . ($total_crematorios !== 1 ? 's' : '') . ' encontrado' . ($total_crematorios !== 1 ? 's' : '');
$descripcion = 'Provincia de ' . $provincia_nombre . '. Compara servicios y contacta directamente con el crematorio que necesites.';
$mapaRegionUrl = $usar_leaflet_mapa
    ? BASE_URL . '/mapa/' . urlencode($provincia_slug) . '/' . urlencode($ciudad_slug) . '?volver=' . urlencode($_SERVER['REQUEST_URI'] ?? '')
    : null;
include ROOT_PATH . '/includes/componentes/encabezado-geo.php';
?>

<!-- ─── Mapa con clustering (si hay coordenadas) ─── -->
<?php if ($usar_leaflet_mapa): ?>
<section style="padding: var(--espacio-cuatro) 0; background: var(--color-cuatro);">
    <div class="contenedor">
        <div id="mapa-ciudad" style="width:100%; height:420px; border-radius:var(--radio-dos); overflow:hidden; position:relative;"></div>
    </div>
</section>
<script>
(function() {
    var puntos = <?php echo json_encode(array_map(function($c) {
        $ubic = trim(($c['ciudad'] ?? '') . (!empty($c['provincia_nombre']) ? ', ' . $c['provincia_nombre'] : ''), ', ');
        return [
            'lat'        => (float) $c['latitud'],
            'lng'        => (float) $c['longitud'],
            'nombre'     => $c['nombre'],
            'url'        => BASE_URL . '/' . $c['slug'],
            'foto'       => ($c['foto_local'] ?? $c['foto_principal']) ?: null,
            'ubicacion'  => $ubic ?: null,
            'rating'     => $c['rating'] ? number_format((float)$c['rating'], 1) : null,
            'reviews'    => (int)($c['reviews_total'] ?? 0),
            'verificado' => !empty($c['verificado']),
            'destacado'  => !empty($c['destacado']),
            'registrado' => ($c['origen'] ?? '') === 'registro',
        ];
    }, $coords_mapa), JSON_UNESCAPED_UNICODE); ?>;

    if (!puntos.length || typeof L === 'undefined' || !window.MapaCrematorios) return;

    var lats = puntos.map(function(p){ return p.lat; });
    var lngs = puntos.map(function(p){ return p.lng; });
    var bounds = L.latLngBounds(
        [Math.min.apply(null, lats), Math.min.apply(null, lngs)],
        [Math.max.apply(null, lats), Math.max.apply(null, lngs)]
    );
    var centroLat = (Math.min.apply(null, lats) + Math.max.apply(null, lats)) / 2;
    var centroLng = (Math.min.apply(null, lngs) + Math.max.apply(null, lngs)) / 2;

    var map = L.map('mapa-ciudad', { scrollWheelZoom: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    window.MapaCrematorios.crearClusterConPuntos(map, puntos, { maxClusterRadius: 50 });
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 });

    // Spotlight: enfoca la ciudad oscureciendo el resto.
    window.MapaCrematorios.dibujarSpotlight(map, {
        lat: centroLat, lng: centroLng, puntos: puntos,
        radioMinimoMetros: 3000
    });
})();
</script>
<?php endif; ?>

<div class="contenedor seccion">

    <!-- ─── Listado de crematorios ─── -->
    <section style="margin-bottom: var(--espacio-cinco);">
        <div class="grid-tarjetas <?php echo claseGridTarjetas(count($crematorios)); ?>">
            <?php foreach ($crematorios as $crem): ?>
                <?php include ROOT_PATH . '/includes/componentes/tarjeta-crematorio.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>

</div>

<!-- ─── Nube de ciudades cercanas (misma comunidad, otras provincias) ─── -->
<?php
$nubeScope      = 'cercanas';
$nubeContextoId = $provincia['id'];
$nubeTitulo     = 'Otras ciudades cercanas';
$nubeLimite     = 24;
include ROOT_PATH . '/includes/componentes/nube-ciudades.php';
?>

<?php include 'includes/footer.php'; ?>
