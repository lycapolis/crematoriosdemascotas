<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PROVINCIA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 05 — refresh Fase 6 (encabezado compacto + partials)
 *
 * URL: /espana/{slug}/
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

$provincia_slug = trim($_GET['slug'] ?? '');
$provincia = obtenerProvinciaSlug($provincia_slug);

if (!$provincia) {
    http_response_code(404);
    $titulo_pagina = 'Provincia no encontrada';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Provincia no encontrada</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">La provincia que buscas no existe o no está disponible.</p>
            <a href="<?php echo BASE_URL; ?>/espana/" class="boton uno">Ver todas las provincias</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

$provincia_nombre = $provincia['nombre'];
$comunidad_nombre = $provincia['comunidad_nombre'] ?? '';
$comunidad_slug   = $provincia['comunidad_slug'] ?? '';

$ciudades       = obtenerCiudadesProvincia($provincia['id']);
$total_ciudades = count($ciudades);

$pagina            = max(1, (int)($_GET['pagina'] ?? 1));
$resultado         = obtenerCrematorios(['provincia_id' => $provincia['id']], $pagina);
$crematorios       = $resultado['datos'];
$total_crematorios = $resultado['total'];
$total_paginas     = $resultado['paginas'];

$coords_mapa       = obtenerCoordenadasProvincia($provincia['id']);
$usar_leaflet_mapa = count($coords_mapa) > 0;

$titulo_pagina = 'Crematorios de Mascotas en ' . $provincia_nombre;
$pagina_actual = 'directorio';
include 'includes/header.php';
?>

<?php
// ─── Encabezado compacto (breadcrumb incluye comunidad si difiere del nombre) ───
$migas = [['Inicio', BASE_URL . '/'], ['España', BASE_URL . '/espana/']];
if ($comunidad_nombre && $comunidad_nombre !== $provincia_nombre) {
    $migas[] = [$comunidad_nombre, generarUrl('comunidad', $comunidad_slug)];
}
$migas[] = [$provincia_nombre, null];

$tituloH1   = 'Crematorios de mascotas en ' . $provincia_nombre;
$badgeTotal = $total_crematorios . ' crematorio' . ($total_crematorios !== 1 ? 's' : '')
            . ' en ' . $total_ciudades . ' ciudad' . ($total_ciudades !== 1 ? 'es' : '');
$descripcion = 'Encuentra los mejores crematorios de mascotas en la provincia de ' . $provincia_nombre . '. Compara servicios, reseñas y contacta directamente.';
$mapaRegionUrl = $usar_leaflet_mapa
    ? BASE_URL . '/mapa/' . urlencode($provincia_slug ?? $slug) . '?volver=' . urlencode($_SERVER['REQUEST_URI'] ?? '')
    : null;
include ROOT_PATH . '/includes/componentes/encabezado-geo.php';
?>

<!-- ─── Mapa con clustering ─── -->
<?php if ($usar_leaflet_mapa): ?>
<section style="padding: var(--espacio-cuatro) 0; background: var(--color-cuatro);">
    <div class="contenedor">
        <div id="mapa-provincia" style="width:100%; height:420px; border-radius:var(--radio-dos); overflow:hidden; position:relative;"></div>
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

    var map = L.map('mapa-provincia', { scrollWheelZoom: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    window.MapaCrematorios.crearClusterConPuntos(map, puntos, { maxClusterRadius: 50 });
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 });

    // Spotlight con frontera real de la provincia.
    // Si el archivo falla o no encuentra la provincia → fallback al círculo.
    var slugProvincia = <?php echo json_encode($provincia_slug); ?>;
    fetch('<?php echo BASE_URL; ?>/assets/geojson/provincias.geojson')
        .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function(data) {
            var feature = (data.features || []).find(function(f) {
                return f.properties && f.properties.slug === slugProvincia;
            });
            if (!feature) throw new Error('provincia sin polígono');
            window.MapaCrematorios.dibujarSpotlightPoligono(map, feature.geometry);
        })
        .catch(function() {
            window.MapaCrematorios.dibujarSpotlight(map, {
                lat: centroLat, lng: centroLng, puntos: puntos,
                radioMinimoMetros: 10000
            });
        });
})();
</script>
<?php endif; ?>

<div class="contenedor seccion">

    <!-- ─── Grid de ciudades ─── -->
    <?php if ($total_ciudades > 0): ?>
    <section style="margin-bottom: var(--espacio-cinco);">
        <h2 class="estilo-h4" style="margin-bottom: var(--espacio-tres);">Ciudades en <?php echo limpiar($provincia_nombre); ?></h2>
        <div class="lista-geo">
            <?php foreach ($ciudades as $ciudad): ?>
            <a href="<?php echo generarUrl('ciudad', $ciudad['slug'], $provincia_slug); ?>" class="lista-geo__item">
                <div>
                    <h3 class="lista-geo__item-titulo"><?php echo limpiar($ciudad['nombre']); ?></h3>
                    <span class="lista-geo__item-meta"><?php echo $ciudad['total_crematorios']; ?> crematorio<?php echo $ciudad['total_crematorios'] != 1 ? 's' : ''; ?></span>
                </div>
                <i data-lucide="chevron-right" class="icono lista-geo__item-flecha"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ─── Listado de crematorios ─── -->
    <?php if ($total_crematorios > 0): ?>
    <section style="margin-bottom: var(--espacio-cinco);">
        <h2 class="estilo-h4" style="margin-bottom: var(--espacio-tres);">Crematorios en <?php echo limpiar($provincia_nombre); ?></h2>
        <div class="grid-tarjetas <?php echo claseGridTarjetas(count($crematorios)); ?>">
            <?php foreach ($crematorios as $crem): ?>
                <?php include ROOT_PATH . '/includes/componentes/tarjeta-crematorio.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($total_paginas > 1): ?>
        <nav class="paginacion" aria-label="Paginación" style="margin-top: var(--espacio-cuatro);">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?slug=<?php echo urlencode($provincia_slug); ?>&pagina=<?php echo $i; ?>" class="paginacion__enlace <?php echo $i === $pagina ? 'activo' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </section>
    <?php endif; ?>

</div>

<!-- ─── Nube de ciudades de esta provincia ─── -->
<?php
$nubeScope      = 'provincia';
$nubeContextoId = $provincia['id'];
$nubeTitulo     = 'Crematorios por ciudad en ' . $provincia_nombre;
$nubeLimite     = 30;
include ROOT_PATH . '/includes/componentes/nube-ciudades.php';
?>

<?php include 'includes/footer.php'; ?>
