<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CERCA DE MÍ — versión V2 (layout estilo Booking)
 * ═══════════════════════════════════════════════════════════
 *
 * Layout 3 columnas (desktop):
 *   - Sidebar IZQ: filtros (radio + servicios + valoración).
 *   - Columna CENTRO: fichas con layout horizontal (foto izq + info der).
 *   - Mapa DER: grande, sticky, ocupa el alto visible.
 *
 * Sync mapa ↔ fichas:
 *   - Hover en ficha → pin del mapa cambia a color destacado.
 *   - Click en pin → scroll a la ficha correspondiente.
 *
 * URL: /cerca-mapa.php?lat={}&lng={}&radio=50
 *
 * Autor: Facundo M. Campos | Lycapolis LLC
 * Versión: 01 — experimento para comparar contra cerca.php (v1)
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

// Filtros adicionales del sidebar
$valoracionMin = (int)($_GET['valoracion_minima'] ?? 0);
$serviciosBoolFiltrables = [
    'verificado', 'abiertos_ahora',
    'cremacion_individual', 'cremacion_colectiva',
    'atencion_24h', 'sala_velatoria',
    'recogida_domicilio', 'entrega_domicilio',
    'urna', 'souvenires', 'carta', 'molde',
];
$serviciosActivos = [];
foreach ($serviciosBoolFiltrables as $sk) {
    if (!empty($_GET[$sk])) $serviciosActivos[$sk] = 1;
}

// Build WHERE para servicios (booleanos)
$whereServicios = '';
foreach ($serviciosActivos as $col => $_) {
    if ($col === 'abiertos_ahora') continue; // se filtra en PHP
    $whereServicios .= " AND c.{$col} = 1";
}
if ($valoracionMin > 0) {
    $whereServicios .= " AND c.rating >= " . (float)$valoracionMin;
}

// Orden (default: cercanos = distancia ASC)
$ordenActual = $_GET['orden'] ?? '';
$orderBy = "distancia_km ASC"; // default
switch ($ordenActual) {
    case 'calificacion': $orderBy = "c.rating DESC, distancia_km ASC"; break;
    case 'mas_resenas':  $orderBy = "c.reviews_total DESC, distancia_km ASC"; break;
    case 'recientes':    $orderBy = "c.created_at DESC, distancia_km ASC"; break;
    case 'nombre':       $orderBy = "c.nombre ASC"; break;
}

// Haversine + filtros
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
          $whereServicios
        HAVING distancia_km <= :radio
        ORDER BY $orderBy
        LIMIT 60";

$stmt = $pdo->prepare($sql);
$stmt->execute([':lat1' => $lat, ':lat2' => $lat, ':lng1' => $lng, ':radio' => $radio]);
$crematorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
enriquecerConFotoLocal($crematorios);

// Filtro abiertos_ahora en PHP (horarios son JSON)
if (!empty($serviciosActivos['abiertos_ahora'])) {
    $crematorios = array_values(array_filter($crematorios, function ($c) {
        return estaAbiertoAhora($c['horarios'] ?? null);
    }));
}

$total = count($crematorios);
$hayFiltros = $valoracionMin > 0 || !empty($serviciosActivos);

$titulo_pagina    = 'Crematorios de Mascotas cerca de mí — vista mapa';
$meta_descripcion = 'Encuentra el crematorio de mascotas más cercano con vista mapa estilo Booking.';
$pagina_actual    = 'directorio';
$usar_leaflet_mapa = true;
include 'includes/header.php';
?>

<style>
    /* ═════════════════════════════════════════════════
       CERCA-MAPA V2 — layout 3 columnas full-screen estilo Booking
       Body bloqueado: solo la columna de cards hace scroll.
       Sidebar y mapa están "fixed" dentro de su zona.
       Footer del sitio queda oculto en esta página.
       ═════════════════════════════════════════════════ */

    /* Body fullscreen: sin scroll global */
    body.cerca-mapa-pagina { overflow: hidden; height: 100vh; }
    body.cerca-mapa-pagina .footer { display: none !important; }

    /* Layout 3 cols (mobile default = stack normal con scroll de página) */
    .cerca-mapa-layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: var(--espacio-tres);
        padding: var(--espacio-tres) var(--espacio-cuatro);
    }

    /* Sidebar */
    .cerca-mapa__sidebar {
        background: var(--color-ocho);
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-dos);
        padding: var(--espacio-tres);
    }
    .cerca-mapa__sidebar h3 {
        font-size: var(--fs-dos);
        color: var(--color-dos);
        margin: var(--espacio-tres) 0 var(--espacio-dos);
    }
    .cerca-mapa__sidebar h3:first-child { margin-top: 0; }
    .cerca-mapa__sidebar .field { margin-bottom: 0; }

    /* Botón "Cambiar a vista lista" — marrón oscuro consistente con el "Ver con mapa" de v1 */
    .cerca-mapa__toggle {
        display: inline-flex;
        align-items: center;
        gap: var(--espacio-dos);
        font-size: var(--fs-dos);
        font-weight: var(--peso-medio);
        color: var(--color-ocho);
        background: var(--color-dos);
        border-radius: var(--radio-uno);
        padding: 0.5rem 1rem;
        margin-bottom: var(--espacio-tres);
        cursor: pointer;
        width: 100%;
        justify-content: center;
        font-family: inherit;
        text-decoration: none;
        transition: background .15s ease;
    }
    .cerca-mapa__toggle:hover { background: var(--color-uno); }
    .cerca-mapa__toggle .icono { width: 16px; height: 16px; }
    .cerca-mapa__toggle-arrow { transition: transform .2s ease; }
    .cerca-mapa__toggle:hover .cerca-mapa__toggle-arrow { transform: translateX(-3px); }

    .cerca-mapa__radio-chips {
        display: flex;
        flex-wrap: wrap;
        gap: var(--espacio-dos);
        justify-content: center;   /* nube de etiquetas centrada */
    }
    .cerca-mapa__radio-chip {
        padding: 0.4rem 0.85rem;
        font-size: var(--fs-uno);
        border-radius: var(--radio-full);
        text-decoration: none;
        border: 1px solid var(--color-cinco);
        background: var(--color-ocho);
        color: var(--color-seis);
        font-weight: var(--peso-medio);
        transition: all .15s ease;
    }
    .cerca-mapa__radio-chip:hover { background: var(--color-uno-claro); border-color: var(--color-uno); }
    .cerca-mapa__radio-chip--activo {
        background: var(--color-uno);
        color: var(--color-ocho);
        border-color: var(--color-uno);
    }

    .cerca-mapa__borrar {
        display: inline-flex;
        align-items: center;
        gap: var(--espacio-uno);
        font-size: var(--fs-uno);
        color: var(--color-uno);
        background: var(--color-ocho);
        border: 1px solid var(--color-cinco);
        border-radius: var(--radio-uno);
        padding: var(--espacio-uno) var(--espacio-dos);
        margin-bottom: var(--espacio-tres);
        cursor: pointer;
        width: 100%;
        justify-content: center;
        font-family: inherit;
    }
    .cerca-mapa__borrar:hover { background: var(--color-uno-claro); border-color: var(--color-uno); }

    /* Card horizontal */
    .cerca-mapa__lista {
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
    }

    .card-horiz {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 0;
        background: var(--admin-superficie);
        border: 1px solid var(--admin-linea);
        border-radius: var(--radio-dos);
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        transition: border-color .15s, box-shadow .15s;
        position: relative;
    }
    .card-horiz:hover,
    .card-horiz.activa {
        border-color: var(--color-uno);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    .card-horiz__foto {
        height: 100%;
        min-height: 160px;
        background: var(--color-cinco);
        overflow: hidden;
        position: relative;
    }
    .card-horiz__foto img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
    }
    .card-horiz__distancia {
        position: absolute;
        bottom: var(--espacio-dos);
        left: var(--espacio-dos);
        background: rgba(0,0,0,0.7);
        color: white;
        font-size: var(--fs-uno);
        padding: 2px var(--espacio-dos);
        border-radius: var(--radio-uno);
    }

    .card-horiz__cuerpo {
        padding: var(--espacio-tres);
        display: flex;
        flex-direction: column;
        gap: var(--espacio-uno);
        min-width: 0;
    }
    .card-horiz__titulo {
        font-size: var(--fs-tres);
        font-weight: var(--peso-negrita);
        color: var(--color-dos);
        margin: 0;
        line-height: 1.25;
    }
    .card-horiz:hover .card-horiz__titulo { color: var(--color-uno); }
    .card-horiz__titulo { transition: color .15s; }

    .card-horiz__ubicacion {
        font-size: var(--fs-uno);
        color: var(--color-seis-claro);
        display: flex;
        align-items: center;
        gap: var(--espacio-uno);
    }
    .card-horiz__ubicacion .icono { width: 14px; height: 14px; }

    .card-horiz__rating {
        display: flex;
        align-items: center;
        gap: var(--espacio-uno);
        font-size: var(--fs-uno);
    }
    .card-horiz__rating-num {
        background: var(--color-dos);
        color: var(--color-ocho);
        padding: 2px var(--espacio-dos);
        border-radius: var(--radio-uno);
        font-weight: var(--peso-negrita);
        font-size: var(--fs-dos);
    }
    .card-horiz__rating-cant {
        color: var(--color-seis-claro);
    }

    .card-horiz__desc {
        font-size: var(--fs-uno);
        color: var(--color-seis);
        line-height: 1.4;
        margin: var(--espacio-uno) 0 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .card-horiz__badges {
        display: flex;
        gap: var(--espacio-uno);
        margin-top: var(--espacio-dos);
        flex-wrap: wrap;
    }
    .card-horiz__badge {
        font-size: 0.7rem;
        padding: 2px var(--espacio-dos);
        border-radius: var(--radio-uno);
        font-weight: var(--peso-medio);
    }
    .card-horiz__badge--verificado {
        background: var(--color-diez);
        color: white;
    }
    .card-horiz__badge--registrado {
        background: var(--color-tres);
        color: white;
    }

    /* Mapa */
    .cerca-mapa__mapa-wrap {
        background: var(--color-cuatro);
        border-radius: var(--radio-dos);
        overflow: hidden;
        min-height: 400px;
    }
    .cerca-mapa__mapa {
        width: 100%;
        height: 100%;
        min-height: 400px;
    }

    /* Sin resultados */
    .cerca-mapa__vacio {
        text-align: center;
        padding: var(--espacio-cinco) var(--espacio-tres);
        background: var(--admin-superficie);
        border: 1px dashed var(--color-cinco);
        border-radius: var(--radio-dos);
    }

    /* Pin/popup styles ahora viven en componentes.css (globales para todos los mapas) */

    /* Placeholder para card sin foto */
    .card-horiz__foto-ph {
        width: 100%; height: 100%;
        min-height: 160px;
        display: flex; align-items: center; justify-content: center;
        background: var(--color-cinco);
    }
    .card-horiz__foto-ph .icono {
        width: 32px; height: 32px;
        color: var(--color-cuatro);
    }

    /* ═════════════════════════════════════════════════
       DESKTOP (≥1024px) — 3 cols full-screen, solo scroll en columna fichas
       ═════════════════════════════════════════════════ */
    @media (min-width: 1024px) {
        .cerca-mapa-layout {
            /* Altura = viewport - header. JS afina el valor exacto. */
            height: calc(100vh - 65px);
            grid-template-columns: 280px minmax(0, 600px) 1fr;
            gap: var(--espacio-tres);
            padding: var(--espacio-tres);
            align-items: stretch;
            max-width: 100%;
            overflow: hidden;
        }

        .cerca-mapa__sidebar {
            height: 100%;
            overflow-y: auto;
            margin: 0;
        }

        .cerca-mapa__fichas {
            height: 100%;
            overflow-y: auto;
            padding-right: var(--espacio-uno);
        }

        .cerca-mapa__mapa-wrap {
            height: 100%;
            min-height: 0;
        }
        .cerca-mapa__mapa {
            height: 100%;
        }
    }
</style>

<script>document.body.classList.add('cerca-mapa-pagina');</script>

<!-- ─── Layout 3 columnas ─── -->
<div class="cerca-mapa-layout">

    <!-- ═══ SIDEBAR FILTROS ═══ -->
    <aside class="cerca-mapa__sidebar">
        <!-- Toggle a la otra versión (lista sin mapa) -->
        <a href="<?php echo BASE_URL; ?>/cerca.php?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=<?php echo $radio; ?>" class="cerca-mapa__toggle">
            <i data-lucide="arrow-left" class="icono cerca-mapa__toggle-arrow"></i>
            Cambiar a vista lista
            <i data-lucide="list" class="icono"></i>
        </a>

        <!-- Resumen total + breadcrumb compacto -->
        <div style="margin-bottom: var(--espacio-tres); padding-bottom: var(--espacio-tres); border-bottom: 1px solid var(--color-cinco);">
            <div style="font-size: var(--fs-uno); color: var(--color-seis-claro);">
                <a href="<?php echo BASE_URL; ?>/" style="color: var(--color-seis-claro); text-decoration: none;">Inicio</a>
                <span style="margin: 0 4px;">›</span>
                <span>Cerca de mí</span>
            </div>
            <div style="margin-top: var(--espacio-uno); font-size: var(--fs-tres); font-weight: var(--peso-negrita); color: var(--color-dos);">
                <?php echo $total; ?> crematorio<?php echo $total !== 1 ? 's' : ''; ?> en <?php echo $radio; ?> km
            </div>
        </div>

        <?php if ($hayFiltros): ?>
        <button type="button" class="cerca-mapa__borrar"
                onclick="window.location.href='<?php echo BASE_URL; ?>/cerca-mapa.php?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=<?php echo $radio; ?>'">
            <i data-lucide="x" class="icono" style="width:14px;height:14px;"></i>
            Borrar filtros
        </button>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/cerca-mapa.php" method="GET" id="form-cerca-mapa">
            <input type="hidden" name="lat" value="<?php echo $lat; ?>">
            <input type="hidden" name="lng" value="<?php echo $lng; ?>">
            <input type="hidden" name="radio" value="<?php echo $radio; ?>">

            <h3>Radio de búsqueda</h3>
            <div class="cerca-mapa__radio-chips">
                <?php
                $paramsExtra = array_diff_key($_GET, ['lat'=>1,'lng'=>1,'radio'=>1]);
                $qsExtra = !empty($paramsExtra) ? '&' . http_build_query($paramsExtra) : '';
                foreach ([25, 50, 100, 200, 300] as $r):
                ?>
                <a href="?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=<?php echo $r; ?><?php echo $qsExtra; ?>"
                   class="cerca-mapa__radio-chip <?php echo $r == $radio ? 'cerca-mapa__radio-chip--activo' : ''; ?>">
                    <?php echo $r; ?> km
                </a>
                <?php endforeach; ?>
            </div>

            <h3>Ordenar por</h3>
            <select name="orden" class="field__select field__select--enhanced" data-ts-search="off" data-ts-autosubmit="1">
                <option value=""             <?php echo $ordenActual === ''            ? 'selected' : ''; ?>>Más cercanos</option>
                <option value="calificacion" <?php echo $ordenActual === 'calificacion'? 'selected' : ''; ?>>Mejor valorados</option>
                <option value="mas_resenas"  <?php echo $ordenActual === 'mas_resenas' ? 'selected' : ''; ?>>Más reseñas</option>
                <option value="recientes"    <?php echo $ordenActual === 'recientes'   ? 'selected' : ''; ?>>Más recientes</option>
                <option value="nombre"       <?php echo $ordenActual === 'nombre'      ? 'selected' : ''; ?>>Nombre A-Z</option>
            </select>

            <h3>Valoración mínima</h3>
            <select name="valoracion_minima" class="field__select field__select--enhanced" data-ts-search="off" data-ts-autosubmit="1">
                <option value="">Cualquiera</option>
                <option value="5" <?php echo $valoracionMin == 5 ? 'selected' : ''; ?>>5 estrellas</option>
                <option value="4" <?php echo $valoracionMin == 4 ? 'selected' : ''; ?>>4+ estrellas</option>
                <option value="3" <?php echo $valoracionMin == 3 ? 'selected' : ''; ?>>3+ estrellas</option>
            </select>

            <h3>Filtros</h3>
            <div style="display:flex; flex-direction:column; gap:var(--espacio-dos);">
                <?php
                $chipsServ = [
                    'abiertos_ahora' => 'Abiertos ahora',
                    'verificado'     => 'Verificado',
                    'cremacion_individual' => 'Cremación individual',
                    'atencion_24h'   => 'Atención 24/7',
                    'sala_velatoria' => 'Sala velatoria',
                    'recogida_domicilio' => 'Recogida a domicilio',
                    'urna'           => 'Urna incluida',
                ];
                foreach ($chipsServ as $name => $label):
                    $checked = !empty($_GET[$name]) ? 'checked' : '';
                ?>
                <label class="field__opcion">
                    <input type="checkbox" class="field__check" name="<?php echo $name; ?>" value="1" <?php echo $checked; ?>
                           onchange="document.getElementById('form-cerca-mapa').submit()">
                    <span><?php echo $label; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </form>
    </aside>

    <!-- ═══ COLUMNA CENTRO: FICHAS ═══ -->
    <main class="cerca-mapa__fichas">
        <div class="cerca-mapa__lista">
            <?php if (empty($crematorios)): ?>
            <div class="cerca-mapa__vacio">
                <i data-lucide="map-pin-off" class="icono" style="width:36px;height:36px;color:var(--color-cinco);margin-bottom:var(--espacio-dos);"></i>
                <h2 class="estilo-h5" style="margin:0 0 var(--espacio-uno);">Sin resultados con estos filtros</h2>
                <p style="color:var(--color-seis-claro);margin:0 0 var(--espacio-tres);font-size:var(--fs-dos);">Probá ampliar el radio o quitar filtros.</p>
                <a href="<?php echo BASE_URL; ?>/cerca-mapa.php?lat=<?php echo $lat; ?>&lng=<?php echo $lng; ?>&radio=200" class="boton uno">Ampliar a 200 km</a>
            </div>
            <?php else: ?>
                <?php foreach ($crematorios as $crem):
                    $urlFicha   = generarUrl('crematorio', $crem['slug']);
                    // Mismo fallback que el partial tarjeta-crematorio.php (foto_local primero)
                    $foto       = $crem['foto_local'] ?? $crem['foto_principal'] ?? null;
                    $distancia  = round((float)$crem['distancia_km'], 1);
                    $ubicacion  = trim(($crem['ciudad'] ?? '') . ', ' . ($crem['provincia_nombre'] ?? ''), ', ');
                    $rating     = $crem['rating'] ? number_format((float)$crem['rating'], 1) : null;
                    $reviews    = (int)($crem['reviews_total'] ?? 0);
                    $esRegistrado = ($crem['origen'] ?? '') === 'registro';
                    $esVerificado = !empty($crem['verificado']);
                ?>
                <a href="<?php echo $urlFicha; ?>" class="card-horiz"
                   data-crem-id="<?php echo (int)$crem['id']; ?>"
                   data-lat="<?php echo (float)$crem['latitud']; ?>"
                   data-lng="<?php echo (float)$crem['longitud']; ?>"
                   aria-label="Ver ficha de <?php echo limpiar($crem['nombre']); ?>">
                    <div class="card-horiz__foto">
                        <?php if (!empty($foto)): ?>
                        <img src="<?php echo limpiar($foto); ?>" alt="<?php echo limpiar($crem['nombre']); ?>" loading="lazy"
                             onerror="this.parentElement.innerHTML='<div class=\'card-horiz__foto-ph\'><i data-lucide=\'heart\' class=\'icono\'></i></div><span class=\'card-horiz__distancia\'><?php echo $distancia; ?> km</span>'; if (window.lucide) lucide.createIcons();">
                        <?php else: ?>
                        <div class="card-horiz__foto-ph"><i data-lucide="heart" class="icono"></i></div>
                        <?php endif; ?>
                        <span class="card-horiz__distancia"><?php echo $distancia; ?> km</span>
                    </div>
                    <div class="card-horiz__cuerpo">
                        <h3 class="card-horiz__titulo"><?php echo limpiar($crem['nombre']); ?></h3>
                        <?php if ($ubicacion): ?>
                        <div class="card-horiz__ubicacion">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span><?php echo limpiar($ubicacion); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($rating): ?>
                        <div class="card-horiz__rating">
                            <span class="card-horiz__rating-num"><?php echo $rating; ?></span>
                            <?php if ($reviews > 0): ?>
                            <span class="card-horiz__rating-cant"><?php echo $reviews; ?> reseña<?php echo $reviews !== 1 ? 's' : ''; ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($crem['descripcion_corta']) || !empty($crem['descripcion'])): ?>
                        <p class="card-horiz__desc">
                            <?php echo limpiar(mb_substr(strip_tags($crem['descripcion_corta'] ?? $crem['descripcion']), 0, 140)); ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($esRegistrado || $esVerificado): ?>
                        <div class="card-horiz__badges">
                            <?php if ($esRegistrado): ?>
                            <span class="card-horiz__badge card-horiz__badge--registrado">Registrado</span>
                            <?php endif; ?>
                            <?php if ($esVerificado): ?>
                            <span class="card-horiz__badge card-horiz__badge--verificado">Verificado</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- ═══ MAPA GRANDE STICKY ═══ -->
    <aside class="cerca-mapa__mapa-wrap">
        <div id="mapa-cerca-grande" class="cerca-mapa__mapa"></div>
    </aside>

</div>

<script>
(function() {
    if (typeof L === 'undefined') return;

    var userLat     = <?php echo $lat; ?>;
    var userLng     = <?php echo $lng; ?>;
    var radioMetros = <?php echo $radio * 1000; ?>;
    var puntos = <?php echo json_encode(array_map(function($c) {
        $foto = $c['foto_local'] ?? $c['foto_principal'] ?? null;
        $ubic = trim(($c['ciudad'] ?? '') . (!empty($c['provincia_nombre']) ? ', ' . $c['provincia_nombre'] : ''), ', ');
        return [
            'id'          => (int) $c['id'],
            'lat'         => (float) $c['latitud'],
            'lng'         => (float) $c['longitud'],
            'nombre'      => $c['nombre'],
            'url'         => generarUrl('crematorio', $c['slug']),
            // foto_local/foto_principal ya viene resuelto desde enriquecerConFotoLocal()
            // — no agregar BASE_URL (rompe URLs absolutas o duplica la base)
            'foto'        => $foto ?: null,
            'ubicacion'   => $ubic,
            'rating'      => $c['rating'] ? number_format((float)$c['rating'], 1) : null,
            'reviews'     => (int)($c['reviews_total'] ?? 0),
            'km'          => round((float)$c['distancia_km'], 1),
            'verificado'  => !empty($c['verificado']),
            'registrado'  => ($c['origen'] ?? '') === 'registro',
            'destacado'   => !empty($c['destacado']),
        ];
    }, $crematorios), JSON_UNESCAPED_UNICODE); ?>;

    var map = L.map('mapa-cerca-grande', { scrollWheelZoom: true }).setView([userLat, userLng], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    // Usuario
    L.marker([userLat, userLng], {
        icon: L.divIcon({
            className: '',
            html: '<div style="background:#3b82f6;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);"></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        })
    }).addTo(map).bindPopup('<strong>Tu ubicación</strong>');

    // Círculo radio
    var circulo = L.circle([userLat, userLng], {
        radius: radioMetros,
        color: '#c0705a',
        fillColor: '#c0705a',
        fillOpacity: 0.05,
        weight: 1.5,
        dashArray: '6,4'
    }).addTo(map);

    // Marcadores por crematorio (sin cluster en v2 para sync directo)
    var marcadoresPorId = {};

    // Iconos/popup compartidos con cerca/comunidad/provincia → mapa-leaflet-pines.js
    var iconoNormal     = window.MapaCrematorios.crearIconoNormal();
    var iconoDestacado  = window.MapaCrematorios.crearIconoDestacado;
    var popupHtml       = window.MapaCrematorios.popupHtml;

    // Variante "activa" — pin agrandado al hacer hover en su card (UX exclusivo de v2)
    var iconoActivo = L.divIcon({
        className: 'map-pin map-pin--activo',
        html: '<div class="map-pin__dot"></div>',
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });

    var popupActual = null;
    var timerCierre = null;

    puntos.forEach(function(p) {
        var icono = p.destacado ? iconoDestacado(p) : iconoNormal;
        var marker = L.marker([p.lat, p.lng], {
            icon: icono,
            // Destacados SIEMPRE arriba de los pines normales (modelo comercial)
            zIndexOffset: p.destacado ? 1000 : 0,
            riseOnHover: true   // al hover también queda arriba (UX)
        }).addTo(map);
        marcadoresPorId[p.id] = marker;

        marker.bindPopup(popupHtml(p), {
            closeButton: false,
            autoClose: true,
            closeOnEscapeKey: true,
            maxWidth: 340,
            minWidth: 300,
            offset: [0, -4],
            className: 'map-popup-wrap'
        });

        // Hover en pin → abre popup
        marker.on('mouseover', function() {
            clearTimeout(timerCierre);
            this.openPopup();
            popupActual = this;
            // Highlight card correspondiente
            var card = document.querySelector('.card-horiz[data-crem-id="' + p.id + '"]');
            if (card) card.classList.add('activa');
        });

        marker.on('mouseout', function() {
            var that = this;
            // Delay para permitir mover el mouse al popup
            timerCierre = setTimeout(function() {
                if (popupActual === that) that.closePopup();
                var card = document.querySelector('.card-horiz[data-crem-id="' + p.id + '"]');
                if (card) card.classList.remove('activa');
            }, 200);
        });

        // Click en pin → scroll a la ficha (no navega; el link del popup sí)
        marker.on('click', function() {
            var card = document.querySelector('.card-horiz[data-crem-id="' + p.id + '"]');
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                document.querySelectorAll('.card-horiz').forEach(function(c) { c.classList.remove('activa'); });
                card.classList.add('activa');
                setTimeout(function() { card.classList.remove('activa'); }, 1500);
            }
        });
    });

    // Mantener popup abierto si el mouse pasa al popup mismo
    map.on('popupopen', function(e) {
        var el = e.popup.getElement();
        if (!el) return;
        el.addEventListener('mouseenter', function() { clearTimeout(timerCierre); });
        el.addEventListener('mouseleave', function() {
            timerCierre = setTimeout(function() { e.popup._source.closePopup(); }, 200);
        });
    });

    // Ajustar vista al círculo del radio
    map.fitBounds(circulo.getBounds(), { padding: [20, 20] });

    // Spotlight: ilumina el área dentro del radio del filtro, oscurece el resto.
    if (window.MapaCrematorios && window.MapaCrematorios.dibujarSpotlight) {
        window.MapaCrematorios.dibujarSpotlight(map, {
            lat: userLat, lng: userLng,
            radioMetros: radioMetros
        });
    }

    // Hover en ficha → highlight pin
    document.querySelectorAll('.card-horiz').forEach(function(card) {
        var id = parseInt(card.dataset.cremId, 10);
        card.addEventListener('mouseenter', function() {
            var m = marcadoresPorId[id];
            if (m) m.setIcon(iconoActivo);
        });
        card.addEventListener('mouseleave', function() {
            var m = marcadoresPorId[id];
            if (m) m.setIcon(iconoNormal);
        });
    });

    // Recalcular tamaño del mapa después de que se asiente (sticky a veces lo desconfigura)
    setTimeout(function() { map.invalidateSize(); }, 100);
})();

// Sync altura del layout con header del sitio (full-screen sin scroll global)
(function() {
    function ajustar() {
        var h = document.querySelector('.header');
        if (!h) return;
        var H = h.offsetHeight;
        var layout = document.querySelector('.cerca-mapa-layout');
        if (layout) layout.style.height = 'calc(100vh - ' + H + 'px)';
    }
    ajustar();
    window.addEventListener('resize', ajustar);
})();
</script>

<?php include 'includes/footer.php'; ?>
