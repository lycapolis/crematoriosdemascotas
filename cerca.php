<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CERCA DE MÍ - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Versión: 03 — radios en encabezado 2-col + sticky desktop + zoom dinámico
 *
 * URL: /cerca.php?lat={}&lng={}&radio=50
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

$lat   = isset($_GET['lat']) ? (float) $_GET['lat'] : null;
$lng   = isset($_GET['lng']) ? (float) $_GET['lng'] : null;
$radio = isset($_GET['radio']) ? max(5, min(300, (int) $_GET['radio'])) : 50;

if (!$lat || !$lng) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Haversine en MySQL: distancia en km
$pdo = obtenerConexion();
$sql = "SELECT c.*,
               p.nombre AS provincia_nombre,
               p.slug   AS provincia_slug,
               ca.nombre AS comunidad_nombre,
               ca.slug   AS comunidad_slug,
               (6371 * acos(
                   cos(radians(:lat1)) * cos(radians(c.latitud))
                   * cos(radians(c.longitud) - radians(:lng1))
                   + sin(radians(:lat2)) * sin(radians(c.latitud))
               )) AS distancia_km
        FROM crematorios c
        LEFT JOIN provincias p  ON c.provincia_id = p.id
        LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
        WHERE c.latitud IS NOT NULL AND c.latitud != 0
          AND c.longitud IS NOT NULL AND c.longitud != 0
        HAVING distancia_km <= :radio
        ORDER BY distancia_km
        LIMIT 30";

$stmt = $pdo->prepare($sql);
$stmt->execute([':lat1' => $lat, ':lat2' => $lat, ':lng1' => $lng, ':radio' => $radio]);
$crematorios_cerca = $stmt->fetchAll(PDO::FETCH_ASSOC);
enriquecerConFotoLocal($crematorios_cerca);
$total = count($crematorios_cerca);

$titulo_pagina    = 'Crematorios de Mascotas cerca de mí';
$meta_descripcion = 'Encuentra el crematorio de mascotas más cercano a tu ubicación actual.';
$pagina_actual    = 'directorio';
$usar_leaflet_mapa = true;
include 'includes/header.php';
?>

<style>
    /* Encabezado cerca: 2 columnas (izq título + der radios), sticky en desktop */
    .cerca-encabezado-layout {
        background: var(--color-cuatro);
        padding: var(--espacio-tres) var(--espacio-cuatro);
    }

    .cerca-radios {
        display: flex;
        align-items: center;
        gap: var(--espacio-dos);
        flex-wrap: wrap;
    }

    .cerca-radios__label {
        font-size: var(--fs-uno);
        color: var(--color-seis-claro);
        white-space: nowrap;
    }

    .cerca-radios__chips {
        display: flex;
        gap: var(--espacio-uno);
        flex-wrap: wrap;
    }

    .cerca-radios__chip {
        padding: 0.45rem 0.95rem;     /* 20% más grandes que la versión anterior */
        font-size: var(--fs-dos);      /* 14px en vez de 12px */
        border-radius: var(--radio-full);
        text-decoration: none;
        font-weight: var(--peso-medio);
        transition: all .15s ease;
    }
    .cerca-radios__chip--inactivo {
        background: var(--color-ocho);
        color: var(--color-seis);
        border: 1px solid var(--color-cinco);
    }
    .cerca-radios__chip--inactivo:hover {
        background: var(--color-uno-claro);
        border-color: var(--color-uno);
    }
    .cerca-radios__chip--activo {
        background: var(--color-uno);
        color: var(--color-ocho);
        border: 1px solid var(--color-uno);
    }

    /* "Ver con mapa" — botón normal marrón oscuro (consistente con la paleta) */
    .cerca-radios__ver-mapa {
        margin-left: var(--espacio-tres);
        display: inline-flex;
        align-items: center;
        gap: var(--espacio-dos);
        background: var(--color-dos);
        color: var(--color-ocho);
        padding: 0.5rem 1rem;
        border-radius: var(--radio-uno);
        font-size: var(--fs-dos);
        font-weight: var(--peso-medio);
        text-decoration: none;
        transition: background .15s ease;
    }
    .cerca-radios__ver-mapa:hover {
        background: var(--color-uno);
    }
    .cerca-radios__ver-mapa .icono { width: 16px; height: 16px; }
    .cerca-radios__ver-mapa-arrow { transition: transform .2s ease; }
    .cerca-radios__ver-mapa:hover .cerca-radios__ver-mapa-arrow {
        transform: translateX(3px);
    }

    /* Popup más chico en cerca.php (mapa apaisado, 500px alto) */
    .map-popup-wrap .leaflet-popup-content { width: 220px !important; }
    .map-popup-wrap .map-popup__foto { height: 120px; }
    .map-popup-wrap .map-popup__cuerpo { padding: var(--espacio-dos) var(--espacio-tres); }

    /* ─── Mobile (≤768px): UX adaptada ─── */
    @media (max-width: 768px) {
        /* Botón "Ver en el mapa" oculto en mobile: el mapa abajo ya está visible */
        .cerca-radios__ver-mapa { display: none !important; }

        /* Label "Radio:" oculto en mobile — se sobreentiende viendo las pastillas */
        .cerca-radios__label { display: none; }

        /* H2 "Crematorios de mascotas en N km a la redonda" oculto en mobile:
           redundante con el badge "X encontrados en N km" y el h1 que están
           justo arriba. Ahorra espacio vertical. */
        .cerca-titulo-listado { display: none; }

        /* Chips de radios centrados — antes flotaban a la izquierda */
        .cerca-radios { justify-content: center; }
        .cerca-radios__chips { justify-content: center; flex: 1 1 100%; }

        /* Mapa cuadrado 1:1 a ancho completo (sin esquinas redondeadas que
           se ven mal cuando el mapa ocupa todo el viewport). Override del
           border-radius y altura inline del HTML. */
        #mapa-cerca {
            height: 100vw !important;
            max-height: 500px;
            border-radius: 0 !important;
        }
        /* El contenedor que envuelve el mapa también pierde su padding lateral */
        section:has(#mapa-cerca) .contenedor { padding-left: 0; padding-right: 0; }
    }

    @media (min-width: 768px) {
        .cerca-encabezado-layout {
            padding: var(--espacio-tres) 0;
        }
    }

    @media (min-width: 1024px) {
        .cerca-encabezado-layout {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: var(--espacio-cuatro);
            align-items: center;
            /* Sticky debajo del header del sitio. La propiedad `top` la setea
               JS al cargar (header.offsetHeight) para evitar el desfase de 1-2px.
               z-index 1050: por encima de los controles de Leaflet (zoom +/-)
               que llegan a 800, debajo del header del sitio (1100). */
            position: sticky;
            top: 65px; /* fallback; JS lo recalcula con la altura exacta */
            z-index: 1050;
        }
        .cerca-radios {
            justify-self: end;
        }
    }
</style>

<div class="contenedor cerca-encabezado-layout">
    <div class="directorio-encabezado">
        <?php
        $migas = [
            ['Inicio',     BASE_URL . '/'],
            ['Cerca de mí', null],
        ];
        include ROOT_PATH . '/includes/componentes/breadcrumb.php';
        ?>
        <div class="directorio-encabezado__fila">
            <h1 class="directorio-encabezado__titulo">Crematorios de Mascotas cerca de ti</h1>
            <span class="directorio-encabezado__badge">
                <?php echo $total > 0
                    ? $total . ' encontrado' . ($total !== 1 ? 's' : '') . ' en ' . $radio . ' km'
                    : 'Sin resultados en ' . $radio . ' km'; ?>
            </span>
        </div>
        <p class="directorio-encabezado__descripcion">Ordenados por distancia desde tu ubicación. Ajusta el radio para ampliar o reducir.</p>
    </div>

    <div class="cerca-radios">
        <span class="cerca-radios__label">Radio:</span>
        <div class="cerca-radios__chips">
            <?php foreach ([25, 50, 100, 200, 300] as $r): ?>
            <a href="cerca.php?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=<?php echo $r; ?>"
               class="cerca-radios__chip <?php echo $r == $radio ? 'cerca-radios__chip--activo' : 'cerca-radios__chip--inactivo'; ?>">
                <?php echo $r; ?> km
            </a>
            <?php endforeach; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/cerca-mapa.php?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=<?php echo $radio; ?>"
           class="cerca-radios__ver-mapa">
            <i data-lucide="map" class="icono"></i>
            Ver en el mapa
            <i data-lucide="arrow-right" class="icono cerca-radios__ver-mapa-arrow"></i>
        </a>
    </div>
</div>

<!-- ─── Mapa ─── -->
<section style="background: var(--color-cuatro);">
    <div class="contenedor">
        <div id="mapa-cerca" style="width:100%; height:500px; border-radius:var(--radio-dos); overflow:hidden; position:relative;"></div>
    </div>
</section>
<script>
(function() {
    if (typeof L === 'undefined') return;
    var userLat = <?php echo $lat; ?>;
    var userLng = <?php echo $lng; ?>;
    var radioMetros = <?php echo $radio * 1000; ?>;
    var puntos  = <?php echo json_encode(array_map(function($c) {
        $foto = $c['foto_local'] ?? $c['foto_principal'] ?? null;
        $ubic = trim(($c['ciudad'] ?? '') . (!empty($c['provincia_nombre']) ? ', ' . $c['provincia_nombre'] : ''), ', ');
        return [
            'id'         => (int) $c['id'],
            'lat'        => (float) $c['latitud'],
            'lng'        => (float) $c['longitud'],
            'nombre'     => $c['nombre'],
            'url'        => generarUrl('crematorio', $c['slug']),
            'foto'       => $foto ?: null,
            'ubicacion'  => $ubic,
            'rating'     => $c['rating'] ? number_format((float)$c['rating'], 1) : null,
            'reviews'    => (int)($c['reviews_total'] ?? 0),
            'km'         => round((float)$c['distancia_km'], 1),
            'verificado' => !empty($c['verificado']),
            'registrado' => ($c['origen'] ?? '') === 'registro',
            'destacado'  => !empty($c['destacado']),
        ];
    }, $crematorios_cerca), JSON_UNESCAPED_UNICODE); ?>;

    // setView inicial para que el mapa empiece a pedir tiles enseguida.
    // El fitBounds al final ajusta zoom/centro al radio elegido.
    var map = L.map('mapa-cerca', { scrollWheelZoom: true }).setView([userLat, userLng], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    var iconoUsuario = L.divIcon({
        className: '',
        html: '<div style="background:#3b82f6;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);"></div>',
        iconSize: [22, 22],
        iconAnchor: [11, 11]
    });
    L.marker([userLat, userLng], { icon: iconoUsuario })
        .addTo(map)
        .bindPopup('<strong>Tu ubicación</strong>');

    // Círculo del radio — usado para fitBounds() así el mapa siempre muestra
    // todo el radio elegido (al cambiar 50→200 km, el zoom se aleja solo).
    var circuloRadio = L.circle([userLat, userLng], {
        radius: radioMetros,
        color: '#c0705a',
        fillColor: '#c0705a',
        fillOpacity: 0.05,
        weight: 1.5,
        dashArray: '6,4'
    }).addTo(map);

    // Iconos + popups + cluster — single source of truth en mapa-leaflet-pines.js
    if (window.MapaCrematorios) {
        window.MapaCrematorios.crearClusterConPuntos(map, puntos, { maxClusterRadius: 40 });

        // Spotlight: ilumina el área dentro del radio del filtro, oscurece el resto.
        window.MapaCrematorios.dibujarSpotlight(map, {
            lat: userLat, lng: userLng,
            radioMetros: radioMetros
        });
    }

    // Ajustar zoom y centro al radio elegido (incluye el círculo entero).
    map.fitBounds(circuloRadio.getBounds(), { padding: [20, 20] });
})();

// Sync sticky top con altura exacta del header del sitio (evita desfase 1-2px)
(function() {
    function ajustar() {
        var h = document.querySelector('.header');
        var s = document.querySelector('.cerca-encabezado-layout');
        if (h && s) s.style.top = h.offsetHeight + 'px';
    }
    ajustar();
    window.addEventListener('resize', ajustar);
})();
</script>

<!-- ─── Listado ─── -->
<div class="contenedor seccion">
    <?php if ($total > 0): ?>
    <h2 class="estilo-h4 cerca-titulo-listado" style="margin-bottom: var(--espacio-tres);">
        Crematorios de mascotas en <?php echo $radio; ?> km a la redonda
    </h2>
    <div class="grid-tarjetas <?php echo claseGridTarjetas($total); ?>">
        <?php foreach ($crematorios_cerca as $crem): ?>
            <?php include __DIR__ . '/includes/componentes/tarjeta-crematorio.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <?php
    // Próximo radio secuencial (25→50→100→200→ir a directorio)
    $radiosDisponibles = [25, 50, 100, 200, 300];
    $idxActual         = array_search($radio, $radiosDisponibles);
    $siguienteRadio    = ($idxActual !== false && $idxActual < count($radiosDisponibles) - 1)
        ? $radiosDisponibles[$idxActual + 1]
        : null;
    ?>
    <div style="text-align:center; padding: var(--espacio-dos) 0 var(--espacio-cuatro);">
        <i data-lucide="map-pin-off" class="icono" style="width:36px; height:36px; color:var(--color-cinco); margin-bottom:var(--espacio-dos);"></i>
        <?php if ($siguienteRadio !== null): ?>
            <h2 class="estilo-h5" style="margin: 0 0 var(--espacio-uno);">Sin resultados en <?php echo $radio; ?> km a la redonda</h2>
            <p style="color:var(--color-seis-claro); margin: 0 0 var(--espacio-tres); font-size: var(--fs-dos);">Probá ampliando el radio de búsqueda.</p>
            <a href="cerca.php?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=<?php echo $siguienteRadio; ?>" class="boton uno">Ampliar a <?php echo $siguienteRadio; ?> km</a>
        <?php else: ?>
            <h2 class="estilo-h5" style="margin: 0 0 var(--espacio-uno);">Sin resultados en 300 km a la redonda</h2>
            <p style="color:var(--color-seis-claro); margin: 0 0 var(--espacio-tres); font-size: var(--fs-dos);">Probá una búsqueda más detallada en el directorio completo.</p>
            <a href="<?php echo BASE_URL; ?>/directorio.php" class="boton uno">
                <i data-lucide="search" class="icono"></i>
                Ir al directorio
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
