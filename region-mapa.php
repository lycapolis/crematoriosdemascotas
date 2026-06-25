<?php
/**
 * ═══════════════════════════════════════════════════════════
 * REGION-MAPA — vista mapa de los crematorios de una región
 * ═══════════════════════════════════════════════════════════
 *
 * Layout 3 columnas (estilo cerca-mapa.php):
 *   IZQ:    sidebar de filtros (valoración + servicios + ordenar)
 *   CENTRO: listado de fichas (cards scrolleables)
 *   DERECHA: mapa grande sticky
 *
 * NO usa GPS — la vista "cerca de mí" con geolocalización es otra
 * intención y vive en `cerca.php` / `cerca-mapa.php`.
 *
 * Parámetros (GET):
 *   - nivel:     espana | comunidad | provincia | ciudad
 *   - slug:      slug de la región (no aplica para nivel=espana)
 *   - provincia: solo para nivel=ciudad (slug de la provincia)
 *   - volver:    URL a la que vuelve el chip "← Volver"
 *   - orden:     '' | calificacion | mas_resenas | recientes | nombre
 *   - valoracion_minima: 3 | 4 | 5
 *   - servicios bool: verificado, abiertos_ahora, cremacion_individual, etc.
 *
 * URLs limpias (.htaccess):
 *   /mapa                       → nivel=espana
 *   /mapa/comunidad/{slug}      → nivel=comunidad
 *   /mapa/{provincia}           → nivel=provincia
 *   /mapa/{provincia}/{ciudad}  → nivel=ciudad
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// ─── 1. Parámetros base ──────────────────────────────────────────────────────
$nivel         = trim($_GET['nivel']     ?? 'espana');
$slug          = trim($_GET['slug']      ?? '');
$provinciaArg  = trim($_GET['provincia'] ?? '');
$volverUrl     = trim($_GET['volver']    ?? '');

$nivelesValidos = ['espana', 'comunidad', 'provincia', 'ciudad'];
if (!in_array($nivel, $nivelesValidos, true)) $nivel = 'espana';

// ─── 2. Validar la región + obtener centro ───────────────────────────────────
$centro = centroRegion($nivel, $slug ?: null, $provinciaArg ?: null);
if (!$centro) {
    http_response_code(404);
    $titulo_pagina = 'Región sin crematorios disponibles';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align:center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="map-pin-off" style="width:64px; height:64px; color:var(--color-cinco); margin-bottom:var(--espacio-cuatro);"></i>
            <h1 style="color:var(--color-dos); margin-bottom:var(--espacio-tres);">No hay crematorios para mostrar en mapa</h1>
            <p style="color:var(--color-seis-claro); margin-bottom:var(--espacio-cinco);">Todavía no hay fichas activas con ubicación en esta zona.</p>
            <a href="<?php echo BASE_URL; ?>/espana/" class="boton uno">Ver todas las provincias</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// ─── 3. Filtros del sidebar ──────────────────────────────────────────────────
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

$whereServicios = '';
foreach ($serviciosActivos as $col => $_) {
    if ($col === 'abiertos_ahora') continue; // se filtra en PHP (horarios JSON)
    $whereServicios .= " AND c.{$col} = 1";
}
if ($valoracionMin > 0) {
    $whereServicios .= " AND c.rating >= " . (float)$valoracionMin;
}

// ─── 4. Orden ─────────────────────────────────────────────────────────────────
$ordenActual = $_GET['orden'] ?? '';
$orderBy = "c.destacado DESC, c.rating DESC, c.nombre ASC";  // default: destacados primero
switch ($ordenActual) {
    case 'calificacion': $orderBy = "c.rating DESC, c.reviews_total DESC"; break;
    case 'mas_resenas':  $orderBy = "c.reviews_total DESC, c.rating DESC"; break;
    case 'recientes':    $orderBy = "c.created_at DESC"; break;
    case 'nombre':       $orderBy = "c.nombre ASC"; break;
}

// ─── 5. WHERE base según nivel ───────────────────────────────────────────────
$pdo = obtenerConexion();
$whereNivel = '';
$paramsNivel = [];
$filtrarCiudadEnPhp = false;

switch ($nivel) {
    case 'espana': /* sin filtro extra */ break;

    case 'comunidad':
        $stmt = $pdo->prepare("SELECT id FROM comunidades_autonomas WHERE slug = :s LIMIT 1");
        $stmt->execute([':s' => $slug]);
        $cid = (int)$stmt->fetchColumn();
        if (!$cid) { $whereNivel = " AND 0 "; break; }
        $whereNivel = " AND p.comunidad_id = :cid ";
        $paramsNivel[':cid'] = $cid;
        break;

    case 'provincia':
        $stmt = $pdo->prepare("SELECT id FROM provincias WHERE slug = :s LIMIT 1");
        $stmt->execute([':s' => $slug]);
        $pid = (int)$stmt->fetchColumn();
        if (!$pid) { $whereNivel = " AND 0 "; break; }
        $whereNivel = " AND c.provincia_id = :pid ";
        $paramsNivel[':pid'] = $pid;
        break;

    case 'ciudad':
        $stmt = $pdo->prepare("SELECT id FROM provincias WHERE slug = :s LIMIT 1");
        $stmt->execute([':s' => $provinciaArg]);
        $pid = (int)$stmt->fetchColumn();
        if (!$pid) { $whereNivel = " AND 0 "; break; }
        $whereNivel = " AND c.provincia_id = :pid ";
        $paramsNivel[':pid'] = $pid;
        $filtrarCiudadEnPhp = true; // slug ciudad → en PHP con slugificar()
        break;
}

// ─── 6. SELECT ────────────────────────────────────────────────────────────────
$sql = "SELECT c.*,
               p.nombre AS provincia_nombre,
               p.slug   AS provincia_slug,
               ca.nombre AS comunidad_nombre,
               ca.slug   AS comunidad_slug
        FROM crematorios c
        LEFT JOIN provincias p  ON c.provincia_id = p.id
        LEFT JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
        WHERE c.estado = 'activa'
          AND c.latitud IS NOT NULL AND c.latitud != 0
          AND c.longitud IS NOT NULL AND c.longitud != 0
          $whereNivel
          $whereServicios
        ORDER BY $orderBy
        LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($paramsNivel);
$crematorios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtro ciudad en PHP (slug normalizado con acentos)
if ($filtrarCiudadEnPhp) {
    $slugCiu = slugificar($slug);
    $crematorios = array_values(array_filter($crematorios, function ($c) use ($slugCiu) {
        return slugificar((string)($c['ciudad'] ?? '')) === $slugCiu;
    }));
}

// Filtro abiertos_ahora en PHP (horarios son JSON)
if (!empty($serviciosActivos['abiertos_ahora'])) {
    $crematorios = array_values(array_filter($crematorios, function ($c) {
        return estaAbiertoAhora($c['horarios'] ?? null);
    }));
}

enriquecerConFotoLocal($crematorios);

$total = count($crematorios);
$hayFiltros = $valoracionMin > 0 || !empty($serviciosActivos) || ($ordenActual && $ordenActual !== '');

// ─── 7. Volver URL segura ────────────────────────────────────────────────────
$volverSeguro = '';
if ($volverUrl !== '' && (str_starts_with($volverUrl, '/') || str_starts_with($volverUrl, BASE_URL))) {
    $volverSeguro = $volverUrl;
}

// ─── 8. Título y meta ────────────────────────────────────────────────────────
$tituloRegion      = $centro['nombre'];
$titulo_pagina     = 'Mapa de crematorios en ' . $tituloRegion;
$meta_descripcion  = 'Vista mapa de crematorios de mascotas en ' . $tituloRegion . '.';
$pagina_actual     = 'directorio';
$usar_leaflet_mapa = true;

// URL base para limpiar filtros y para action del form
$qsBase = http_build_query(array_filter([
    'nivel'     => $nivel,
    'slug'      => $slug,
    'provincia' => $provinciaArg,
    'volver'    => $volverSeguro,
]));
$urlSinFiltros = BASE_URL . '/region-mapa.php?' . $qsBase;

include 'includes/header.php';
?>
<body class="region-mapa-pagina">
<style>
/* ═════════════════════════════════════════════════
   REGION-MAPA — layout 3 cols full-screen
   Body bloqueado: scroll solo en la columna de cards.
   Sidebar + mapa sticky en su zona.
   ═════════════════════════════════════════════════ */
body.region-mapa-pagina { overflow: hidden; height: 100vh; }
body.region-mapa-pagina .footer { display: none !important; }

.region-mapa-layout {
    display: grid;
    grid-template-columns: 260px 1fr;          /* mobile/tablet: stack del mapa abajo */
    gap: 0;
    height: calc(100vh - var(--altura-header, 64px));
}
@media (min-width: 1100px) {
    .region-mapa-layout {
        grid-template-columns: 240px 380px 1fr;
    }
}

.region-mapa__sidebar {
    background: var(--color-cuatro);
    border-right: 1px solid var(--color-cinco);
    padding: var(--espacio-tres) var(--espacio-tres) var(--espacio-cuatro);
    overflow-y: auto;
}
.region-mapa__sidebar h3 {
    font-size: var(--fs-uno);
    color: var(--color-dos);
    margin: var(--espacio-tres) 0 var(--espacio-dos);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: var(--peso-negrita);
}
.region-mapa__sidebar h3:first-child { margin-top: 0; }
.region-mapa__sidebar .field { margin-bottom: 0; }

/* Botón "Volver al listado" — mismo estilo que .cerca-mapa__toggle (marrón oscuro). */
.region-mapa__toggle {
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
    text-decoration: none;
    transition: background .15s ease;
}
.region-mapa__toggle:hover { background: var(--color-uno); }
.region-mapa__toggle .icono { width: 16px; height: 16px; }
.region-mapa__toggle-arrow { transition: transform .2s ease; }
.region-mapa__toggle:hover .region-mapa__toggle-arrow { transform: translateX(-3px); }

.region-mapa__resumen {
    margin-bottom: var(--espacio-tres);
    padding-bottom: var(--espacio-tres);
    border-bottom: 1px solid var(--color-cinco);
}
.region-mapa__resumen-mini { font-size: var(--fs-uno); color: var(--color-seis-claro); }
.region-mapa__resumen-total {
    margin-top: var(--espacio-uno);
    font-size: var(--fs-tres);
    font-weight: var(--peso-negrita);
    color: var(--color-dos);
}

.region-mapa__borrar {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .4rem .7rem;
    font-size: .8rem;
    background: rgba(196, 105, 91, .12);
    border: 1px solid rgba(196, 105, 91, .35);
    color: var(--color-siete);
    border-radius: var(--radio-uno);
    cursor: pointer;
    margin-bottom: var(--espacio-tres);
}
.region-mapa__borrar:hover { background: rgba(196, 105, 91, .2); }

/* Lista de filtros de servicios — usa `.field__opcion` + `.field__check` global,
   mismo patrón que cerca-mapa.php. Sin contenedor blanco por ítem. */
.region-mapa__chips-servicios { display: flex; flex-direction: column; gap: var(--espacio-dos); }

/* Columna central: cards scrolleables */
.region-mapa__cards {
    overflow-y: auto;
    padding: var(--espacio-tres);
    background: var(--color-ocho);
    border-right: 1px solid var(--color-cinco);
}
@media (max-width: 1099px) {
    /* En mobile el mapa va abajo, ocultamos por simplicidad — mejorable en futuro */
    .region-mapa__mapa-wrap { display: none; }
}

/* Columna derecha: mapa sticky */
.region-mapa__mapa-wrap { position: relative; }
.region-mapa__mapa { width: 100%; height: 100%; min-height: 500px; }

/* Header chip "Volver" + título — fuera del layout 3 cols (top bar) */
.region-mapa__topbar {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--espacio-dos) var(--espacio-cuatro);
    background: #fff;
    border-bottom: 1px solid var(--color-cinco);
    flex-wrap: wrap; gap: var(--espacio-tres);
}
.region-mapa__topbar h1 {
    margin: 0;
    font-family: var(--fuente-titulo);
    font-size: var(--fs-cuatro);
    color: var(--color-dos);
    letter-spacing: -.01em;
}
.region-mapa__topbar h1 small {
    font-family: inherit;
    font-weight: var(--peso-medio);
    font-size: var(--fs-dos);
    color: var(--color-seis);
    margin-left: .5rem;
}
.region-mapa-volver {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .4rem .9rem; border-radius: 999px;
    background: var(--color-cinco); border: 1px solid var(--color-cinco);
    color: var(--color-dos); text-decoration: none;
    font-size: .8rem; font-weight: var(--peso-medio);
    transition: all .15s ease;
}
.region-mapa-volver:hover { background: var(--color-uno); border-color: var(--color-uno); color: #fff; }

/* Card horizontal */
.rmcard {
    display: flex; gap: .8rem;
    padding: .8rem;
    background: #fff;
    border: 1px solid var(--color-cinco);
    border-radius: var(--radio-dos);
    margin-bottom: .7rem;
    text-decoration: none; color: inherit;
    transition: all .15s ease;
}
.rmcard:hover,
.rmcard.activa { border-color: var(--color-uno); transform: translateY(-1px); box-shadow: 0 3px 12px rgba(184, 112, 79, .12); }
/* Estado .activa: aplicado por JS cuando el pin del mapa correspondiente
   también está activo (hover sync). Misma visual que hover. */
.rmcard__foto {
    width: 80px; height: 80px; flex-shrink: 0;
    background: var(--color-cinco) center/cover no-repeat;
    border-radius: var(--radio-uno);
    display: flex; align-items: center; justify-content: center;
    color: var(--color-seis); font-size: 1.5rem;
}
.rmcard__cuerpo { flex: 1; min-width: 0; }
.rmcard__nombre {
    font-weight: var(--peso-negrita); color: var(--color-dos);
    font-size: .95rem; line-height: 1.25;
    margin-bottom: .25rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}
.rmcard__ciudad { font-size: .8rem; color: var(--color-seis); margin-bottom: .35rem; }
.rmcard__meta { display: flex; gap: .55rem; font-size: .78rem; color: var(--color-seis); align-items: center; flex-wrap: wrap; }
.rmcard__meta strong { color: var(--color-dos); }
.rmcard__badge {
    display: inline-block; font-size: .65rem; padding: .15rem .45rem;
    border-radius: 999px; font-weight: var(--peso-negrita); letter-spacing: .02em;
}
.rmcard__badge--destacado { background: var(--color-uno); color: #fff; }
.rmcard__badge--verificado { background: rgba(184, 112, 79, .12); color: var(--color-uno); }

/* Sidebar oculto en mobile (estética básica); en futuro: drawer */
@media (max-width: 1099px) {
    .region-mapa__sidebar { display: none; }
    .region-mapa-layout { grid-template-columns: 1fr; }
}
</style>

<div class="region-mapa__topbar">
    <h1>
        Crematorios en <?php echo limpiar($tituloRegion); ?>
        <small>· <?php echo (int)$total; ?> ficha<?php echo $total === 1 ? '' : 's'; ?></small>
    </h1>
    <?php if ($volverSeguro): ?>
    <a class="region-mapa-volver" href="<?php echo htmlspecialchars($volverSeguro); ?>">
        <i data-lucide="arrow-left" class="icono" style="width:14px; height:14px;"></i>
        Volver a <?php echo htmlspecialchars($tituloRegion); ?>
    </a>
    <?php endif; ?>
</div>

<div class="region-mapa-layout">
    <!-- ── Sidebar de filtros ── -->
    <aside class="region-mapa__sidebar">
        <a href="<?php echo $volverSeguro ?: BASE_URL . '/espana/'; ?>" class="region-mapa__toggle">
            <i data-lucide="arrow-left" class="icono region-mapa__toggle-arrow" style="width:14px;height:14px;"></i>
            Volver al listado
            <i data-lucide="list" class="icono" style="width:14px;height:14px; margin-left:auto;"></i>
        </a>

        <div class="region-mapa__resumen">
            <div class="region-mapa__resumen-mini">Filtrando en</div>
            <div class="region-mapa__resumen-total"><?php echo limpiar($tituloRegion); ?></div>
        </div>

        <?php if ($hayFiltros): ?>
        <button type="button" class="region-mapa__borrar" onclick="window.location.href='<?php echo htmlspecialchars($urlSinFiltros); ?>'">
            <i data-lucide="x" class="icono" style="width:14px;height:14px;"></i>
            Borrar filtros
        </button>
        <?php endif; ?>

        <form action="<?php echo BASE_URL; ?>/region-mapa.php" method="GET" id="form-region-mapa">
            <input type="hidden" name="nivel"     value="<?php echo htmlspecialchars($nivel); ?>">
            <input type="hidden" name="slug"      value="<?php echo htmlspecialchars($slug); ?>">
            <input type="hidden" name="provincia" value="<?php echo htmlspecialchars($provinciaArg); ?>">
            <input type="hidden" name="volver"    value="<?php echo htmlspecialchars($volverSeguro); ?>">

            <h3>Ordenar por</h3>
            <select name="orden" class="field__select field__select--enhanced" data-ts-search="off" data-ts-autosubmit="1">
                <option value=""             <?php echo $ordenActual === ''            ? 'selected' : ''; ?>>Recomendados</option>
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
            <div class="region-mapa__chips-servicios">
                <?php
                $chipsServ = [
                    'abiertos_ahora'       => 'Abiertos ahora',
                    'verificado'           => 'Verificado',
                    'cremacion_individual' => 'Cremación individual',
                    'atencion_24h'         => 'Atención 24/7',
                    'sala_velatoria'       => 'Sala velatoria',
                    'recogida_domicilio'   => 'Recogida a domicilio',
                    'urna'                 => 'Urna incluida',
                ];
                foreach ($chipsServ as $name => $label):
                    $checked = !empty($serviciosActivos[$name]) ? 'checked' : '';
                ?>
                <label class="field__opcion">
                    <input type="checkbox" class="field__check" name="<?php echo $name; ?>" value="1" <?php echo $checked; ?>
                           onchange="document.getElementById('form-region-mapa').submit()">
                    <span><?php echo $label; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </form>
    </aside>

    <!-- ── Listado de cards ── -->
    <main class="region-mapa__cards">
        <?php if ($total === 0): ?>
            <div style="text-align:center; padding:var(--espacio-cinco) 0;">
                <i data-lucide="search-x" class="icono" style="width:48px;height:48px;color:var(--color-cinco);margin-bottom:var(--espacio-tres);"></i>
                <h2 style="font-size:var(--fs-tres);color:var(--color-dos);margin:0 0 var(--espacio-uno);">Sin resultados con estos filtros</h2>
                <p style="color:var(--color-seis-claro);font-size:var(--fs-dos);margin:0 0 var(--espacio-tres);">Probá quitar algún filtro o cambiar la valoración mínima.</p>
                <a href="<?php echo htmlspecialchars($urlSinFiltros); ?>" class="boton uno pequeno">Ver todos los crematorios</a>
            </div>
        <?php else: ?>
            <?php foreach ($crematorios as $c):
                $foto = $c['foto_local'] ?? $c['foto_principal'] ?? '';
                $url  = BASE_URL . '/' . $c['slug'];
                $ubic = trim(($c['ciudad'] ?? '') . (!empty($c['provincia_nombre']) ? ', ' . $c['provincia_nombre'] : ''), ', ');
                $rating = $c['rating'] ? number_format((float)$c['rating'], 1) : null;
            ?>
            <a class="rmcard" href="<?php echo htmlspecialchars($url); ?>" data-crem-id="<?php echo (int)$c['id']; ?>">
                <?php if ($foto): ?>
                <div class="rmcard__foto" style="background-image:url('<?php echo htmlspecialchars($foto); ?>');"></div>
                <?php else: ?>
                <div class="rmcard__foto">🐾</div>
                <?php endif; ?>
                <div class="rmcard__cuerpo">
                    <div class="rmcard__nombre"><?php echo limpiar($c['nombre']); ?></div>
                    <?php if ($ubic): ?><div class="rmcard__ciudad"><?php echo limpiar($ubic); ?></div><?php endif; ?>
                    <div class="rmcard__meta">
                        <?php if ($rating): ?><span><strong>★ <?php echo $rating; ?></strong></span><?php endif; ?>
                        <?php if (!empty($c['reviews_total'])): ?><span><?php echo (int)$c['reviews_total']; ?> reseñas</span><?php endif; ?>
                        <?php if (!empty($c['destacado'])): ?><span class="rmcard__badge rmcard__badge--destacado">Destacado</span><?php endif; ?>
                        <?php if (!empty($c['verificado'])): ?><span class="rmcard__badge rmcard__badge--verificado">Verificado</span><?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- ── Mapa sticky ── -->
    <div class="region-mapa__mapa-wrap">
        <div id="region-mapa" class="region-mapa__mapa"></div>
    </div>
</div>

<script>
(function() {
    if (typeof L === 'undefined' || !window.MapaCrematorios) return;

    var puntos = <?php echo json_encode(array_map(function($c) {
        $ubic = trim(($c['ciudad'] ?? '') . (!empty($c['provincia_nombre']) ? ', ' . $c['provincia_nombre'] : ''), ', ');
        $foto = $c['foto_local'] ?? $c['foto_principal'] ?? null;
        return [
            'id'         => (int)$c['id'],
            'lat'        => (float)$c['latitud'],
            'lng'        => (float)$c['longitud'],
            'nombre'     => $c['nombre'],
            'url'        => BASE_URL . '/' . $c['slug'],
            'foto'       => $foto ?: null,
            'ubicacion'  => $ubic ?: null,
            'rating'     => $c['rating'] ? number_format((float)$c['rating'], 1) : null,
            'reviews'    => (int)($c['reviews_total'] ?? 0),
            'verificado' => !empty($c['verificado']),
            'destacado'  => !empty($c['destacado']),
            'registrado' => ($c['origen'] ?? '') === 'registro',
        ];
    }, $crematorios), JSON_UNESCAPED_UNICODE); ?>;

    var centroLat = <?php echo (float)$centro['lat']; ?>;
    var centroLng = <?php echo (float)$centro['lng']; ?>;

    var map = L.map('region-mapa', { scrollWheelZoom: true }).setView([centroLat, centroLng], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(map);

    if (puntos.length) {
        var clusterRes = window.MapaCrematorios.crearClusterConPuntos(map, puntos, { maxClusterRadius: 50 });
        var marcadoresPorId = clusterRes.marcadoresPorId;

        // ── Sync hover entre cards (.rmcard) y pines del mapa ──
        // Al hacer hover en una card, agrandamos el pin correspondiente. UX:
        // el usuario ve al toque dónde está ubicado el negocio en el mapa.
        var iconoNormal     = window.MapaCrematorios.crearIconoNormal();
        var iconoDestacadoF = window.MapaCrematorios.crearIconoDestacado;
        var iconoActivo = L.divIcon({
            className: 'map-pin map-pin--activo',
            html: '<div class="map-pin__dot"></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 30]
        });
        // Cachear el icono original (normal o destacado) por id para restaurarlo
        var iconoOriginalPorId = {};
        puntos.forEach(function(p) {
            iconoOriginalPorId[p.id] = p.destacado ? iconoDestacadoF(p) : iconoNormal;
        });
        document.querySelectorAll('.rmcard').forEach(function(card) {
            var id = parseInt(card.dataset.cremId, 10);
            if (!id || !marcadoresPorId[id]) return;
            card.addEventListener('mouseenter', function() {
                marcadoresPorId[id].setIcon(iconoActivo);
                card.classList.add('activa');
            });
            card.addEventListener('mouseleave', function() {
                marcadoresPorId[id].setIcon(iconoOriginalPorId[id]);
                card.classList.remove('activa');
            });
        });

        var lats = puntos.map(function(p){ return p.lat; });
        var lngs = puntos.map(function(p){ return p.lng; });
        var bounds = L.latLngBounds(
            [Math.min.apply(null, lats), Math.min.apply(null, lngs)],
            [Math.max.apply(null, lats), Math.max.apply(null, lngs)]
        );
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });

        // Spotlight: oscurece alrededores, ilumina la región.
        // - Nivel "espana": frontera real (península + Baleares + Canarias).
        // - Otros niveles: círculo dinámico con radio mínimo por nivel.
        var nivelActual = <?php echo json_encode($nivel); ?>;
        var radioMinPorNivel = <?php
            $radiosPorNivel = [
                'espana'    => 100000,
                'comunidad' =>  30000,
                'provincia' =>  10000,
                'ciudad'    =>   3000,
            ];
            echo (int)($radiosPorNivel[$nivel] ?? 10000);
        ?>;

        function spotlightCirculo() {
            window.MapaCrematorios.dibujarSpotlight(map, {
                lat: centroLat, lng: centroLng, puntos: puntos,
                radioMinimoMetros: radioMinPorNivel
            });
        }

        var slugRegion = <?php echo json_encode($slug ?? ''); ?>;

        function cargarPoligono(url, buscadorFeature) {
            fetch(url)
                .then(function(r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function(data) {
                    var feature = buscadorFeature(data);
                    if (!feature) throw new Error('sin feature');
                    window.MapaCrematorios.dibujarSpotlightPoligono(map, feature.geometry);
                })
                .catch(spotlightCirculo);
        }

        if (nivelActual === 'espana') {
            cargarPoligono(
                '<?php echo BASE_URL; ?>/assets/geojson/espana.geojson',
                function(data) { return data.features && data.features[0]; }
            );
        } else if (nivelActual === 'comunidad') {
            cargarPoligono(
                '<?php echo BASE_URL; ?>/assets/geojson/comunidades.geojson',
                function(data) {
                    return (data.features || []).find(function(f) {
                        return f.properties && f.properties.slug === slugRegion;
                    });
                }
            );
        } else if (nivelActual === 'provincia') {
            cargarPoligono(
                '<?php echo BASE_URL; ?>/assets/geojson/provincias.geojson',
                function(data) {
                    return (data.features || []).find(function(f) {
                        return f.properties && f.properties.slug === slugRegion;
                    });
                }
            );
        } else {
            // ciudad → siempre círculo (datasets municipales imprecisos/incompletos)
            spotlightCirculo();
        }
    }

    setTimeout(function() { map.invalidateSize(); }, 100);
})();
</script>

<?php include 'includes/footer.php'; ?>
