<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ESPAÑA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 05 — refresh Fase 6 (encabezado compacto + partials)
 *
 * Lista las provincias de España + grid de crematorios + nube de ciudades.
 * URL: /espana/
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

$pais_nombre = 'España';

// Provincias + totales
$provincias = obtenerProvincias();
$total_provincias = count($provincias);

// Crematorios paginados
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$resultado = obtenerCrematorios([], $pagina);
$crematorios = $resultado['datos'];
$total_crematorios = $resultado['total'];
$total_paginas = $resultado['paginas'];

// Coordenadas para mapa nacional
$coords_mapa       = obtenerCoordenadasEspana();
$usar_leaflet_mapa = count($coords_mapa) > 0;

$titulo_pagina = 'Crematorios de Mascotas en España';
$pagina_actual = 'directorio';
include 'includes/header.php';
?>

<?php
// ─── Encabezado compacto (breadcrumb + h1 + badge + descripción) ───
$migas = [
    ['Inicio', BASE_URL . '/'],
    ['España', null],
];
$tituloH1    = 'Crematorios de mascotas en España';
$badgeTotal  = $total_crematorios . ' crematorio' . ($total_crematorios !== 1 ? 's' : '') . ' en ' . $total_provincias . ' provincias';
$descripcion = 'Explora el directorio nacional y encuentra el crematorio ideal para despedir a tu mascota con dignidad.';
// Botón "Ver con mapa" — solo si hay coordenadas para mostrar
$mapaRegionUrl = $usar_leaflet_mapa
    ? BASE_URL . '/mapa?volver=' . urlencode($_SERVER['REQUEST_URI'] ?? '/espana')
    : null;
include ROOT_PATH . '/includes/componentes/encabezado-geo.php';
?>

<!-- ─── Mapa nacional con clustering ─── -->
<?php if ($usar_leaflet_mapa): ?>
<section style="padding: var(--espacio-cuatro) 0; background: var(--color-cuatro);">
    <div class="contenedor">
        <div id="mapa-espana" style="width:100%; height:480px; border-radius:var(--radio-dos); overflow:hidden; position:relative;"></div>
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

    var map = L.map('mapa-espana', { scrollWheelZoom: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    window.MapaCrematorios.crearClusterConPuntos(map, puntos, { maxClusterRadius: 60 });
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 9 });

    // Spotlight con frontera real de España (península + Baleares + Canarias).
    // Si el archivo falla por cualquier motivo, fallback al spotlight circular.
    fetch('<?php echo BASE_URL; ?>/assets/geojson/espana.geojson')
        .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
        .then(function(data) {
            var feature = data.features && data.features[0];
            if (!feature) throw new Error('sin feature');
            window.MapaCrematorios.dibujarSpotlightPoligono(map, feature.geometry);
        })
        .catch(function() {
            window.MapaCrematorios.dibujarSpotlight(map, {
                lat: centroLat, lng: centroLng, puntos: puntos,
                margenPct: 15, radioMinimoMetros: 100000
            });
        });
})();
</script>
<?php endif; ?>

<div class="contenedor seccion">

    <!-- ─── Grid de provincias ─── -->
    <section style="margin-bottom: var(--espacio-cinco);">
        <h2 class="estilo-h4" style="margin-bottom: var(--espacio-tres);">Provincias</h2>
        <div class="lista-geo">
            <?php foreach ($provincias as $prov): ?>
            <a href="<?php echo generarUrl('provincia', $prov['slug']); ?>" class="lista-geo__item">
                <div>
                    <h3 class="lista-geo__item-titulo"><?php echo limpiar($prov['nombre']); ?></h3>
                    <span class="lista-geo__item-meta"><?php echo $prov['total_crematorios']; ?> crematorio<?php echo $prov['total_crematorios'] != 1 ? 's' : ''; ?></span>
                </div>
                <i data-lucide="chevron-right" class="icono lista-geo__item-flecha"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ─── Listado de crematorios paginado ─── -->
    <?php if ($total_crematorios > 0): ?>
    <section style="margin-bottom: var(--espacio-cinco);">
        <h2 class="estilo-h4" style="margin-bottom: var(--espacio-tres);">Todos los crematorios de mascotas</h2>
        <div class="grid-tarjetas <?php echo claseGridTarjetas(count($crematorios)); ?>">
            <?php foreach ($crematorios as $crem): ?>
                <?php include ROOT_PATH . '/includes/componentes/tarjeta-crematorio.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($total_paginas > 1): ?>
        <nav class="paginacion" aria-label="Paginación" style="margin-top: var(--espacio-cuatro);">
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?pagina=<?php echo $i; ?>" class="paginacion__enlace <?php echo $i === $pagina ? 'activo' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
    </section>
    <?php endif; ?>

</div>

<!-- ─── Nube de ciudades para internal linking SEO ─── -->
<?php
$nubeScope  = 'todas';
$nubeTitulo = 'Crematorios de mascotas por ciudad';
$nubeLimite = 30;
include ROOT_PATH . '/includes/componentes/nube-ciudades.php';
?>

<?php include 'includes/footer.php'; ?>
