<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LLAMADAS POR HACER — campaña de verificación de fichas
 * Negocios que han pedido desde verificar-ficha.php que les llamemos.
 * Resultado de cada llamada: verificado / no contesta / reagendar.
 * «Verificado» otorga «Verificado por humano» y, acumulativa, «Datos actualizados por el negocio».
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/insignias.php';

requerirAutenticacion();
requierePermiso('solicitudes');

$admin = obtenerAdminActual();
$pdo   = obtenerConexion();

$RESULTADOS = ['verificado', 'no_contesta', 'reagendar'];
$ESTADO_LABEL = [
    'pendiente'   => 'Por llamar',
    'reagendar'   => 'Reagendada',
    'no_contesta' => 'No contesta',
    'verificado'  => 'Verificada',
];

// ─── POST: registrar el resultado de la llamada (PRG) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int) ($_POST['llamada_id'] ?? 0);
    $resultado = in_array($_POST['resultado'] ?? '', $RESULTADOS, true) ? $_POST['resultado'] : null;
    $notas     = trim($_POST['notas_admin'] ?? '');
    $volver    = in_array($_POST['volver'] ?? '', ['por_hacer', 'verificada', 'todas'], true) ? $_POST['volver'] : 'por_hacer';

    if ($id && $resultado) {
        try {
            $st = $pdo->prepare("SELECT crematorio_id FROM solicitudes_llamada WHERE id = ?");
            $st->execute([$id]);
            $cremId = (int) $st->fetchColumn();
            if (!$cremId) throw new RuntimeException('Solicitud inexistente');

            $pdo->prepare("UPDATE solicitudes_llamada
                              SET estado = :e, notas_admin = :n, atendida_por = :a, resultado_en = NOW(),
                                  intentos = intentos + :inc
                            WHERE id = :id")
                ->execute([':e' => $resultado, ':inc' => $resultado === 'reagendar' ? 0 : 1,   // una llamada hecha cuenta como intento
                           ':n' => ($notas !== '' ? $notas : null), ':a' => (int) ($admin['id'] ?? 0) ?: null, ':id' => $id]);

            if ($resultado === 'verificado') {
                insigniaOtorgarVerificacion($pdo, $cremId, (int) ($admin['id'] ?? 0) ?: null);
                $msg = 'Llamada verificada: la ficha ya tiene «Verificado por humano» y «Datos actualizados por el negocio»';
            } else {
                $msg = $resultado === 'no_contesta' ? 'Anotado: no contesta' : 'Anotado: se reagenda';
            }
            header('Location: llamadas-pendientes.php?ver=' . $volver . '&ok=' . urlencode($msg));
        } catch (Throwable $e) {
            error_log('[llamadas-pendientes] ' . $e->getMessage());
            header('Location: llamadas-pendientes.php?ver=' . $volver . '&error=' . urlencode('No se pudo guardar el resultado'));
        }
        exit;
    }
    header('Location: llamadas-pendientes.php?error=' . urlencode('Datos inválidos'));
    exit;
}

// ─── Filtro ──────────────────────────────────────────────────────────────────
$ver = in_array($_GET['ver'] ?? '', ['por_hacer', 'verificada', 'todas'], true) ? $_GET['ver'] : 'por_hacer';
$where = match ($ver) {
    'por_hacer'  => "WHERE l.estado IN ('pendiente','reagendar','no_contesta')",
    'verificada' => "WHERE l.estado = 'verificado'",
    default      => '',
};

$llamadas = $pdo->query("SELECT l.*, c.nombre AS ficha_nombre, c.slug AS ficha_slug
                           FROM solicitudes_llamada l
                           JOIN crematorios c ON c.id = l.crematorio_id
                           $where
                          ORDER BY (l.estado = 'verificado'), l.created_at ASC
                          LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$cont = $pdo->query("SELECT
    SUM(estado IN ('pendiente','reagendar','no_contesta')) AS por_hacer,
    SUM(estado = 'verificado') AS verificada,
    COUNT(*) AS todas
    FROM solicitudes_llamada")->fetch(PDO::FETCH_ASSOC);

function llamadaPill(string $e): string {
    return match ($e) {
        'pendiente'   => 'admin-pill--alerta',
        'reagendar'   => 'admin-pill--marca',
        'no_contesta' => 'admin-pill--error',
        'verificado'  => 'admin-pill--exito',
        default       => '',
    };
}

$titulo_pagina = 'Llamadas por hacer - Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Llamadas por hacer</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Negocios que han pedido que les llamemos para verificar su ficha (campaña de verificación). Llama en horario comercial, de lunes a viernes, y anota el resultado.
            </p>
        </div>
    </header>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-uno);"><?php echo htmlspecialchars($_GET['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-siete);"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <nav class="admin-tabs">
        <?php foreach (['por_hacer' => 'Por hacer', 'verificada' => 'Verificadas', 'todas' => 'Todas'] as $k => $lbl): ?>
        <a href="?ver=<?php echo $k; ?>" class="admin-tab<?php echo $ver === $k ? ' admin-tab--activo' : ''; ?>">
            <?php echo $lbl; ?> <span class="admin-tab__count"><?php echo (int) ($cont[$k] ?? 0); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($llamadas)): ?>
        <div class="panel" style="text-align:center; color:var(--admin-tinta-suave);">
            <i data-lucide="phone-off" style="width:32px;height:32px;"></i>
            <p style="margin:var(--espacio-dos) 0 0;">No hay llamadas en este filtro.</p>
        </div>
    <?php else: foreach ($llamadas as $l):
        $tel = preg_replace('/[^0-9+]/', '', $l['telefono_confirmado']);
    ?>
        <section class="panel llamada" style="margin-bottom:var(--espacio-cuatro);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:var(--espacio-tres); flex-wrap:wrap;">
                <div style="min-width:0; flex:1;">
                    <h2 class="panel__title" style="margin-bottom:var(--espacio-uno);">
                        <i data-lucide="phone-call" class="icono"></i>
                        <a href="editar-ficha-negocio.php?id=<?php echo (int) $l['crematorio_id']; ?>" class="admin-link"><?php echo htmlspecialchars($l['ficha_nombre']); ?></a>
                    </h2>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
                        <span>Pedida el <?php echo date('d/m/Y H:i', strtotime($l['created_at'])); ?></span>
                        <span>·</span>
                        <a href="<?php echo htmlspecialchars(BASE_URL . '/' . $l['ficha_slug']); ?>" target="_blank" rel="noopener" class="admin-link">Ver la ficha pública</a>
                    </div>
                </div>
                <span class="admin-pill <?php echo llamadaPill($l['estado']); ?>"><?php echo $ESTADO_LABEL[$l['estado']] ?? $l['estado']; ?></span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--espacio-tres); margin-top:var(--espacio-tres);">
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Teléfono al que llamar</div>
                    <a href="tel:<?php echo htmlspecialchars($tel); ?>" class="admin-link" style="font-weight:700;"><?php echo htmlspecialchars($l['telefono_confirmado']); ?></a>
                    <?php if ($l['telefono_cambiado']): ?>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tone-alerta-fg); margin-top:.15rem;">
                        Cambiado por el negocio (ya es el público de la ficha). Antes: <?php echo htmlspecialchars($l['telefono_original'] ?: 'ninguno'); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Intentos</div>
                    <?php echo (int) $l['intentos']; ?>
                    <?php if ($l['resultado_en']): ?>
                    <span style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave);"> · último resultado <?php echo date('d/m/Y H:i', strtotime($l['resultado_en'])); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" style="margin-top:var(--espacio-cuatro); border-top:1px solid var(--admin-linea); padding-top:var(--espacio-tres); display:grid; gap:var(--espacio-tres);">
                <input type="hidden" name="llamada_id" value="<?php echo (int) $l['id']; ?>">
                <input type="hidden" name="volver" value="<?php echo htmlspecialchars($ver); ?>">
                <div class="field" style="margin-bottom:0;">
                    <label class="field__label">Notas internas</label>
                    <textarea name="notas_admin" class="field__textarea" rows="2" placeholder="Qué se habló, qué datos se corrigieron, cuándo volver a llamar…"><?php echo htmlspecialchars($l['notas_admin'] ?? ''); ?></textarea>
                </div>
                <div style="display:flex; gap:var(--espacio-dos); flex-wrap:wrap;">
                    <button type="submit" name="resultado" value="verificado" class="boton uno pequeno"
                            onclick="return confirm('¿Marcar como verificado? La ficha recibirá las insignias «Verificado por humano» y «Datos actualizados por el negocio».');">
                        <i data-lucide="badge-check" class="icono"></i> Verificado
                    </button>
                    <button type="submit" name="resultado" value="no_contesta" class="boton dos pequeno">
                        <i data-lucide="phone-missed" class="icono"></i> No contesta
                    </button>
                    <button type="submit" name="resultado" value="reagendar" class="boton dos pequeno">
                        <i data-lucide="calendar-clock" class="icono"></i> Reagendar
                    </button>
                </div>
            </form>
        </section>
    <?php endforeach; endif; ?>

</div>

<?php include 'footer.php'; ?>
