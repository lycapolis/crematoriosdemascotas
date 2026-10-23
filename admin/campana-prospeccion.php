<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CAMPAÑA DE PROSPECCIÓN — estado, pausa y simulación
 * ═══════════════════════════════════════════════════════════
 * Interruptor de pausa del motor (cron/enviar-campana-prospeccion.php), simulación de «a quién se
 * escribiría hoy» (sin enviar nada), métricas y estado de la campaña ficha por ficha.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/campana-prospeccion.php';

requerirAutenticacion();
requierePermiso('solicitudes');

$admin = obtenerAdminActual();
$pdo   = obtenerConexion();

// ─── POST: pausar / reanudar / cambiar el tope (PRG) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCSRF((string) ($_POST['csrf_token'] ?? ''))) {
        header('Location: campana-prospeccion.php?error=' . urlencode('Sesión caducada, inténtalo de nuevo'));
        exit;
    }
    try {
        $accion = (string) ($_POST['accion'] ?? '');
        if ($accion === 'pausar')   { campanaFijarConfig($pdo, 'pausada', '1'); $msg = 'Campaña en pausa: el cron no enviará nada'; }
        elseif ($accion === 'reanudar') { campanaFijarConfig($pdo, 'pausada', '0'); $msg = 'Campaña ACTIVA: el cron enviará dentro del horario'; }
        elseif ($accion === 'tope') {
            $t = max(1, min(50, (int) ($_POST['tope'] ?? 10)));
            campanaFijarConfig($pdo, 'tope_diario', (string) $t);
            $msg = "Tope diario: {$t}";
        } else { throw new RuntimeException('Acción desconocida'); }
        header('Location: campana-prospeccion.php?ok=' . urlencode($msg));
    } catch (Throwable $e) {
        error_log('[campana-prospeccion] ' . $e->getMessage());
        header('Location: campana-prospeccion.php?error=' . urlencode('No se pudo guardar (¿falta correr la migración create_campana_motor.sql?)'));
    }
    exit;
}

// ─── Datos ───────────────────────────────────────────────────────────────────
$migrado = true;
try { $pdo->query("SELECT 1 FROM campana_config LIMIT 1"); } catch (Throwable $e) { $migrado = false; }

$pausada   = campanaPausada($pdo);
$tope      = campanaTope($pdo);
$ahoraEs   = campanaAhoraEspana();
$enVentana = campanaEnVentana($ahoraEs);
$hoy       = $migrado ? campanaEnviadosHoy($pdo, false) : 0;
$quedan    = max(0, $tope - $hoy);

$candidatas = $migrado ? campanaCandidatas($pdo, false) : [];
$simulacion = array_slice($candidatas, 0, $quedan);

$m = $migrado ? campanaMetricas($pdo) : ['estados' => [], 'envios' => [1 => 0, 2 => 0, 3 => 0], 'clics' => ['llamada' => 0, 'formulario' => 0]];
$totalActivas = (int) $pdo->query("SELECT COUNT(*) FROM crematorios WHERE estado = 'activa'")->fetchColumn();
$conEmail = 0; $conBajaEmail = 0;
foreach ($pdo->query("SELECT id, email, email_clientes, emails_json FROM crematorios WHERE estado = 'activa'")->fetchAll(PDO::FETCH_ASSOC) as $f) {
    if (!campanaEmailsFicha($f)) continue;
    $conEmail++;
    if (campanaFichaSuprimida($pdo, $f)) $conBajaEmail++;   // baja desde el pie del email o «No me interesa»
}

$ESTADO_LABEL = [
    'sin_enviar' => 'Sin enviar', 'enviado_1' => 'Invitación enviada', 'enviado_2' => 'Recordatorio enviado',
    'enviado_3' => 'Último aviso enviado', 'respondio' => 'Respondió', 'llamada' => 'Pidió llamada',
    'formulario' => 'Envió formulario', 'baja' => 'Baja',
];
$estadoPill = fn(string $e) => match ($e) {
    'llamada', 'formulario', 'respondio' => 'admin-pill--exito',
    'baja' => 'admin-pill--error',
    'enviado_1', 'enviado_2', 'enviado_3' => 'admin-pill--marca',
    default => '',
};
$ETQ_PASO = [1 => 'Invitación', 2 => 'Recordatorio', 3 => 'Último aviso'];

$filas = [];
if ($migrado) {
    $filas = $pdo->query("SELECT c.id, c.nombre, c.estado AS ficha_estado, c.email, c.email_clientes, c.emails_json, c.verificado,
                                 COALESCE(cf.estado, 'sin_enviar') AS campana_estado, cf.ultimo_envio,
                                 (SELECT COUNT(*) FROM campana_envios e WHERE e.crematorio_id = c.id AND e.prueba = 0) AS n_envios,
                                 (SELECT COUNT(*) FROM campana_clics k WHERE k.crematorio_id = c.id AND k.boton = 'llamada') AS clics_llamada,
                                 (SELECT COUNT(*) FROM campana_clics k WHERE k.crematorio_id = c.id AND k.boton = 'formulario') AS clics_formulario
                            FROM crematorios c
                       LEFT JOIN campana_fichas cf ON cf.crematorio_id = c.id
                           WHERE c.estado IN ('activa','demo')
                        ORDER BY c.estado = 'demo', FIELD(COALESCE(cf.estado,'sin_enviar'),'llamada','formulario','respondio','enviado_3','enviado_2','enviado_1','sin_enviar','baja'), c.nombre
                           LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
}

$titulo_pagina = 'Campaña de prospección - Admin';
include 'header.php';
?>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Campaña de prospección</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Emails a los negocios para que verifiquen su ficha (invitación → recordatorio a los 7 días → último aviso 14 días después).
                Los envía <code>cron/enviar-campana-prospeccion.php</code>: lunes a viernes, de 9:00 a 14:00 (hora de España), con tope diario.
            </p>
        </div>
    </header>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-uno);"><?php echo htmlspecialchars($_GET['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-siete);"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>
    <?php if (!$migrado): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-siete);">
        Falta correr la migración <code>admin/migrations/create_campana_motor.sql</code>. Mientras tanto la campaña se considera en pausa.
    </div>
    <?php endif; ?>

    <section class="panel campana-control" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="panel__title"><i data-lucide="power" class="icono"></i> Interruptor</h2>
        <div style="display:flex; gap:var(--espacio-cuatro); align-items:center; flex-wrap:wrap;">
            <span class="admin-pill <?php echo $pausada ? 'admin-pill--error' : 'admin-pill--exito'; ?>">
                <?php echo $pausada ? 'EN PAUSA — no se envía nada' : 'ACTIVA'; ?>
            </span>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar(generarTokenCSRF()); ?>">
                <?php if ($pausada): ?>
                <button type="submit" name="accion" value="reanudar" class="boton uno pequeno" <?php echo $migrado ? '' : 'disabled'; ?>
                        onclick="return confirm('¿Activar la campaña? El cron empezará a enviar emails REALES a los negocios dentro del horario.');">
                    <i data-lucide="play" class="icono"></i> Activar campaña
                </button>
                <?php else: ?>
                <button type="submit" name="accion" value="pausar" class="boton dos pequeno">
                    <i data-lucide="pause" class="icono"></i> Pausar campaña
                </button>
                <?php endif; ?>
            </form>
            <form method="POST" style="display:flex; gap:var(--espacio-dos); align-items:center;">
                <input type="hidden" name="csrf_token" value="<?php echo limpiar(generarTokenCSRF()); ?>">
                <label for="campana-tope" class="field__label" style="margin:0;">Tope diario</label>
                <input type="number" id="campana-tope" name="tope" class="field__input" min="1" max="50" value="<?php echo (int) $tope; ?>" style="width:5rem;">
                <button type="submit" name="accion" value="tope" class="boton dos pequeno" <?php echo $migrado ? '' : 'disabled'; ?>>Guardar</button>
            </form>
        </div>
        <p class="admin-page-subtitle" style="margin-top:var(--espacio-tres);">
            Ahora en España: <?php echo $ahoraEs->format('d/m/Y H:i'); ?> ·
            <?php echo $enVentana ? 'dentro del horario de envío' : 'fuera del horario de envío (lunes-viernes, 9-14 h)'; ?> ·
            enviados hoy: <?php echo (int) $hoy; ?> de <?php echo (int) $tope; ?>.
        </p>
    </section>

    <section class="panel campana-simulacion" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="panel__title"><i data-lucide="eye" class="icono"></i> Simulación: a quién se escribiría hoy</h2>
        <p class="admin-page-subtitle">
            No envía nada. Muestra los próximos envíos según el tope que queda hoy (<?php echo (int) $quedan; ?>).
            Quedan en total <?php echo count($candidatas); ?> ficha(s) con algo pendiente de enviar.
            Solo cuentan fichas activas, con email válido, sin baja, sin respuesta y no verificadas.
        </p>
        <?php if (!$simulacion): ?>
            <p style="color:var(--admin-tinta-suave);">Hoy no hay nada que enviar.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Ficha</th><th>Email</th><th>Qué se enviaría</th></tr></thead>
            <tbody>
            <?php foreach ($simulacion as $f): ?>
                <tr>
                    <td><a href="editar-ficha-negocio.php?id=<?php echo (int) $f['id']; ?>" class="admin-link"><?php echo htmlspecialchars($f['nombre']); ?></a></td>
                    <td><?php echo htmlspecialchars($f['email']); ?></td>
                    <td>Email <?php echo (int) $f['paso']; ?> · <?php echo $ETQ_PASO[$f['paso']]; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="panel campana-metricas" style="margin-bottom:var(--espacio-cuatro);">
        <h2 class="panel__title"><i data-lucide="bar-chart-3" class="icono"></i> Métricas (fichas activas)</h2>
        <?php
        $respondieron = ($m['estados']['respondio'] ?? 0) + ($m['estados']['llamada'] ?? 0) + ($m['estados']['formulario'] ?? 0);
        $tarjetas = [
            ['Fichas activas', $totalActivas . ' (' . $conEmail . ' con email)'],
            ['Invitaciones enviadas', $m['envios'][1]],
            ['Recordatorios enviados', $m['envios'][2]],
            ['Últimos avisos enviados', $m['envios'][3]],
            ['Respondieron', $respondieron],
            ['Pidieron llamada', $m['estados']['llamada'] ?? 0],
            ['Enviaron formulario', $m['estados']['formulario'] ?? 0],
            ['Bajas (No me interesa o enlace de baja del email)', max($conBajaEmail, $m['estados']['baja'] ?? 0)],
            ['Clics «Quiero que me llamen»', $m['clics']['llamada']],
            ['Clics «Completarlo yo»', $m['clics']['formulario']],
        ];
        ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:var(--espacio-tres);">
            <?php foreach ($tarjetas as [$etq, $val]): ?>
            <div class="campana-metricas__item">
                <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;"><?php echo htmlspecialchars($etq); ?></div>
                <div style="font-size:1.4rem; font-weight:700;"><?php echo htmlspecialchars((string) $val); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="admin-page-subtitle" style="margin-top:var(--espacio-tres);">Los clics incluyen también los de las pruebas con fichas demo y las visitas repetidas al mismo botón.</p>
    </section>

    <section class="panel campana-fichas">
        <h2 class="panel__title"><i data-lucide="list" class="icono"></i> Estado por ficha</h2>
        <table class="admin-table">
            <thead><tr><th>Ficha</th><th>Email</th><th>Estado</th><th>Último envío</th><th>Envíos</th><th>Clics llamada / formulario</th></tr></thead>
            <tbody>
            <?php foreach ($filas as $f):
                $tieneEmail = (bool) campanaEmailsFicha($f); ?>
                <tr>
                    <td>
                        <a href="editar-ficha-negocio.php?id=<?php echo (int) $f['id']; ?>" class="admin-link"><?php echo htmlspecialchars($f['nombre']); ?></a>
                        <?php if ($f['ficha_estado'] === 'demo'): ?><span class="admin-pill">demo</span><?php endif; ?>
                        <?php if ((int) $f['verificado'] === 1): ?><span class="admin-pill admin-pill--exito">verificada</span><?php endif; ?>
                    </td>
                    <td><?php echo $tieneEmail ? 'Sí' : '<span style="color:var(--admin-tinta-suave);">No (llamada en frío)</span>'; ?></td>
                    <td><span class="admin-pill <?php echo $estadoPill($f['campana_estado']); ?>"><?php echo $ESTADO_LABEL[$f['campana_estado']] ?? $f['campana_estado']; ?></span></td>
                    <td><?php echo $f['ultimo_envio'] ? date('d/m/Y H:i', strtotime($f['ultimo_envio'])) : '—'; ?></td>
                    <td><?php echo (int) $f['n_envios']; ?></td>
                    <td><?php echo (int) $f['clics_llamada']; ?> / <?php echo (int) $f['clics_formulario']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>

</div>

<?php include 'footer.php'; ?>
