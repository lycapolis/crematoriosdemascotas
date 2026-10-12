<?php
/**
 * Panel Admin — Laboratorio de insignias (maqueta de pruebas, SIN tocar la BD de fichas).
 *
 * Genera fichas de ejemplo en memoria y las pinta con los componentes REALES (tarjeta, fila de
 * insignias) y el motor REAL (insigniasCalcularFicha), para ver cómo un cambio de reglas, puntos,
 * iconos o colores afecta a distintos tipos de negocio. Nada se escribe: no altera contadores,
 * percentiles, sitemap, buscador ni ranking real.
 *
 *   - Escenarios fijos: casos límite (vacía, perfecta, 4 justas, 5 con «+N», nombre larguísimo…).
 *   - Escenarios barajados: "Frankenstein" con datos reales mezclados (?semilla=N los reproduce).
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/insignias.php';

requerirAutenticacion();
requierePermiso('insignias');

$pdo      = obtenerConexion();
$catalogo = insigniasCatalogo($pdo, true);

// ─── Semilla: "Barajar" = nueva semilla; la misma semilla reproduce los mismos escenarios ───────
$semilla = isset($_GET['semilla']) ? (int) $_GET['semilla'] : random_int(1000, 999999);
mt_srand($semilla);
$modoTodas = isset($_GET['todas']); // incluir también las insignias no visibles en tarjeta

// ─── Datos reales para mezclar ──────────────────────────────────────────────────────────────────
$reales = $pdo->query("SELECT id, nombre, ciudad, direccion_completa, provincia_id, foto_principal, descripcion,
                              rating, reviews_total, atencion_24h, recogida_domicilio, entrega_domicilio,
                              google_verificado, origen, telefono, website
                         FROM crematorios WHERE estado = 'activa' ORDER BY RAND(" . $semilla . ") LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
$provincias = $pdo->query("SELECT id, nombre FROM provincias")->fetchAll(PDO::FETCH_KEY_PAIR);
$conFoto = array_values(array_filter($reales, fn($r) => !empty($r['foto_principal'])));
$fotoBase = $conFoto[0]['foto_principal'] ?? null;
$pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];

/** Ficha base vacía sobre la que se aplican los overrides de cada escenario */
function labBase(array $o): array
{
    return array_merge([
        'id' => 0, 'slug' => 'laboratorio', 'estado' => 'activa',
        'nombre' => 'Crematorio de ejemplo', 'ciudad' => 'Madrid', 'provincia_id' => 18, 'provincia_nombre' => 'Madrid',
        'foto_principal' => null, 'descripcion_corta' => 'Servicio de cremación individual y colectiva de mascotas, con recogida y entrega.',
        'rating' => 0, 'reviews_total' => 0, 'portal_n' => 0, 'portal_suma' => 0,
        'atencion_24h' => 0, 'recogida_domicilio' => 0, 'entrega_domicilio' => 0, 'google_verificado' => null, 'origen' => 'scraping',
        'pct' => 40, 'manuales' => [], 'popular' => 0, 'resena_reciente' => false,
    ], $o);
}

// ─── Escenarios fijos (casos límite) ───────────────────────────────────────────────────────────
$escenarios = [];
$fijos = [
    ['Ficha vacía (sin nada)',            ['pct' => 10, 'nombre' => 'Crematorio Vacío']],
    ['Perfecta: todas las insignias',     ['nombre' => 'Crematorio Perfecto', 'rating' => 4.9, 'reviews_total' => 180, 'pct' => 100, 'atencion_24h' => 1, 'recogida_domicilio' => 1,
                                           'google_verificado' => 1, 'origen' => 'registro', 'manuales' => ['verificado_humano' => 1, 'rapida_atencion' => 1], 'popular' => 3, 'resena_reciente' => true]],
    ['Justo 4 insignias en tarjeta',      ['nombre' => 'Cuatro Justas', 'rating' => 4.3, 'reviews_total' => 40, 'pct' => 95, 'atencion_24h' => 1, 'recogida_domicilio' => 1]],
    ['5 insignias → debe verse «+N»',     ['nombre' => 'Cinco Insignias', 'rating' => 4.4, 'reviews_total' => 60, 'pct' => 95, 'atencion_24h' => 1, 'recogida_domicilio' => 1, 'manuales' => ['verificado_humano' => 1]]],
    ['Recién reseñada (Popular ahora)',   ['nombre' => 'Opinión Reciente', 'rating' => 4.6, 'reviews_total' => 12, 'pct' => 70, 'resena_reciente' => true, 'recogida_domicilio' => 1]],
    ['Verificada pero sin reseñas',       ['nombre' => 'Verificada Sin Reseñas', 'pct' => 90, 'manuales' => ['verificado_humano' => 1, 'rapida_atencion' => 1]]],
    ['Nombre larguísimo',                 ['nombre' => 'Crematorio e Incineración de Animales de Compañía “Los Amigos de Cuatro Patas” — Tanatorio, Cementerio y Servicios Integrales de Despedida', 'rating' => 4.7, 'reviews_total' => 33, 'pct' => 92, 'atencion_24h' => 1, 'manuales' => ['verificado_humano' => 1]]],
    ['Descripción larguísima, sin foto',  ['nombre' => 'Descripción Larga', 'foto_principal' => null, 'rating' => 4.1, 'reviews_total' => 8, 'pct' => 60, 'recogida_domicilio' => 1,
                                           'descripcion_corta' => str_repeat('Ofrecemos cremación individual y colectiva con entrega de cenizas en urna personalizada, recogida a domicilio las 24 horas y acompañamiento. ', 4)]],
];
foreach ($fijos as [$titulo, $o]) {
    $o += ['foto_principal' => $fotoBase];
    $escenarios[] = ['titulo' => $titulo, 'tipo' => 'fijo', 'cr' => labBase($o)];
}

// ─── Escenarios barajados: «Frankenstein» con piezas de negocios reales distintos ──────────────
for ($n = 0; $n < 6 && count($reales) >= 3; $n++) {
    $a = $pick($reales); $b = $pick($reales); $c = $pick($reales);
    $manuales = [];
    if (mt_rand(0, 2) === 0) $manuales['verificado_humano'] = 1;
    if (mt_rand(0, 3) === 0) $manuales['rapida_atencion'] = 1;
    $escenarios[] = ['titulo' => 'Barajada #' . ($n + 1), 'tipo' => 'barajada', 'cr' => labBase([
        'nombre' => $a['nombre'], 'ciudad' => $a['ciudad'], 'provincia_id' => (int) $a['provincia_id'],
        'provincia_nombre' => $provincias[$a['provincia_id']] ?? '', 'foto_principal' => $a['foto_principal'] ?: $fotoBase,
        'descripcion_corta' => mb_substr((string) $b['descripcion'], 0, 160) ?: 'Servicio de cremación de mascotas.',
        'rating' => (float) $b['rating'], 'reviews_total' => (int) $b['reviews_total'],
        'atencion_24h' => (int) $c['atencion_24h'], 'recogida_domicilio' => (int) $c['recogida_domicilio'], 'entrega_domicilio' => (int) $c['entrega_domicilio'],
        'google_verificado' => $c['google_verificado'], 'origen' => mt_rand(0, 4) === 0 ? 'registro' : 'scraping',
        'pct' => mt_rand(35, 100), 'manuales' => $manuales, 'popular' => mt_rand(0, 3) === 0 ? mt_rand(1, 3) : 0, 'resena_reciente' => mt_rand(0, 5) === 0,
    ])];
}

// ─── Evaluar con el motor real ─────────────────────────────────────────────────────────────────
foreach ($escenarios as $k => &$e) {
    $cr = $e['cr'];
    $cr['_sim'] = ['popular' => $cr['popular'], 'resena_reciente' => $cr['resena_reciente']];
    $rating = insigniaRatingCombinado($cr, ['n' => $cr['portal_n'], 'suma' => $cr['portal_suma']]);
    $calc = insigniasCalcularFicha($catalogo, $cr, 0, (int) $cr['pct'], $rating, $cr['manuales']);
    $e['calc']      = $calc;
    $e['rating_c']  = $rating;
    $e['tarjeta']   = insigniasPintables($catalogo, $calc['tiene'], 'tarjeta');
    $e['ficha']     = insigniasPintables($catalogo, $calc['tiene'], 'ficha');
    $e['puntaje']   = $calc['puntos'];
    $e['crem']      = array_merge($cr, ['_insignias' => $e['tarjeta'], 'destacado' => isset($calc['tiene']['destacado']) ? 1 : 0, 'verificado' => 0]);
}
unset($e);

// Posición en el ranking simulado
$orden = array_keys($escenarios);
usort($orden, fn($x, $y) => $escenarios[$y]['puntaje'] <=> $escenarios[$x]['puntaje']);
foreach ($orden as $pos => $k) $escenarios[$k]['pos'] = $pos + 1;

$nombresInsignia = array_column($catalogo, 'nombre', 'slug');
$titulo_pagina = 'Laboratorio de insignias — Admin';
include 'header.php';
?>
<div class="admin-page">

    <p style="margin-bottom:var(--espacio-dos);"><a href="insignias.php" class="admin-text-muted">← Insignias</a></p>

    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap:var(--espacio-tres); flex-wrap:wrap;">
            <h1 class="admin-page-title">Laboratorio de insignias</h1>
            <a href="insignias-laboratorio.php" class="boton uno pequeno">
                <i data-lucide="shuffle" class="icono" style="width:14px;height:14px;"></i> Barajar
            </a>
            <span class="admin-pill">semilla <?php echo $semilla; ?></span>
            <div class="js-lab-anchos" style="display:inline-flex; gap:.3rem;">
                <button type="button" class="boton dos pequeno" data-ancho="320">Móvil</button>
                <button type="button" class="boton dos pequeno" data-ancho="380">Tarjeta</button>
                <button type="button" class="boton dos pequeno" data-ancho="480">Ancha</button>
            </div>
        </div>
        <p class="admin-page-subtitle">
            Fichas de ejemplo en memoria pintadas con las tarjetas y el motor reales. <strong>No se guarda nada</strong>:
            edita reglas, puntos, iconos o colores en <a href="insignias.php">Insignias</a>, vuelve aquí y recarga para ver el efecto.
            Para repetir exactamente estas fichas barajadas: <code>?semilla=<?php echo $semilla; ?></code>.
        </p>
    </header>

    <!-- Ranking simulado -->
    <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="ficha-card__title"><i data-lucide="list-ordered" class="icono"></i> Ranking simulado</h2>
        <div class="admin-table-wrap" style="overflow-x:auto;">
        <table class="admin-table">
            <thead><tr><th>#</th><th>Escenario</th><th>Insignias</th><th style="text-align:right;">Puntos insignias</th><th style="text-align:right;">Calidad</th><th style="text-align:right;">Total</th></tr></thead>
            <tbody>
            <?php foreach ($orden as $k): $e = $escenarios[$k]; ?>
            <tr>
                <td style="font-variant-numeric:tabular-nums;"><?php echo $e['pos']; ?></td>
                <td><div class="admin-table__principal"><?php echo htmlspecialchars($e['titulo']); ?></div>
                    <div class="admin-table__secundario"><?php echo htmlspecialchars(mb_substr($e['cr']['nombre'], 0, 50)); ?></div></td>
                <td><?php $insigniasFila = $e['ficha']; $insigniasMax = 0; $insigniasTam = ''; $insigniasTitulo = true; $insigniasAnidada = false; include dirname(__DIR__) . '/includes/componentes/insignias-fila.php'; unset($insigniasTitulo); ?></td>
                <td style="text-align:right; font-variant-numeric:tabular-nums;"><?php echo number_format($e['puntaje'] - $e['calc']['calidad'], 1); ?></td>
                <td style="text-align:right; font-variant-numeric:tabular-nums;"><?php echo number_format($e['calc']['calidad'], 1); ?></td>
                <td style="text-align:right; font-weight:700; font-variant-numeric:tabular-nums;"><?php echo number_format($e['puntaje'], 1); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <!-- Escenarios -->
    <?php foreach ($escenarios as $e): $cr = $e['cr']; ?>
    <section class="ficha-card" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="ficha-card__title" style="display:flex; gap:var(--espacio-tres); align-items:center; flex-wrap:wrap;">
            <?php echo htmlspecialchars($e['titulo']); ?>
            <span class="admin-pill <?php echo $e['tipo'] === 'fijo' ? 'admin-pill--info' : ''; ?>"><?php echo $e['tipo'] === 'fijo' ? 'caso límite' : 'datos reales mezclados'; ?></span>
            <span class="admin-pill">puesto <?php echo $e['pos']; ?> · <?php echo number_format($e['puntaje'], 1); ?> pts</span>
        </h2>

        <div style="display:grid; grid-template-columns:auto 1fr; gap:var(--espacio-cuatro); align-items:start;" class="lab-fila">
            <!-- Tarjeta real -->
            <div class="lab-tarjeta" style="width:380px; max-width:100%;">
                <?php $crem = $e['crem']; $insigniasAnidada = false; include dirname(__DIR__) . '/includes/componentes/tarjeta-crematorio.php'; ?>
            </div>

            <div style="min-width:0; display:flex; flex-direction:column; gap:var(--espacio-tres);">
                <!-- Encabezado de ficha (réplica del de ficha.php: insignias en grande) -->
                <div style="border:1px dashed var(--admin-linea-fuerte); border-radius:var(--admin-r-md); padding:var(--espacio-tres) var(--espacio-cuatro);">
                    <div class="admin-text-muted" style="font-size:.75rem; margin-bottom:.4rem;">Cabecera de la ficha pública</div>
                    <div class="directorio-encabezado__fila">
                        <h3 class="directorio-encabezado__titulo" style="margin:0;"><?php echo htmlspecialchars($cr['nombre']); ?></h3>
                        <?php $insigniasFila = $e['ficha']; $insigniasMax = 0; $insigniasTam = 'grande'; $insigniasAnidada = false; include dirname(__DIR__) . '/includes/componentes/insignias-fila.php'; ?>
                    </div>
                </div>

                <!-- Por qué tiene esas insignias -->
                <div class="admin-text-muted" style="font-size:.85rem; line-height:1.6;">
                    <strong>Datos simulados:</strong>
                    completitud <?php echo (int) $cr['pct']; ?> % ·
                    rating <?php echo $e['rating_c']['total'] ? number_format($e['rating_c']['rating'], 1) . ' (' . $e['rating_c']['total'] . ' reseñas)' : 'sin reseñas'; ?> ·
                    24 h <?php echo $cr['atencion_24h'] ? 'sí' : 'no'; ?> ·
                    domicilio <?php echo ($cr['recogida_domicilio'] || $cr['entrega_domicilio']) ? 'sí' : 'no'; ?> ·
                    Google <?php echo $cr['google_verificado'] ? 'verificado' : 'no verificado'; ?> ·
                    <?php echo $cr['origen'] === 'registro' ? 'registrado por el dueño' : 'importado'; ?>
                    <?php if ($cr['popular']): ?> · popularidad nivel <?php echo (int) $cr['popular']; ?><?php endif; ?>
                    <?php if ($cr['resena_reciente']): ?> · reseña reciente<?php endif; ?>
                    <br>
                    <strong>Puntos:</strong>
                    <?php
                    $partes = [];
                    foreach ($e['calc']['desglose'] as $slug => $p) $partes[] = htmlspecialchars($nombresInsignia[$slug] ?? $slug) . ' +' . (int) $p;
                    echo $partes ? implode(' · ', $partes) : 'ninguna insignia';
                    echo ' · calidad +' . number_format($e['calc']['calidad'], 1);
                    $enTarj = count($e['tarjeta']);
                    if ($enTarj > 4) echo '<br><strong>En la tarjeta:</strong> se ven 4 iconos y «+' . ($enTarj - 4) . '».';
                    ?>
                </div>
            </div>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<style>
    @media (max-width: 900px) { .lab-fila { grid-template-columns: 1fr !important; } }
</style>
<script>
document.querySelectorAll('.js-lab-anchos button').forEach(function (b) {
    b.addEventListener('click', function () {
        document.querySelectorAll('.lab-tarjeta').forEach(function (t) { t.style.width = b.dataset.ancho + 'px'; });
    });
});
</script>

<?php include 'footer.php'; ?>
