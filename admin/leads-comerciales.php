<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LEADS COMERCIALES B2B — bandeja admin
 * Form "Promocionar mi crematorio" (popup + futura landing).
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('solicitudes'); // mismo permiso que Solicitudes (decisión 2026-05-19)

$admin = obtenerAdminActual();
$pdo   = obtenerConexion();

$ESTADOS = ['nuevo', 'en_proceso', 'cerrado', 'descartado'];

// ─── POST: actualizar estado + notas (PRG) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lead_id = (int) ($_POST['lead_id'] ?? 0);
    $nuevoEstado = in_array($_POST['estado'] ?? '', $ESTADOS, true) ? $_POST['estado'] : null;
    $notas = trim($_POST['notas_admin'] ?? '');

    if ($lead_id && $nuevoEstado) {
        try {
            $up = $pdo->prepare("UPDATE leads_comerciales SET estado = :e, notas_admin = :n WHERE id = :id");
            $up->execute([':e' => $nuevoEstado, ':n' => ($notas !== '' ? $notas : null), ':id' => $lead_id]);
            header('Location: leads-comerciales.php?estado=' . urlencode($_POST['volver_estado'] ?? 'todas') . '&ok=' . urlencode('Lead actualizado'));
        } catch (PDOException $e) {
            header('Location: leads-comerciales.php?error=' . urlencode('No se pudo actualizar'));
        }
        exit;
    }
    header('Location: leads-comerciales.php?error=' . urlencode('Datos inválidos'));
    exit;
}

// ─── Filtro de estado ────────────────────────────────────────────────────────
$estados_validos = array_merge($ESTADOS, ['todas']);
$estado_explicito = isset($_GET['estado']) && in_array($_GET['estado'], $estados_validos, true);
$filtro_estado = $estado_explicito ? $_GET['estado'] : 'nuevo';

// Si no eligió pestaña y no hay nuevos, abrir en "todas" (no mostrar vacío)
if (!$estado_explicito) {
    $hayNuevos = (int) $pdo->query("SELECT COUNT(*) FROM leads_comerciales WHERE estado = 'nuevo'")->fetchColumn();
    if ($hayNuevos === 0) $filtro_estado = 'todas';
}

$where  = $filtro_estado !== 'todas' ? "WHERE estado = :estado" : "";
$params = $filtro_estado !== 'todas' ? [':estado' => $filtro_estado] : [];

$stmt = $pdo->prepare("SELECT * FROM leads_comerciales $where ORDER BY created_at DESC");
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cont = $pdo->query("SELECT
    SUM(estado='nuevo')      AS nuevo,
    SUM(estado='en_proceso') AS en_proceso,
    SUM(estado='cerrado')    AS cerrado,
    SUM(estado='descartado') AS descartado,
    COUNT(*)                 AS total
    FROM leads_comerciales")->fetch(PDO::FETCH_ASSOC);

function leadPill(string $e): string {
    return match ($e) {
        'nuevo'      => 'admin-pill--alerta',
        'en_proceso' => 'admin-pill--marca',
        'cerrado'    => 'admin-pill--exito',
        'descartado' => 'admin-pill--error',
        default      => '',
    };
}
$ESTADO_LABEL = ['nuevo'=>'Nuevo','en_proceso'=>'En proceso','cerrado'=>'Cerrado','descartado'=>'Descartado'];

$titulo_pagina = 'Leads comerciales - Admin';
include 'header.php';
?>

<div class="admin-page admin-page--narrow">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Leads comerciales</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Solicitudes de "Promocionar mi crematorio" (B2B). Contactá, gestioná el estado y dejá notas internas.
            </p>
        </div>
    </header>

    <nav class="admin-tabs">
        <a href="?estado=nuevo" class="admin-tab<?php echo $filtro_estado==='nuevo'?' admin-tab--activo':''; ?>">
            Nuevos <span class="admin-tab__count"><?php echo (int)($cont['nuevo'] ?? 0); ?></span>
        </a>
        <a href="?estado=en_proceso" class="admin-tab<?php echo $filtro_estado==='en_proceso'?' admin-tab--activo':''; ?>">
            En proceso <span class="admin-tab__count"><?php echo (int)($cont['en_proceso'] ?? 0); ?></span>
        </a>
        <a href="?estado=cerrado" class="admin-tab<?php echo $filtro_estado==='cerrado'?' admin-tab--activo':''; ?>">
            Cerrados <span class="admin-tab__count"><?php echo (int)($cont['cerrado'] ?? 0); ?></span>
        </a>
        <a href="?estado=descartado" class="admin-tab<?php echo $filtro_estado==='descartado'?' admin-tab--activo':''; ?>">
            Descartados <span class="admin-tab__count"><?php echo (int)($cont['descartado'] ?? 0); ?></span>
        </a>
        <a href="?estado=todas" class="admin-tab<?php echo $filtro_estado==='todas'?' admin-tab--activo':''; ?>">
            Todas <span class="admin-tab__count"><?php echo (int)($cont['total'] ?? 0); ?></span>
        </a>
    </nav>

    <?php if (empty($leads)): ?>
        <div class="panel" style="text-align:center; color:var(--admin-tinta-suave);">
            <i data-lucide="inbox" style="width:32px;height:32px;"></i>
            <p style="margin:var(--espacio-dos) 0 0;">No hay leads en este estado.</p>
        </div>
    <?php else: foreach ($leads as $l): ?>
        <section class="panel" style="margin-bottom:var(--espacio-cuatro);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:var(--espacio-tres); flex-wrap:wrap;">
                <div>
                    <h2 class="panel__title" style="margin-bottom:var(--espacio-uno);">
                        <i data-lucide="megaphone" class="icono"></i>
                        <?php echo htmlspecialchars($l['nombre_negocio']); ?>
                    </h2>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);">
                        <?php echo htmlspecialchars($l['nombre']); ?>
                        <?php if (!empty($l['ciudad'])): ?> · <?php echo htmlspecialchars($l['ciudad']); ?><?php endif; ?>
                        · <?php echo date('d/m/Y H:i', strtotime($l['created_at'])); ?>
                        · <span style="text-transform:capitalize;"><?php echo htmlspecialchars($l['origen']); ?></span>
                    </div>
                </div>
                <span class="admin-pill <?php echo leadPill($l['estado']); ?>"><?php echo $ESTADO_LABEL[$l['estado']] ?? $l['estado']; ?></span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:var(--espacio-tres); margin-top:var(--espacio-tres);">
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Email</div>
                    <a href="mailto:<?php echo htmlspecialchars($l['email']); ?>" class="admin-link"><?php echo htmlspecialchars($l['email']); ?></a>
                </div>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Teléfono</div>
                    <a href="tel:<?php echo htmlspecialchars($l['telefono']); ?>" class="admin-link"><?php echo htmlspecialchars($l['telefono']); ?></a>
                </div>
            </div>

            <?php if (!empty($l['mensaje'])): ?>
            <div style="margin-top:var(--espacio-tres);">
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600; margin-bottom:.25rem;">Mensaje</div>
                <div style="background:var(--admin-papel-alt); border-radius:var(--admin-r-sm); padding:var(--espacio-tres); font-size:var(--admin-body-sm); line-height:1.55;">
                    <?php echo nl2br(htmlspecialchars($l['mensaje'])); ?>
                </div>
            </div>
            <?php endif; ?>

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
