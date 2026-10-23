<?php
/**
 * ═══════════════════════════════════════════════════════════
 * TELÉFONOS REPORTADOS — avisos de visitantes («¿No funciona este teléfono?»)
 * Cada aviso se revisa a mano: mirar la ficha, llamar si hace falta y marcarlo como revisado.
 * El aviso NO cambia la ficha por sí solo.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('solicitudes');

$admin = obtenerAdminActual();
$pdo   = obtenerConexion();

$MOTIVOS = ['no_contesta' => 'No contesta', 'numero_equivocado' => 'Número equivocado', 'otro' => 'Otro'];

// ─── POST: marcar como revisado / volver a pendiente (PRG) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['reporte_id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $notas  = trim($_POST['notas_admin'] ?? '');
    $volver = in_array($_POST['volver'] ?? '', ['pendiente', 'revisado', 'todos'], true) ? $_POST['volver'] : 'pendiente';

    if ($id && in_array($accion, ['revisar', 'reabrir'], true)) {
        try {
            if ($accion === 'revisar') {
                $pdo->prepare("UPDATE telefono_reportes
                                  SET estado = 'revisado', notas_admin = :n, atendida_por = :a, resuelto_en = NOW()
                                WHERE id = :id")
                    ->execute([':n' => ($notas !== '' ? $notas : null), ':a' => (int) ($admin['id'] ?? 0) ?: null, ':id' => $id]);
                $msg = 'Aviso marcado como revisado';
            } else {
                $pdo->prepare("UPDATE telefono_reportes SET estado = 'pendiente', resuelto_en = NULL WHERE id = ?")->execute([$id]);
                $msg = 'Aviso devuelto a pendientes';
            }
            header('Location: telefonos-reportados.php?ver=' . $volver . '&ok=' . urlencode($msg));
        } catch (Throwable $e) {
            error_log('[telefonos-reportados] ' . $e->getMessage());
            header('Location: telefonos-reportados.php?ver=' . $volver . '&error=' . urlencode('No se pudo guardar'));
        }
        exit;
    }
    header('Location: telefonos-reportados.php?error=' . urlencode('Datos inválidos'));
    exit;
}

// ─── Filtro ──────────────────────────────────────────────────────────────────
$ver = in_array($_GET['ver'] ?? '', ['pendiente', 'revisado', 'todos'], true) ? $_GET['ver'] : 'pendiente';
$where = match ($ver) {
    'pendiente' => "WHERE t.estado = 'pendiente'",
    'revisado'  => "WHERE t.estado = 'revisado'",
    default     => '',
};

$avisos = [];
$cont   = ['pendiente' => 0, 'revisado' => 0, 'todos' => 0];
$migracionFalta = false;
try {
    $avisos = $pdo->query("SELECT t.*, c.nombre AS ficha_nombre, c.slug AS ficha_slug
                             FROM telefono_reportes t
                             JOIN crematorios c ON c.id = t.crematorio_id
                             $where
                            ORDER BY (t.estado = 'revisado'), t.created_at DESC
                            LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    $c = $pdo->query("SELECT SUM(estado = 'pendiente') AS pendiente, SUM(estado = 'revisado') AS revisado, COUNT(*) AS todos
                        FROM telefono_reportes")->fetch(PDO::FETCH_ASSOC);
    $cont = array_map('intval', $c);
} catch (PDOException $e) {
    $migracionFalta = true;
}

$titulo_pagina = 'Teléfonos reportados - Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Teléfonos reportados</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Visitantes que han avisado de que el teléfono de una ficha no funciona. Revisa la ficha, llama si hace falta, corrige el número y marca el aviso como revisado. El aviso no cambia nada por sí solo.
            </p>
        </div>
    </header>

    <?php if ($migracionFalta): ?>
    <div class="panel" style="border-left:3px solid var(--color-siete);">Falta ejecutar la migración <code>admin/migrations/create_telefono_reportes.sql</code>.</div>
    <?php else: ?>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-uno);"><?php echo htmlspecialchars($_GET['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-siete);"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <nav class="admin-tabs">
        <?php foreach (['pendiente' => 'Pendientes', 'revisado' => 'Revisados', 'todos' => 'Todos'] as $k => $lbl): ?>
        <a href="?ver=<?php echo $k; ?>" class="admin-tab<?php echo $ver === $k ? ' admin-tab--activo' : ''; ?>">
            <?php echo $lbl; ?> <span class="admin-tab__count"><?php echo (int) ($cont[$k] ?? 0); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($avisos)): ?>
        <div class="panel" style="text-align:center; color:var(--admin-tinta-suave);">
            <i data-lucide="phone" style="width:32px;height:32px;"></i>
            <p style="margin:var(--espacio-dos) 0 0;">No hay avisos en este filtro.</p>
        </div>
    <?php else: foreach ($avisos as $a): ?>
        <section class="panel" style="margin-bottom:var(--espacio-cuatro);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:var(--espacio-tres); flex-wrap:wrap;">
                <div style="min-width:0; flex:1;">
                    <h2 class="panel__title" style="margin-bottom:var(--espacio-uno);">
                        <i data-lucide="phone-off" class="icono"></i>
                        <a href="editar-ficha-negocio.php?id=<?php echo (int) $a['crematorio_id']; ?>" class="admin-link"><?php echo htmlspecialchars($a['ficha_nombre']); ?></a>
                    </h2>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
                        <span>Aviso del <?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></span>
                        <span>·</span>
                        <a href="<?php echo htmlspecialchars(BASE_URL . '/' . $a['ficha_slug']); ?>" target="_blank" rel="noopener" class="admin-link">Ver la ficha pública</a>
                    </div>
                </div>
                <span class="admin-pill <?php echo $a['estado'] === 'pendiente' ? 'admin-pill--alerta' : 'admin-pill--exito'; ?>">
                    <?php echo $a['estado'] === 'pendiente' ? 'Por revisar' : 'Revisado'; ?>
                </span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--espacio-tres); margin-top:var(--espacio-tres);">
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Teléfono reportado</div>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $a['telefono'])); ?>" class="admin-link" style="font-weight:700;"><?php echo htmlspecialchars($a['telefono']); ?></a>
                </div>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Qué pasó</div>
                    <?php echo htmlspecialchars($MOTIVOS[$a['motivo'] ?? ''] ?? 'No indicado'); ?>
                </div>
                <?php if ($a['resuelto_en']): ?>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Revisado el</div>
                    <?php echo date('d/m/Y H:i', strtotime($a['resuelto_en'])); ?>
                </div>
                <?php endif; ?>
            </div>

            <form method="POST" style="margin-top:var(--espacio-cuatro); border-top:1px solid var(--admin-linea); padding-top:var(--espacio-tres); display:grid; gap:var(--espacio-tres);">
                <input type="hidden" name="reporte_id" value="<?php echo (int) $a['id']; ?>">
                <input type="hidden" name="volver" value="<?php echo htmlspecialchars($ver); ?>">
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label">Notas internas</label>
                    <textarea name="notas_admin" class="field__textarea" rows="2" placeholder="Qué se comprobó, si se llamó, qué número es el correcto…"><?php echo htmlspecialchars($a['notas_admin'] ?? ''); ?></textarea>
                </div>
                <div style="display:flex; gap:var(--espacio-dos); flex-wrap:wrap;">
                    <?php if ($a['estado'] === 'pendiente'): ?>
                    <button type="submit" name="accion" value="revisar" class="boton uno pequeno">
                        <i data-lucide="check" class="icono"></i> Marcar como revisado
                    </button>
                    <?php else: ?>
                    <button type="submit" name="accion" value="reabrir" class="boton dos pequeno">
                        <i data-lucide="rotate-ccw" class="icono"></i> Volver a pendientes
                    </button>
                    <?php endif; ?>
                    <a href="editar-ficha-negocio.php?id=<?php echo (int) $a['crematorio_id']; ?>" class="boton dos pequeno">
                        <i data-lucide="pencil" class="icono"></i> Editar la ficha
                    </a>
                </div>
            </form>
        </section>
    <?php endforeach; endif; ?>

    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
