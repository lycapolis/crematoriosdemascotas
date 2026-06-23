<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LEADS B2C — bandeja admin
 * Consumidores finales contactando negocios listados.
 * Capturados por el widget interno lead-capture (modal del sitio + burbuja).
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('solicitudes');

$admin = obtenerAdminActual();
$pdo   = obtenerConexion();

$ESTADOS  = ['nuevo', 'contactado', 'cerrado', 'descartado'];
$CANALES  = ['tel', 'wa', 'maps', 'web'];

// ─── POST: actualizar estado + notas (PRG) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id     = (int) ($_POST['lead_id'] ?? 0);
    $nuevoEstado = in_array($_POST['estado'] ?? '', $ESTADOS, true) ? $_POST['estado'] : null;
    $notas       = trim($_POST['notas_admin'] ?? '');

    if ($lead_id && $nuevoEstado) {
        try {
            $up = $pdo->prepare("UPDATE leads_b2c SET estado = :e, notas_admin = :n WHERE id = :id");
            $up->execute([':e' => $nuevoEstado, ':n' => ($notas !== '' ? $notas : null), ':id' => $lead_id]);
            header('Location: leads-b2c.php?estado=' . urlencode($_POST['volver_estado'] ?? 'todas') . '&ok=' . urlencode('Lead actualizado'));
        } catch (PDOException $e) {
            header('Location: leads-b2c.php?error=' . urlencode('No se pudo actualizar'));
        }
        exit;
    }
    header('Location: leads-b2c.php?error=' . urlencode('Datos inválidos'));
    exit;
}

// ─── Filtros ────────────────────────────────────────────────────────────────
$estados_validos = array_merge($ESTADOS, ['todas']);
$estado_explicito = isset($_GET['estado']) && in_array($_GET['estado'], $estados_validos, true);
$filtro_estado = $estado_explicito ? $_GET['estado'] : 'nuevo';

if (!$estado_explicito) {
    $hayNuevos = (int) $pdo->query("SELECT COUNT(*) FROM leads_b2c WHERE estado = 'nuevo'")->fetchColumn();
    if ($hayNuevos === 0) $filtro_estado = 'todas';
}

$filtro_canal = (isset($_GET['canal']) && in_array($_GET['canal'], $CANALES, true)) ? $_GET['canal'] : '';

// ─── Query ──────────────────────────────────────────────────────────────────
$conds  = [];
$params = [];
if ($filtro_estado !== 'todas') { $conds[] = 'l.estado = :estado';      $params[':estado'] = $filtro_estado; }
if ($filtro_canal !== '')        { $conds[] = 'l.channel_type = :canal'; $params[':canal']  = $filtro_canal; }
$where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

$stmt = $pdo->prepare("SELECT l.*, c.nombre AS crematorio_nombre_actual, c.slug AS crematorio_slug
                       FROM leads_b2c l
                       LEFT JOIN crematorios c ON l.crematorio_id = c.id
                       $where
                       ORDER BY l.created_at DESC
                       LIMIT 200");
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Contadores por estado ──────────────────────────────────────────────────
$cont = $pdo->query("SELECT
    SUM(estado='nuevo')      AS nuevo,
    SUM(estado='contactado') AS contactado,
    SUM(estado='cerrado')    AS cerrado,
    SUM(estado='descartado') AS descartado,
    COUNT(*)                 AS total
    FROM leads_b2c")->fetch(PDO::FETCH_ASSOC);

// ─── Métricas dashboard (últimos 30 días) ───────────────────────────────────
$desde30 = date('Y-m-d H:i:s', strtotime('-30 days'));

$metricas = $pdo->prepare("SELECT
    COUNT(*) AS total_clicks,
    SUM(modal_action='sent')      AS forms_completados,
    SUM(modal_action='skipped')   AS forms_saltados,
    SUM(modal_action='cancelled') AS forms_cancelados,
    SUM(modal_action='click')     AS clicks_directos
    FROM outbound_clicks
    WHERE created_at >= ?");
$metricas->execute([$desde30]);
$metricas = $metricas->fetch(PDO::FETCH_ASSOC) ?: [];

$tasaConversion = ($metricas['total_clicks'] ?? 0) > 0
    ? round(((int)$metricas['forms_completados'] / (int)$metricas['total_clicks']) * 100, 1)
    : 0;

// Top 5 fichas con más clicks salientes (últimos 30 días)
$topFichas = $pdo->prepare("SELECT oc.crematorio_id, c.nombre, COUNT(*) AS total
    FROM outbound_clicks oc
    LEFT JOIN crematorios c ON oc.crematorio_id = c.id
    WHERE oc.created_at >= ? AND oc.crematorio_id IS NOT NULL
    GROUP BY oc.crematorio_id, c.nombre
    ORDER BY total DESC
    LIMIT 5");
$topFichas->execute([$desde30]);
$topFichas = $topFichas->fetchAll(PDO::FETCH_ASSOC);

// ─── Helpers de presentación ────────────────────────────────────────────────
function leadPill(string $e): string {
    return match ($e) {
        'nuevo'      => 'admin-pill--alerta',
        'contactado' => 'admin-pill--marca',
        'cerrado'    => 'admin-pill--exito',
        'descartado' => 'admin-pill--error',
        default      => '',
    };
}
function canalIcon(string $c): string {
    return match ($c) {
        'tel'  => 'phone',
        'wa'   => 'message-circle',
        'maps' => 'navigation',
        'web'  => 'globe',
        default => 'link',
    };
}
function canalLabel(string $c): string {
    return match ($c) {
        'tel'  => 'Teléfono',
        'wa'   => 'WhatsApp',
        'maps' => 'Maps',
        'web'  => 'Sitio web',
        default => $c,
    };
}
$ESTADO_LABEL = [
    'nuevo'      => 'Nuevo',
    'contactado' => 'Contactado',
    'cerrado'    => 'Cerrado',
    'descartado' => 'Descartado',
];

$titulo_pagina = 'Leads B2C - Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Leads B2C</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Consumidores finales capturados por el widget interno (modal + burbuja). Contactá, gestioná el estado y dejá notas.
            </p>
        </div>
        <div class="admin-page-header__actions">
            <a href="notif-leads-test.php" class="boton dos pequeno" title="Probar plantillas de email de notificación">
                <i data-lucide="mail-check" class="icono"></i>
                Probar notificaciones
            </a>
        </div>
    </header>

    <!-- ═══ Dashboard de métricas (últimos 30 días) ═══ -->
    <section class="panel" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="panel__title" style="margin-bottom:var(--espacio-tres);">
            <i data-lucide="bar-chart-3" class="icono"></i>
            Métricas — últimos 30 días
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:var(--espacio-tres);">
            <div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--admin-tinta-fuerte); line-height:1;"><?php echo (int)($metricas['total_clicks'] ?? 0); ?></div>
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-top:.25rem;">Clicks salientes</div>
            </div>
            <div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--color-uno); line-height:1;"><?php echo (int)($metricas['forms_completados'] ?? 0); ?></div>
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-top:.25rem;">Leads completados</div>
            </div>
            <div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--admin-tinta-fuerte); line-height:1;"><?php echo (int)($metricas['forms_saltados'] ?? 0); ?></div>
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-top:.25rem;">Saltaron el form</div>
            </div>
            <div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--admin-tinta-fuerte); line-height:1;"><?php echo (int)($metricas['forms_cancelados'] ?? 0); ?></div>
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-top:.25rem;">Cancelaron el form</div>
            </div>
            <div>
                <div style="font-size:1.75rem; font-weight:700; color:var(--color-uno); line-height:1;"><?php echo $tasaConversion; ?>%</div>
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); margin-top:.25rem;">Tasa de conversión<br><small>(completados / clicks)</small></div>
            </div>
        </div>

        <?php if (!empty($topFichas)): ?>
        <div style="margin-top:var(--espacio-cuatro); padding-top:var(--espacio-tres); border-top:1px solid var(--admin-linea);">
            <h3 style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600; margin-bottom:var(--espacio-dos); text-transform:uppercase; letter-spacing:.3px;">Top 5 fichas con más clicks (30 días)</h3>
            <ol style="margin:0; padding-left:1.25rem; line-height:1.7;">
                <?php foreach ($topFichas as $tf): ?>
                <li>
                    <strong><?php echo (int)$tf['total']; ?></strong>
                    <?php echo $tf['nombre'] ? htmlspecialchars($tf['nombre']) : '<em>(ficha eliminada)</em>'; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
    </section>

    <!-- ═══ Tabs por estado ═══ -->
    <nav class="admin-tabs">
        <?php
        $qsExtra = $filtro_canal ? '&canal=' . urlencode($filtro_canal) : '';
        foreach ([
            'nuevo'      => 'Nuevos',
            'contactado' => 'Contactados',
            'cerrado'    => 'Cerrados',
            'descartado' => 'Descartados',
            'todas'      => 'Todos',
        ] as $est => $lbl):
            $countKey = $est === 'todas' ? 'total' : $est;
        ?>
        <a href="?estado=<?php echo $est . $qsExtra; ?>" class="admin-tab<?php echo $filtro_estado===$est?' admin-tab--activo':''; ?>">
            <?php echo $lbl; ?> <span class="admin-tab__count"><?php echo (int)($cont[$countKey] ?? 0); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- ═══ Filtro por canal ═══ -->
    <div style="display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; margin-bottom:var(--espacio-cuatro); font-size:var(--admin-body-sm);">
        <span style="color:var(--admin-tinta-suave);">Filtrar por canal:</span>
        <a href="?estado=<?php echo urlencode($filtro_estado); ?>" class="boton <?php echo $filtro_canal===''?'uno':'dos'; ?> pequeno">Todos</a>
        <?php foreach ($CANALES as $c): ?>
        <a href="?estado=<?php echo urlencode($filtro_estado); ?>&canal=<?php echo $c; ?>" class="boton <?php echo $filtro_canal===$c?'uno':'dos'; ?> pequeno" style="display:inline-flex; gap:.3rem; align-items:center;">
            <i data-lucide="<?php echo canalIcon($c); ?>" style="width:14px;height:14px;"></i> <?php echo canalLabel($c); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ═══ Listado de leads ═══ -->
    <?php if (empty($leads)): ?>
        <div class="panel" style="text-align:center; color:var(--admin-tinta-suave);">
            <i data-lucide="inbox" style="width:32px;height:32px;"></i>
            <p style="margin:var(--espacio-dos) 0 0;">No hay leads en este filtro.</p>
        </div>
    <?php else: foreach ($leads as $l):
        $cremNombre = $l['crematorio_nombre_actual'] ?: $l['crematorio_nombre'] ?: '';
        $cremUrl    = $l['crematorio_slug'] ? '/' . $l['crematorio_slug'] : '';
    ?>
        <section class="panel" style="margin-bottom:var(--espacio-cuatro);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:var(--espacio-tres); flex-wrap:wrap;">
                <div style="min-width:0; flex:1;">
                    <h2 class="panel__title" style="margin-bottom:var(--espacio-uno);">
                        <i data-lucide="<?php echo canalIcon($l['channel_type'] ?? ''); ?>" class="icono"></i>
                        <?php echo htmlspecialchars($l['nombre']); ?>
                    </h2>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
                        <span><?php echo date('d/m/Y H:i', strtotime($l['created_at'])); ?></span>
                        <span>·</span>
                        <span><strong><?php echo canalLabel($l['channel_type'] ?? ''); ?></strong></span>
                        <?php if ($cremNombre): ?>
                        <span>·</span>
                        <span>
                            <?php if ($cremUrl): ?>
                            <a href="<?php echo htmlspecialchars(BASE_URL . $cremUrl); ?>" target="_blank" class="admin-link"><?php echo htmlspecialchars($cremNombre); ?></a>
                            <?php else: ?>
                            <?php echo htmlspecialchars($cremNombre); ?> <em>(ficha eliminada)</em>
                            <?php endif; ?>
                        </span>
                        <?php else: ?>
                        <span>·</span>
                        <span><em>Lead genérico (no de ficha)</em></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="admin-pill <?php echo leadPill($l['estado']); ?>"><?php echo $ESTADO_LABEL[$l['estado']] ?? $l['estado']; ?></span>
            </div>

            <!-- Datos del lead -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--espacio-tres); margin-top:var(--espacio-tres);">
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Email</div>
                    <a href="mailto:<?php echo htmlspecialchars($l['email']); ?>" class="admin-link"><?php echo htmlspecialchars($l['email']); ?></a>
                </div>
                <?php if (!empty($l['whatsapp_number'])): ?>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Teléfono</div>
                    <a href="tel:+<?php echo htmlspecialchars(($l['phone_code']??'').($l['whatsapp_number']??'')); ?>" class="admin-link">
                        +<?php echo htmlspecialchars($l['phone_code'] ?? ''); ?> <?php echo htmlspecialchars($l['whatsapp_number']); ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (!empty($l['ciudad_lead'])): ?>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Ciudad del usuario</div>
                    <?php echo htmlspecialchars($l['ciudad_lead']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($l['servicio'])): ?>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Mascota</div>
                    <?php echo htmlspecialchars($l['servicio']); ?>
                    <?php if (!empty($l['mascota_tamano'])): ?>
                    · <?php echo htmlspecialchars($l['mascota_tamano']); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($l['mensaje'])): ?>
            <div style="margin-top:var(--espacio-tres);">
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600; margin-bottom:.25rem;">Mensaje del usuario</div>
                <div style="background:var(--admin-papel-alt); border-radius:var(--admin-r-sm); padding:var(--espacio-tres); font-size:var(--admin-body-sm); line-height:1.55;">
                    <?php echo nl2br(htmlspecialchars($l['mensaje'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Meta técnica colapsable -->
            <details style="margin-top:var(--espacio-tres); font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);">
                <summary style="cursor:pointer;">Detalles técnicos</summary>
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:var(--espacio-dos); margin-top:var(--espacio-dos);">
                    <?php if ($l['phone_agent']): ?><div><strong>Destino:</strong> <?php echo htmlspecialchars($l['phone_agent']); ?></div><?php endif; ?>
                    <?php if ($l['pagina_origen']): ?><div><strong>Página origen:</strong> <?php echo htmlspecialchars($l['pagina_origen']); ?></div><?php endif; ?>
                    <?php if ($l['ip']): ?><div><strong>IP:</strong> <?php echo htmlspecialchars($l['ip']); ?></div><?php endif; ?>
                    <?php if ($l['utm_source']): ?><div><strong>UTM source:</strong> <?php echo htmlspecialchars($l['utm_source']); ?></div><?php endif; ?>
                    <?php if ($l['utm_campaign']): ?><div><strong>UTM campaign:</strong> <?php echo htmlspecialchars($l['utm_campaign']); ?></div><?php endif; ?>
                    <div><strong>Webhook:</strong> <?php echo $l['webhook_enviado'] ? '✓ enviado' : '✗ falló'; ?></div>
                    <?php if (!empty($l['webhook_error'])): ?><div style="color:var(--color-siete);"><strong>Error:</strong> <?php echo htmlspecialchars($l['webhook_error']); ?></div><?php endif; ?>
                </div>
            </details>

            <!-- Form de actualización -->
            <form method="POST" style="margin-top:var(--espacio-cuatro); border-top:1px solid var(--admin-linea); padding-top:var(--espacio-tres); display:grid; gap:var(--espacio-tres);">
                <input type="hidden" name="lead_id" value="<?php echo (int)$l['id']; ?>">
                <input type="hidden" name="volver_estado" value="<?php echo htmlspecialchars($filtro_estado); ?>">
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--espacio-tres); align-items:end;">
                    <div class="field" style="margin-bottom:0;">
                        <label class="field__label">Estado</label>
                        <select name="estado" class="field__select">
                            <?php foreach ($ESTADOS as $e): ?>
                            <option value="<?php echo $e; ?>" <?php echo $l['estado']===$e?'selected':''; ?>><?php echo $ESTADO_LABEL[$e]; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="boton uno pequeno" style="justify-content:center;">
                        <i data-lucide="save" class="icono"></i> Guardar
                    </button>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label">Notas internas</label>
                    <textarea name="notas_admin" class="field__textarea" rows="2" placeholder="Seguimiento, acuerdos, próximos pasos…"><?php echo htmlspecialchars($l['notas_admin'] ?? ''); ?></textarea>
                </div>
            </form>
        </section>
    <?php endforeach; endif; ?>

</div>

<?php include 'footer.php'; ?>
