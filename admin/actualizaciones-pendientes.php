<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ACTUALIZACIONES PENDIENTES — campaña de verificación de fichas
 * Datos que los negocios proponen desde verificar-ficha.php. Nada se publica solo:
 * aquí se compara «actual» vs. «propuesto» y se aprueba o rechaza campo a campo.
 *
 * Al aprobar: el dato pasa a la ficha pública (servicios, horarios y precios se archivan como
 * texto original para estructurarlos con la IA del editor) y la ficha recibe la insignia
 * «Datos actualizados por el negocio». Después se avisa al negocio por email.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/insignias.php';
require_once dirname(__DIR__) . '/includes/notificaciones/despachador.php';
require_once dirname(__DIR__) . '/includes/actualizacion-ficha.php';

requerirAutenticacion();
requierePermiso('solicitudes');

$admin   = obtenerAdminActual();
$adminId = (int) ($admin['id'] ?? 0) ?: null;
$pdo     = obtenerConexion();
$CAT     = actFichaCatalogo();

// ─── POST: registrar la revisión (PRG) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['actualizacion_id'] ?? 0);
    $volver = in_array($_POST['volver'] ?? '', ['pendientes', 'revisadas', 'todas'], true) ? $_POST['volver'] : 'pendientes';
    $decCampos = is_array($_POST['campo'] ?? null) ? $_POST['campo'] : [];
    $decImgs   = is_array($_POST['imagen'] ?? null) ? $_POST['imagen'] : [];
    $mensaje   = trim((string) ($_POST['mensaje_admin'] ?? ''));

    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT * FROM actualizaciones_ficha WHERE id = ? FOR UPDATE");
        $st->execute([$id]);
        $act = $st->fetch(PDO::FETCH_ASSOC);
        if (!$act || $act['estado'] !== 'pendiente') throw new RuntimeException('Esta actualización ya no está pendiente.');
        $cremId = (int) $act['crematorio_id'];

        $sl = $pdo->prepare("SELECT slug FROM crematorios WHERE id = ?");
        $sl->execute([$cremId]);
        $slug = (string) $sl->fetchColumn();

        $campos = $pdo->prepare("SELECT * FROM actualizacion_ficha_campos WHERE actualizacion_id = ?");
        $campos->execute([$id]);
        $imgs = $pdo->prepare("SELECT * FROM actualizacion_ficha_imagenes WHERE actualizacion_id = ?");
        $imgs->execute([$id]);

        $aprobados = 0; $notas = [];
        $updCampo = $pdo->prepare("UPDATE actualizacion_ficha_campos SET estado = ?, decidido_en = NOW() WHERE id = ?");
        foreach ($campos->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $d = $decCampos[$c['campo']] ?? '';
            if (!in_array($d, ['aprobar', 'rechazar'], true)) throw new RuntimeException('Decide cada campo (aprobar o rechazar) antes de guardar.');
            if ($d === 'aprobar') {
                $nota = actFichaAplicarCampo($pdo, $cremId, $c['campo'], (string) $c['valor_propuesto']);
                if ($nota !== '') $notas[] = ($CAT[$c['campo']][0] ?? $c['campo']) . ': ' . $nota;
                $aprobados++;
            }
            $updCampo->execute([$d === 'aprobar' ? 'aprobado' : 'rechazado', $c['id']]);
        }
        $updImg = $pdo->prepare("UPDATE actualizacion_ficha_imagenes SET estado = ?, decidido_en = NOW() WHERE id = ?");
        foreach ($imgs->fetchAll(PDO::FETCH_ASSOC) as $im) {
            $d = $decImgs[$im['id']] ?? '';
            if (!in_array($d, ['aprobar', 'rechazar'], true)) throw new RuntimeException('Decide cada imagen (aprobar o rechazar) antes de guardar.');
            if ($d === 'aprobar') {
                if (!actFichaAplicarImagen($pdo, $cremId, $slug, $im, $id)) throw new RuntimeException('No se pudo copiar una imagen a la ficha.');
                $aprobados++;
            }
            $updImg->execute([$d === 'aprobar' ? 'aprobada' : 'rechazada', $im['id']]);
        }

        if ($aprobados > 0) insigniaFijarManual($pdo, $cremId, 'datos_actualizados', true, $adminId);

        $pdo->prepare("UPDATE actualizaciones_ficha SET estado = 'revisada', mensaje_admin = ?, revisada_por = ?, revisada_en = NOW() WHERE id = ?")
            ->execute([$mensaje !== '' ? $mensaje : null, $adminId, $id]);
        $pdo->commit();

        notificarEvento($pdo, 'ficha.actualizacion_revisada', ['actualizacion_id' => $id]);

        $msg = $aprobados > 0
            ? 'Revisión guardada: lo aprobado ya está en la ficha pública y tiene «Datos actualizados por el negocio». Se ha avisado al negocio.'
            : 'Revisión guardada: no se ha cambiado nada en la ficha. Se ha avisado al negocio.';
        if ($notas) $msg .= ' · ' . implode(' · ', $notas);
        header('Location: actualizaciones-pendientes.php?ver=' . $volver . '&ok=' . urlencode($msg));
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[actualizaciones-pendientes] ' . $e->getMessage());
        header('Location: actualizaciones-pendientes.php?ver=' . $volver . '&error=' . urlencode($e->getMessage()));
    }
    exit;
}

// ─── Filtro ──────────────────────────────────────────────────────────────────
$ver = in_array($_GET['ver'] ?? '', ['pendientes', 'revisadas', 'todas'], true) ? $_GET['ver'] : 'pendientes';
$where = match ($ver) {
    'pendientes' => "WHERE a.estado = 'pendiente'",
    'revisadas'  => "WHERE a.estado = 'revisada'",
    default      => '',
};

$actualizaciones = $pdo->query("SELECT a.*, c.nombre AS ficha_nombre, c.slug AS ficha_slug
                                  FROM actualizaciones_ficha a
                                  JOIN crematorios c ON c.id = a.crematorio_id
                                  $where
                                 ORDER BY (a.estado = 'revisada'), a.created_at ASC
                                 LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$cont = $pdo->query("SELECT SUM(estado = 'pendiente') AS pendientes, SUM(estado = 'revisada') AS revisadas, COUNT(*) AS todas
                       FROM actualizaciones_ficha")->fetch(PDO::FETCH_ASSOC);

$camposDe = $pdo->prepare("SELECT * FROM actualizacion_ficha_campos WHERE actualizacion_id = ? ORDER BY id");
$imgsDe   = $pdo->prepare("SELECT * FROM actualizacion_ficha_imagenes WHERE actualizacion_id = ? ORDER BY tipo, orden, id");

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
// Orden de los campos según el catálogo
$ordenCampos = array_flip(array_keys($CAT));

$titulo_pagina = 'Actualizaciones pendientes - Admin';
include 'header.php';
?>

<style>
    .act-tabla { width: 100%; border-collapse: collapse; margin-top: var(--espacio-tres); }
    .act-tabla th { text-align: left; font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); padding: .35rem .6rem; border-bottom: 1px solid var(--admin-linea); }
    .act-tabla td { vertical-align: top; padding: .6rem; border-bottom: 1px solid var(--admin-linea); }
    .act-tabla__campo { font-weight: 600; white-space: nowrap; }
    .act-valor { white-space: pre-wrap; word-break: break-word; max-width: 34ch; }
    .act-valor--vacio { color: var(--admin-tinta-suave); font-style: italic; }
    .act-valor--nuevo { background: var(--admin-tone-exito-bg, #ecf8ef); border-radius: 4px; padding: .15rem .35rem; }
    .act-decision { display: flex; flex-direction: column; gap: .25rem; white-space: nowrap; }
    .act-imagenes { display: flex; flex-wrap: wrap; gap: var(--espacio-tres); margin-top: var(--espacio-tres); }
    .act-imagen { width: 150px; display: flex; flex-direction: column; gap: .35rem; font-size: var(--admin-body-sm); }
    .act-imagen img { width: 150px; height: 110px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-linea); }
    .act-atajos { display: flex; gap: var(--espacio-dos); flex-wrap: wrap; margin-top: var(--espacio-tres); }
    @media (max-width: 720px) { .act-tabla thead { display: none; } .act-tabla tr { display: block; border-bottom: 1px solid var(--admin-linea); padding: .4rem 0; } .act-tabla td { display: block; border: 0; padding: .2rem .4rem; } .act-valor { max-width: none; } }
</style>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Actualizaciones pendientes</h1>
            <p class="admin-page-subtitle admin-page-subtitle--big">
                Datos que los negocios proponen para su ficha desde la página de verificación. Compara lo actual con lo propuesto y aprueba o rechaza cada campo: solo lo aprobado llega a la ficha pública.
            </p>
        </div>
    </header>

    <?php if (!empty($_GET['ok'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-uno);"><?php echo $h($_GET['ok']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
    <div class="panel" style="margin-bottom:var(--espacio-cuatro); border-left:3px solid var(--color-siete);"><?php echo $h($_GET['error']); ?></div>
    <?php endif; ?>

    <nav class="admin-tabs">
        <?php foreach (['pendientes' => 'Pendientes', 'revisadas' => 'Revisadas', 'todas' => 'Todas'] as $k => $lbl): ?>
        <a href="?ver=<?php echo $k; ?>" class="admin-tab<?php echo $ver === $k ? ' admin-tab--activo' : ''; ?>">
            <?php echo $lbl; ?> <span class="admin-tab__count"><?php echo (int) ($cont[$k] ?? 0); ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($actualizaciones)): ?>
        <div class="panel" style="text-align:center; color:var(--admin-tinta-suave);">
            <i data-lucide="file-check" style="width:32px;height:32px;"></i>
            <p style="margin:var(--espacio-dos) 0 0;">No hay actualizaciones en este filtro.</p>
        </div>
    <?php else: foreach ($actualizaciones as $a):
        $camposDe->execute([$a['id']]);
        $campos = $camposDe->fetchAll(PDO::FETCH_ASSOC);
        usort($campos, fn($x, $y) => ($ordenCampos[$x['campo']] ?? 99) <=> ($ordenCampos[$y['campo']] ?? 99));
        $imgsDe->execute([$a['id']]);
        $imagenes  = $imgsDe->fetchAll(PDO::FETCH_ASSOC);
        $pendiente = $a['estado'] === 'pendiente';
    ?>
        <section class="panel actualizacion" style="margin-bottom:var(--espacio-cuatro);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:var(--espacio-tres); flex-wrap:wrap;">
                <div style="min-width:0; flex:1;">
                    <h2 class="panel__title" style="margin-bottom:var(--espacio-uno);">
                        <i data-lucide="file-pen-line" class="icono"></i>
                        <a href="editar-ficha-negocio.php?id=<?php echo (int) $a['crematorio_id']; ?>" class="admin-link"><?php echo $h($a['ficha_nombre']); ?></a>
                    </h2>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); display:flex; gap:.5rem; flex-wrap:wrap; align-items:center;">
                        <span>Enviada el <?php echo date('d/m/Y H:i', strtotime($a['created_at'])); ?></span>
                        <span>·</span>
                        <a href="<?php echo $h(BASE_URL . '/' . $a['ficha_slug']); ?>" target="_blank" rel="noopener" class="admin-link">Ver la ficha pública</a>
                    </div>
                </div>
                <span class="admin-pill <?php echo $pendiente ? 'admin-pill--alerta' : 'admin-pill--exito'; ?>"><?php echo $pendiente ? 'Por revisar' : 'Revisada'; ?></span>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:var(--espacio-tres); margin-top:var(--espacio-tres);">
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Quién lo envía</div>
                    <?php echo $h($a['contacto_nombre']); ?><br>
                    <a href="mailto:<?php echo $h($a['contacto_email']); ?>" class="admin-link"><?php echo $h($a['contacto_email']); ?></a><br>
                    <?php echo $h($a['contacto_telefono']); ?>
                </div>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Contexto</div>
                    <?php echo $a['desde_llamada'] ? 'Había pedido la llamada' : 'Camino del formulario'; ?><br>
                    <?php echo $a['acepta_comunicaciones'] ? 'Acepta ofertas comerciales' : 'No acepta ofertas comerciales'; ?>
                </div>
                <?php if (!empty($a['comentarios'])): ?>
                <div>
                    <div style="font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); font-weight:600;">Comentarios privados del negocio</div>
                    <span class="act-valor"><?php echo $h($a['comentarios']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <form method="POST">
                <input type="hidden" name="actualizacion_id" value="<?php echo (int) $a['id']; ?>">
                <input type="hidden" name="volver" value="<?php echo $h($ver); ?>">

                <?php if ($campos): ?>
                <table class="act-tabla">
                    <thead><tr><th>Dato</th><th>Ahora en la ficha</th><th>Propuesto por el negocio</th><th><?php echo $pendiente ? 'Decisión' : 'Resultado'; ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($campos as $c):
                        $cat = $CAT[$c['campo']] ?? [$c['campo'], 'directo', ''];
                        $esTexto = $cat[1] === 'texto_original';
                        $esEtiqueta = $cat[1] === 'etiqueta_propuesta';
                    ?>
                        <tr>
                            <td class="act-tabla__campo"><?php echo $h($cat[0]); ?>
                                <?php if ($esEtiqueta): ?><div><span class="admin-pill admin-pill--alerta">etiqueta nueva propuesta por usuario</span></div><div style="font-weight:400; font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); white-space:normal; max-width:22ch;">Al aprobar se archivan en la ficha; todavía no entran en filtros ni buscadores</div><?php endif; ?>
                                <?php if ($esTexto): ?><div style="font-weight:400; font-size:var(--admin-body-sm); color:var(--admin-tinta-suave); white-space:normal; max-width:22ch;">Texto libre: se archiva como texto original y lo estructura la IA en la ficha</div><?php endif; ?>
                            </td>
                            <td><span class="act-valor<?php echo $c['valor_actual'] === null || $c['valor_actual'] === '' ? ' act-valor--vacio' : ''; ?>"><?php echo $c['valor_actual'] !== null && $c['valor_actual'] !== '' ? $h($c['valor_actual']) : '(vacío)'; ?></span></td>
                            <td><span class="act-valor act-valor--nuevo"><?php echo $h($c['valor_propuesto']); ?></span></td>
                            <td>
                                <?php if ($pendiente): ?>
                                <div class="act-decision">
                                    <label class="field__opcion"><input type="radio" class="field__radio act-radio" name="campo[<?php echo $h($c['campo']); ?>]" value="aprobar"><span>Aprobar</span></label>
                                    <label class="field__opcion"><input type="radio" class="field__radio act-radio" name="campo[<?php echo $h($c['campo']); ?>]" value="rechazar"><span>Rechazar</span></label>
                                </div>
                                <?php else: ?>
                                <span class="admin-pill <?php echo $c['estado'] === 'aprobado' ? 'admin-pill--exito' : 'admin-pill--error'; ?>"><?php echo $c['estado'] === 'aprobado' ? 'Aprobado' : 'Rechazado'; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <?php if ($imagenes): ?>
                <h3 style="margin:var(--espacio-cuatro) 0 0; font-size:var(--admin-body);">Imágenes propuestas</h3>
                <div class="act-imagenes">
                    <?php foreach ($imagenes as $im): ?>
                    <div class="act-imagen">
                        <a href="<?php echo $h(BASE_URL . '/' . $im['ruta']); ?>" target="_blank" rel="noopener"><img src="<?php echo $h(BASE_URL . '/' . $im['ruta']); ?>" alt="<?php echo $h($im['nombre_original'] ?: $im['nombre_archivo']); ?>" loading="lazy"></a>
                        <span><?php echo $im['tipo'] === 'logo' ? 'Logo' : 'Galería'; ?></span>
                        <?php if ($pendiente): ?>
                        <label class="field__opcion"><input type="radio" class="field__radio act-radio" name="imagen[<?php echo (int) $im['id']; ?>]" value="aprobar"><span>Aprobar</span></label>
                        <label class="field__opcion"><input type="radio" class="field__radio act-radio" name="imagen[<?php echo (int) $im['id']; ?>]" value="rechazar"><span>Rechazar</span></label>
                        <?php else: ?>
                        <span class="admin-pill <?php echo $im['estado'] === 'aprobada' ? 'admin-pill--exito' : 'admin-pill--error'; ?>"><?php echo $im['estado'] === 'aprobada' ? 'Aprobada' : 'Rechazada'; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!$campos && !$imagenes): ?>
                <p style="color:var(--admin-tinta-suave); margin-top:var(--espacio-tres);">Esta actualización no contiene datos.</p>
                <?php endif; ?>

                <?php if ($pendiente): ?>
                <div class="act-atajos">
                    <button type="button" class="boton dos pequeno" data-atajo="aprobar">Aprobar todo</button>
                    <button type="button" class="boton dos pequeno" data-atajo="rechazar">Rechazar todo</button>
                </div>
                <div class="field" style="margin:var(--espacio-tres) 0 0;">
                    <label class="field__label">Mensaje para el negocio (opcional)</label>
                    <textarea name="mensaje_admin" class="field__textarea" rows="2" placeholder="Si algo no se aprueba, explica por qué: sale en el email que recibe el negocio."></textarea>
                </div>
                <div style="margin-top:var(--espacio-tres); display:flex; gap:var(--espacio-dos); flex-wrap:wrap;">
                    <button type="submit" class="boton uno pequeno"
                            onclick="return confirm('¿Guardar la revisión? Lo aprobado se publica en la ficha y se envía un email al negocio.');">
                        <i data-lucide="check-check" class="icono"></i> Guardar revisión
                    </button>
                </div>
                <?php elseif (!empty($a['mensaje_admin'])): ?>
                <p style="margin-top:var(--espacio-tres);"><strong>Mensaje enviado al negocio:</strong> <?php echo $h($a['mensaje_admin']); ?></p>
                <?php endif; ?>
            </form>
        </section>
    <?php endforeach; endif; ?>

</div>

<script>
document.querySelectorAll('[data-atajo]').forEach(function (b) {
    b.addEventListener('click', function () {
        var form = b.closest('form');
        form.querySelectorAll('input.act-radio[value="' + b.dataset.atajo + '"]').forEach(function (r) { r.checked = true; });
    });
});
</script>

<?php include 'footer.php'; ?>
