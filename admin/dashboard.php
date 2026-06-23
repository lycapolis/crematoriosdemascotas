<?php
/**
 * ═══════════════════════════════════════════════════════════
 * DASHBOARD - PANEL ADMIN
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$pdo = obtenerConexion();

// ─── Score SQL: espeja la definición de includes/completitud.php ──────────────
// (mismos 12 checks, en SQL para poder ORDER BY/agregar en DB). Si cambia
// definicionCompletitud(), actualizar este CASE en consecuencia.

$scoreSql = "
    CASE WHEN COALESCE(c.telefono,'')           != '' OR COALESCE(c.telefono_clientes,'') != '' THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.email,'')              != '' OR COALESCE(c.email_clientes,'')    != '' THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.website,'')            != ''                                            THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.direccion_completa,'') != ''                                            THEN 1 ELSE 0 END +
    CASE WHEN c.latitud IS NOT NULL AND c.longitud IS NOT NULL                                   THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.descripcion,'')        != '' AND CHAR_LENGTH(c.descripcion) >= 150      THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.horarios,'')           != ''                                            THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.zona_cobertura,'')     != ''                                            THEN 1 ELSE 0 END +
    CASE WHEN c.cremacion_individual IS NOT NULL OR c.cremacion_colectiva IS NOT NULL
          OR  c.recogida_domicilio   IS NOT NULL OR c.entrega_domicilio    IS NOT NULL            THEN 1 ELSE 0 END +
    CASE WHEN COALESCE(c.meta_description_seo,'') != ''                                          THEN 1 ELSE 0 END +
    IF((SELECT COUNT(*) FROM crematorio_imagenes ci  WHERE ci.crematorio_id  = c.id AND ci.estado_llm = 'procesada') > 0, 1, 0) +
    IF((SELECT COUNT(*) FROM crematorio_imagenes ci2 WHERE ci2.crematorio_id = c.id AND ci2.tipo = 'logo' AND ci2.categoria = 'logo') > 0, 1, 0)
";

// ─── Datos para el dashboard ──────────────────────────────────────────────────

// Crematorios
$cremStats = $pdo->query("
    SELECT
        COUNT(*)                                                   AS total,
        SUM(activo)                                                AS activos,
        COUNT(*) - SUM(activo)                                     AS inactivos,
        ROUND(AVG(($scoreSql) / 12 * 100))                        AS pct_medio
    FROM crematorios c
")->fetch(PDO::FETCH_ASSOC);

// Incompletos (score < 10 de 12, es decir < 83%)
$cremIncompletos = (int) $pdo->query("
    SELECT COUNT(*) FROM crematorios c WHERE ($scoreSql) < 10
")->fetchColumn();

// Sin imágenes procesadas
$cremSinImagenes = (int) $pdo->query("
    SELECT COUNT(*) FROM crematorios c
    WHERE NOT EXISTS (SELECT 1 FROM crematorio_imagenes ci WHERE ci.crematorio_id = c.id AND ci.estado_llm = 'procesada')
")->fetchColumn();

// Sin logo
$cremSinLogo = (int) $pdo->query("
    SELECT COUNT(*) FROM crematorios c
    WHERE NOT EXISTS (SELECT 1 FROM crematorio_imagenes ci WHERE ci.crematorio_id = c.id AND ci.tipo = 'logo' AND ci.categoria = 'logo')
")->fetchColumn();

// Sin descripción (< 150 chars)
$cremSinDesc = (int) $pdo->query("
    SELECT COUNT(*) FROM crematorios
    WHERE descripcion IS NULL OR CHAR_LENGTH(descripcion) < 150
")->fetchColumn();

// Sin horarios
$cremSinHorarios = (int) $pdo->query("
    SELECT COUNT(*) FROM crematorios
    WHERE horarios IS NULL OR horarios = ''
")->fetchColumn();

// Solicitudes de registro (formulario público)
$solStats = ['total' => 0, 'pendientes' => 0, 'aprobadas' => 0, 'rechazadas' => 0];
try {
    $row = $pdo->query("
        SELECT
            COUNT(*)                        AS total,
            SUM(estado = 'pendiente')       AS pendientes,
            SUM(estado = 'aprobada')        AS aprobadas,
            SUM(estado = 'rechazada')       AS rechazadas
        FROM solicitudes_registro
    ")->fetch(PDO::FETCH_ASSOC);
    if ($row) $solStats = $row;
} catch (PDOException $e) { /* tabla puede no existir en algún entorno */ }

// Reseñas
$resStats = $pdo->query("
    SELECT
        COUNT(*)                                        AS total,
        SUM(estado = 'pendiente')                       AS pendientes,
        SUM(estado = 'aprobada')                        AS aprobadas,
        SUM(estado = 'rechazada')                       AS rechazadas,
        SUM(estado = 'rechazada' AND es_spam = 1)       AS spam
    FROM resenas
")->fetch(PDO::FETCH_ASSOC);
$nSpam = (int) ($resStats['spam'] ?? 0);

// Imágenes LLM — solo locales (las URL no se pueden procesar con LLM)
$imgStats = $pdo->query("
    SELECT
        COUNT(*)                                        AS total,
        SUM(estado_llm = 'pendiente')                   AS pendientes,
        SUM(estado_llm = 'procesada')                   AS procesadas,
        SUM(estado_llm = 'error')                       AS errores
    FROM crematorio_imagenes
    WHERE ruta NOT LIKE 'http%'
")->fetch(PDO::FETCH_ASSOC);

// Alt texts pendientes — locales con categoría sin alt text o con duplicados
$nSinAlt = (int)$pdo->query("
    SELECT COUNT(*) FROM crematorio_imagenes
    WHERE (alt_text IS NULL OR alt_text = '')
      AND categoria IS NOT NULL AND categoria != ''
      AND ruta NOT LIKE 'http%'
")->fetchColumn();
$nDupAlt = (int)$pdo->query("
    SELECT COUNT(DISTINCT ci.id) FROM crematorio_imagenes ci
    INNER JOIN (
        SELECT crematorio_id, alt_text FROM crematorio_imagenes
        WHERE alt_text IS NOT NULL AND alt_text != '' AND ruta NOT LIKE 'http%'
        GROUP BY crematorio_id, alt_text HAVING COUNT(*) > 1
    ) dups ON ci.crematorio_id = dups.crematorio_id AND ci.alt_text = dups.alt_text
")->fetchColumn();
$altPendientes = $nSinAlt + $nDupAlt;

// ─── Construir array de tareas pendientes (se ordena por count DESC) ─────────
$errsHtml = $imgStats['errores'] > 0
    ? ' <span class="admin-dash"></span> <span style="color: var(--admin-tone-error-fg); font-weight: 600;">' . (int)$imgStats['errores'] . ' errores</span>'
    : '';

$tareas = [
    [
        'count'    => (int)$solStats['pendientes'],
        'kicker'   => 'Solicitudes de registro',
        'icon'     => 'user-plus',
        'href'     => 'solicitudes.php?estado=pendiente',
        'urgencia' => $solStats['pendientes'] > 0 ? 'urgente' : 'ok',
        'estado'   => $solStats['pendientes'] > 0 ? 'Atención' : 'En orden',
        'sub_html' => (int)$solStats['aprobadas'] . ' aprobadas <span class="admin-dash"></span> ' . (int)$solStats['rechazadas'] . ' rechazadas <span class="admin-dash"></span> ' . (int)$solStats['total'] . ' total',
        'cta'      => 'Ir a solicitudes',
    ],
    [
        'count'    => (int)$resStats['pendientes'],
        'kicker'   => 'Reseñas por aprobar',
        'icon'     => 'star',
        'href'     => 'resenas.php',
        'urgencia' => $resStats['pendientes'] > 0 ? 'urgente' : 'ok',
        'estado'   => $resStats['pendientes'] > 0 ? 'Atención' : 'En orden',
        'sub_html' => (int)$resStats['aprobadas'] . ' aprobadas <span class="admin-dash"></span> ' . (int)$resStats['rechazadas'] . ' rechazadas <span class="admin-dash"></span> ' . (int)$resStats['total'] . ' total',
        'cta'      => 'Ir a reseñas',
    ],
    [
        'count'    => (int)$imgStats['pendientes'],
        'kicker'   => 'Imágenes sin procesar',
        'icon'     => 'image',
        'href'     => 'imagenes-cola.php',
        'urgencia' => $imgStats['pendientes'] > 0 ? 'alerta' : 'ok',
        'estado'   => $imgStats['pendientes'] > 0 ? 'Pendiente' : 'En orden',
        'sub_html' => (int)$imgStats['procesadas'] . ' ya procesadas' . $errsHtml . ' <span class="admin-dash"></span> ' . (int)$imgStats['total'] . ' total',
        'cta'      => 'Ir a cola LLM',
    ],
    [
        'count'    => $altPendientes,
        'kicker'   => 'Alt texts pendientes',
        'icon'     => 'text',
        'href'     => 'imagenes-cola.php',
        'urgencia' => $altPendientes > 0 ? 'alerta' : 'ok',
        'estado'   => $altPendientes > 0 ? 'Pendiente' : 'En orden',
        'sub_html' => $nSinAlt . ' sin alt text <span class="admin-dash"></span> ' . $nDupAlt . ' duplicados',
        'cta'      => 'Ir a cola LLM',
    ],
    [
        'count'    => $cremIncompletos,
        'kicker'   => 'Fichas incompletas',
        'icon'     => 'building-2',
        'href'     => 'fichas-negocios.php?orden=completitud_asc',
        'urgencia' => $cremIncompletos > 0 ? 'alerta' : 'ok',
        'estado'   => $cremIncompletos > 0 ? 'Pendiente' : 'En orden',
        'sub_html' => 'Con menos del 83% de completitud',
        'cta'      => 'Ver los más incompletos',
    ],
];

// Orden: cards con más pendientes primero. En empate, mantener el orden original.
usort($tareas, function($a, $b) {
    return $b['count'] <=> $a['count'];
});
// SPAM se renderiza como banner aparte (no en la grid) — solo si hay

$titulo_pagina = 'Dashboard - Admin';
include 'header.php';
?>

<div class="admin-page">

    <!-- ═══ Page header ═══════════════════════════════════════════════════ -->
    <header class="admin-page-header">
        <h1 class="admin-page-title">Administración</h1>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo (int)$cremStats['activos']; ?></span> negocios activos
            <span class="admin-dash"></span>
            Completitud media <strong style="color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums;"><?php echo (int)$cremStats['pct_medio']; ?>%</strong>
        </p>
    </header>

    <!-- ═══ Tareas pendientes ═════════════════════════════════════════════ -->
    <section class="admin-section">
        <div class="admin-section__heading">
            <h2 class="admin-section__title">Tareas pendientes</h2>
        </div>

        <?php if ($nSpam > 0): ?>
        <div class="admin-spam-alert">
            <span class="admin-spam-alert__icon">
                <i data-lucide="alert-triangle" class="icono"></i>
            </span>
            <div class="admin-spam-alert__contenido">
                <span class="admin-spam-alert__titulo">
                    <?php echo $nSpam; ?> reseña<?php echo $nSpam === 1 ? '' : 's'; ?> marcada<?php echo $nSpam === 1 ? '' : 's'; ?> como SPAM
                </span>
                <span class="admin-spam-alert__texto">
                    Ya rechazadas — listas para borrarse en lote.
                </span>
            </div>
            <div class="admin-spam-alert__acciones">
                <a href="resenas.php?estado=spam" class="boton dos pequeno">
                    <i data-lucide="eye" class="icono" style="width: 14px; height: 14px;"></i>
                    Ver SPAM
                </a>
                <button type="button"
                        id="dash-btn-eliminar-spam"
                        onclick="dashEliminarTodoSpam(<?php echo $nSpam; ?>)"
                        class="boton pequeno"
                        style="background: var(--color-siete); color: var(--color-cuatro); border-color: var(--color-siete);">
                    <i data-lucide="trash-2" class="icono" style="width: 14px; height: 14px;"></i>
                    Eliminar todo
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="admin-grid-stats">
            <?php foreach ($tareas as $t): ?>
            <a href="<?php echo htmlspecialchars($t['href']); ?>" class="admin-stat-card admin-stat-card--<?php echo $t['urgencia']; ?>">
                <div class="admin-stat-card__top">
                    <span class="admin-stat-card__icon"><i data-lucide="<?php echo $t['icon']; ?>" class="icono"></i></span>
                    <span class="admin-stat-card__kicker"><?php echo htmlspecialchars($t['kicker']); ?></span>
                </div>
                <div class="admin-stat-card__cifra-row">
                    <span class="admin-stat-card__cifra"><?php echo (int)$t['count']; ?></span>
                    <span class="admin-stat-card__estado"><?php echo htmlspecialchars($t['estado']); ?></span>
                </div>
                <div class="admin-stat-card__sub"><?php echo $t['sub_html']; ?></div>
                <span class="admin-stat-card__cta">
                    <?php echo htmlspecialchars($t['cta']); ?>
                    <span class="admin-stat-card__arrow">→</span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ═══ Gaps en fichas ════════════════════════════════════════════════ -->
    <section class="admin-section">
        <div class="admin-section__heading">
            <h2 class="admin-section__title">Gaps en fichas</h2>
        </div>

        <div class="admin-grid-mini">
            <div class="admin-mini-stat<?php echo $cremSinImagenes > 0 ? ' admin-mini-stat--alerta' : ' admin-mini-stat--ok'; ?>">
                <div class="admin-mini-stat__num"><?php echo $cremSinImagenes; ?></div>
                <div class="admin-mini-stat__label">Sin imágenes procesadas</div>
            </div>
            <div class="admin-mini-stat<?php echo $cremSinLogo > 0 ? ' admin-mini-stat--alerta' : ' admin-mini-stat--ok'; ?>">
                <div class="admin-mini-stat__num"><?php echo $cremSinLogo; ?></div>
                <div class="admin-mini-stat__label">Sin logo</div>
            </div>
            <div class="admin-mini-stat<?php echo $cremSinDesc > 0 ? ' admin-mini-stat--alerta' : ' admin-mini-stat--ok'; ?>">
                <div class="admin-mini-stat__num"><?php echo $cremSinDesc; ?></div>
                <div class="admin-mini-stat__label">Sin descripción <span style="opacity: .6;">(≥ 150 chars)</span></div>
            </div>
            <div class="admin-mini-stat<?php echo $cremSinHorarios > 0 ? ' admin-mini-stat--alerta' : ' admin-mini-stat--ok'; ?>">
                <div class="admin-mini-stat__num"><?php echo $cremSinHorarios; ?></div>
                <div class="admin-mini-stat__label">Sin horarios</div>
            </div>
        </div>
    </section>

</div>

<?php if ($nSpam > 0): ?>
<script>
function dashEliminarTodoSpam(n) {
    confirmar({
        titulo: 'Eliminar todo el SPAM',
        mensaje: 'Se borran las <strong>' + n + ' reseña' + (n === 1 ? '' : 's') + '</strong> completa' + (n === 1 ? '' : 's') +
                 ' (texto, autor, fechas) y todas las imágenes adjuntas (archivos + BD).' +
                 '<br><br>⚠ Acción irreversible. No vas a poder recuperar nada.',
        textoOK: 'Eliminar todo',
        peligroso: true,
        onOK: function () { proceder(); }
    });

    function proceder() {
    const btn = document.getElementById('dash-btn-eliminar-spam');
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '.6';
        btn.innerHTML = 'Eliminando…';
    }

    fetch('<?php echo BASE_URL; ?>/admin/resena-eliminar-spam-lote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'confirmar=1'
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            toast.ok(data.mensaje);
            setTimeout(function () { location.reload(); }, 900);
        } else {
            toast.error(data.mensaje || 'Error al procesar');
            if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = '<i data-lucide="trash-2" class="icono" style="width:14px;height:14px;"></i> Eliminar todo'; }
        }
    })
    .catch(() => {
        toast.error('Error de conexión');
        if (btn) { btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = '<i data-lucide="trash-2" class="icono" style="width:14px;height:14px;"></i> Eliminar todo'; }
    });
    } // fin proceder()
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
