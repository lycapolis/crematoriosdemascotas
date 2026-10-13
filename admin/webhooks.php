<?php
/**
 * Panel Admin — Webhooks (solo super_admin).
 *
 * Configura el canal «webhook» de la capa de notificaciones (includes/notificaciones/):
 *   1. Destinos: URLs que reciben el payload de los eventos (Make, n8n, CRM…), cada una con
 *      los eventos a los que está suscrita y un secreto opcional para firmar el cuerpo.
 *   2. Eventos: interruptor por evento y variables extra que se añaden al JSON heredado de Make
 *      (nunca se tocan ni renombran las claves que Make ya recibe).
 *   3. Registro: últimos envíos (estado por destino).
 *
 * Tablas: notificaciones_webhook_destinos, notificaciones_webhook_eventos
 * (admin/migrations/create_notificaciones_webhooks.sql). Sin migrar, el canal sigue enviando
 * a WEBHOOK_URLS (config) como respaldo.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/notificaciones/maquetas.php';

requerirAutenticacion();
requiereSuperAdmin();

$pdo = obtenerConexion();
$e   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$catalogo = array_filter(notifCatalogoEventos(), fn($d) => in_array('webhook', $d['canales'], true));
$extrasCatalogo = notifWebhookExtrasCatalogo();

// ¿Está corrida la migración?
$migrado = true;
try {
    $pdo->query("SELECT 1 FROM notificaciones_webhook_destinos LIMIT 1");
    $pdo->query("SELECT 1 FROM notificaciones_webhook_eventos LIMIT 1");
} catch (\Throwable $ex) {
    $migrado = false;
}

// ─── Acciones (POST) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $flash = ['ok' => false, 'msg' => 'La sesión expiró. Recarga la página.'];
    if (validarTokenCSRF((string)($_POST['csrf_token'] ?? '')) && $migrado) {
        $accion = (string)($_POST['accion'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);
        try {
            switch ($accion) {
                case 'destino_guardar':
                    $nombre  = trim((string)($_POST['nombre'] ?? ''));
                    $url     = trim((string)($_POST['url'] ?? ''));
                    $secreto = trim((string)($_POST['secreto'] ?? ''));
                    $activo  = isset($_POST['activo']) ? 1 : 0;
                    $evSel   = array_values(array_intersect(array_keys($catalogo), (array)($_POST['eventos'] ?? [])));
                    $eventos = (count($evSel) === count($catalogo)) ? null : json_encode($evSel);
                    if ($nombre === '' || !preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                        $flash = ['ok' => false, 'msg' => 'Indica un nombre y una URL válida (http:// o https://).'];
                        break;
                    }
                    if ($id > 0) {
                        $pdo->prepare("UPDATE notificaciones_webhook_destinos SET nombre=?, url=?, secreto=?, eventos=?, activo=? WHERE id=?")
                            ->execute([mb_substr($nombre, 0, 80), $url, $secreto ?: null, $eventos, $activo, $id]);
                        $flash = ['ok' => true, 'msg' => 'Destino actualizado.'];
                    } else {
                        $pdo->prepare("INSERT INTO notificaciones_webhook_destinos (nombre, url, secreto, eventos, activo) VALUES (?,?,?,?,?)")
                            ->execute([mb_substr($nombre, 0, 80), $url, $secreto ?: null, $eventos, $activo]);
                        $flash = ['ok' => true, 'msg' => 'Destino añadido. Recibirá los eventos marcados desde ahora.'];
                    }
                    break;

                case 'destino_toggle':
                    $pdo->prepare("UPDATE notificaciones_webhook_destinos SET activo = 1 - activo WHERE id = ?")->execute([$id]);
                    $flash = ['ok' => true, 'msg' => 'Estado del destino cambiado.'];
                    break;

                case 'destino_borrar':
                    $pdo->prepare("DELETE FROM notificaciones_webhook_destinos WHERE id = ?")->execute([$id]);
                    $flash = ['ok' => true, 'msg' => 'Destino eliminado.'];
                    break;

                case 'destino_importar':
                    // Copia las URLs de WEBHOOK_URLS (config) a la tabla, una sola vez
                    $urls = json_decode(defined('WEBHOOK_URLS') ? WEBHOOK_URLS : '[]', true) ?: [];
                    $n = 0;
                    foreach (array_values($urls) as $i => $u) {
                        if (!is_string($u) || $u === '') continue;
                        $pdo->prepare("INSERT INTO notificaciones_webhook_destinos (nombre, url, activo) VALUES (?,?,1)")
                            ->execute([count($urls) > 1 ? 'Make (config ' . ($i + 1) . ')' : 'Make (importado de config)', $u]);
                        $n++;
                    }
                    $flash = $n ? ['ok' => true, 'msg' => "Importados {$n} destino(s) desde la configuración."]
                                : ['ok' => false, 'msg' => 'WEBHOOK_URLS está vacío: no hay nada que importar.'];
                    break;

                case 'destino_probar':
                    $st = $pdo->prepare("SELECT id, nombre, url, secreto FROM notificaciones_webhook_destinos WHERE id = ?");
                    $st->execute([$id]);
                    $d = $st->fetch(\PDO::FETCH_ASSOC);
                    if (!$d) { $flash = ['ok' => false, 'msg' => 'Destino no encontrado.']; break; }
                    $clave = 'prueba:' . bin2hex(random_bytes(6));
                    $json  = json_encode([
                        'evento'  => 'prueba.ping', 'version' => 1, 'id' => $clave, 'fecha' => date('c'),
                        'origen'  => parse_url(notifUrlPublica(), PHP_URL_HOST) ?: 'portal',
                        'data'    => ['mensaje' => 'Envío de prueba desde admin/webhooks.php'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $r = notifWebhookPost($d, $json, 'prueba.ping', $clave);
                    $flash = $r['ok'] ? ['ok' => true, 'msg' => "«{$d['nombre']}» respondió correctamente a la prueba."]
                                      : ['ok' => false, 'msg' => 'La prueba falló: ' . ($r['error'] ?? 'error desconocido')];
                    break;

                case 'evento_guardar':
                    $ev = (string)($_POST['evento'] ?? '');
                    if (!isset($catalogo[$ev])) { $flash = ['ok' => false, 'msg' => 'Evento no válido.']; break; }
                    $activo = isset($_POST['activo']) ? 1 : 0;
                    $sel    = array_values(array_intersect(array_keys($extrasCatalogo), (array)($_POST['extras'] ?? [])));
                    $extras = (count($sel) === count($extrasCatalogo)) ? null : json_encode($sel);
                    $pdo->prepare("INSERT INTO notificaciones_webhook_eventos (evento, activo, extras) VALUES (?,?,?)
                                   ON DUPLICATE KEY UPDATE activo = VALUES(activo), extras = VALUES(extras)")
                        ->execute([$ev, $activo, $extras]);
                    $flash = ['ok' => true, 'msg' => 'Evento «' . $catalogo[$ev]['titulo'] . '» guardado.'];
                    break;

                default:
                    $flash = ['ok' => false, 'msg' => 'Acción no válida.'];
            }
        } catch (\Throwable $ex) {
            error_log('[admin/webhooks] ' . $ex->getMessage());
            $flash = ['ok' => false, 'msg' => 'No se pudo completar la acción. Revisa el log del servidor.'];
        }
    } elseif (!$migrado) {
        $flash = ['ok' => false, 'msg' => 'Falta correr la migración create_notificaciones_webhooks.sql.'];
    }
    $_SESSION['webhooks_flash'] = $flash;
    header('Location: ' . BASE_URL . '/admin/webhooks.php');
    exit;
}

$flash = $_SESSION['webhooks_flash'] ?? null;
unset($_SESSION['webhooks_flash']);

// ─── Datos para pintar ─────────────────────────────────────
$destinos = [];
$cfgEventos = [];
$log = [];
if ($migrado) {
    $destinos = $pdo->query("SELECT * FROM notificaciones_webhook_destinos ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
}
foreach (array_keys($catalogo) as $ev) {
    $cfgEventos[$ev] = notifWebhookConfigEvento($pdo, $ev);
}
try {
    $log = $pdo->query("SELECT evento, destinatario, estado, motivo, error, intentos, created_at
                        FROM notificaciones_log WHERE canal = 'webhook' ORDER BY id DESC LIMIT 15")->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $ex) { $log = []; }

$nombreDest = [];
foreach ($destinos as $d) $nombreDest['wh:' . $d['id']] = $d['nombre'];

$urlsConfig = array_filter(json_decode(defined('WEBHOOK_URLS') ? WEBHOOK_URLS : '[]', true) ?: [], 'is_string');
$negEjemplo = ['id' => 0, 'nombre' => 'Crematorio Ejemplo', 'slug' => 'ejemplo', 'tier' => '00', 'ciudad' => 'Madrid'];
$csrf = generarTokenCSRF();

$titulo_pagina = 'Webhooks — Admin';
include 'header.php';
?>

<style>
    .wh-grid { display: grid; gap: var(--espacio-cuatro); }
    .wh-tabla { width: 100%; border-collapse: collapse; font-size: .92rem; }
    .wh-tabla th { text-align: left; font-size: .75rem; letter-spacing: .06em; text-transform: uppercase; color: var(--admin-tinta-tenue); padding: .4rem .6rem; border-bottom: 1px solid var(--admin-linea); }
    .wh-tabla td { padding: .6rem; border-bottom: 1px solid var(--admin-linea); vertical-align: top; }
    .wh-url { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: .8rem; word-break: break-all; color: var(--admin-tinta-suave); }
    .wh-etiqueta { display: inline-block; padding: .1rem .5rem; border-radius: 999px; font-size: .72rem; font-weight: 600; background: #F5EDE5; color: #5A3E2F; }
    .wh-etiqueta--ok { background: #dcfce7; color: #166534; }
    .wh-etiqueta--off { background: #f3f4f6; color: #6b7280; }
    .wh-etiqueta--error { background: #fee2e2; color: #991b1b; }
    .wh-acciones { display: flex; gap: .4rem; flex-wrap: wrap; }
    .wh-acciones form { margin: 0; }
    .wh-form { display: grid; gap: var(--espacio-tres); grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-top: var(--espacio-tres); }
    .wh-form__ancho { grid-column: 1 / -1; }
    .wh-checks { display: grid; gap: .35rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
    .wh-evento { border: 1px solid var(--admin-linea); border-radius: var(--admin-r-md); padding: 0; margin-bottom: var(--espacio-dos); background: var(--admin-superficie); }
    .wh-evento > summary { cursor: pointer; padding: var(--espacio-tres) var(--espacio-cuatro); display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; list-style: none; }
    .wh-evento > summary::-webkit-details-marker { display: none; }
    .wh-evento__cuerpo { padding: 0 var(--espacio-cuatro) var(--espacio-cuatro); }
    .wh-evento pre { background: #FFFBF7; border: 1px solid var(--admin-linea); border-radius: 8px; padding: 12px 14px; font-size: .78rem; line-height: 1.5; overflow: auto; max-height: 360px; margin: var(--espacio-tres) 0 0; }
    .wh-nota { font-size: .85rem; color: var(--admin-tinta-tenue); margin: .25rem 0 0; }
</style>

<div class="admin-page">

    <p style="margin-bottom:var(--espacio-dos);">
        <a href="configuracion.php" class="admin-text-muted">← Configuración</a>
    </p>

    <header class="admin-page-header">
        <h1 class="admin-page-title">Webhooks</h1>
        <p class="admin-page-subtitle">
            Cada conversión y cada cambio de estado importante se envía como JSON a los destinos de abajo (Make, n8n, un CRM…).
            Los eventos que ya llegaban a Make mantienen su JSON intacto y solo suman las variables extra que actives.
            Los clics anónimos («Ir directo», cerrar el modal) no se envían.
        </p>
    </header>

    <?php if ($flash): ?>
    <div class="admin-banner admin-banner--<?= $flash['ok'] ? 'success' : 'warning' ?>" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="<?= $flash['ok'] ? 'check-circle-2' : 'alert-triangle' ?>" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content"><?= $e($flash['msg']) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!$migrado): ?>
    <div class="admin-banner admin-banner--warning" style="margin-bottom:var(--espacio-cuatro);">
        <i data-lucide="database" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            Falta correr la migración <code>admin/migrations/create_notificaciones_webhooks.sql</code> (phpMyAdmin, utf8mb4).
            Mientras tanto, los webhooks se siguen enviando a <code>WEBHOOK_URLS</code> de la configuración.
        </div>
    </div>
    <?php endif; ?>

    <div class="wh-grid">

        <!-- ═════════ DESTINOS ═════════ -->
        <section class="ficha-card">
            <h2 class="ficha-card__title"><i data-lucide="webhook" class="icono"></i> Destinos</h2>

            <?php if ($migrado && !$destinos && $urlsConfig): ?>
            <div class="admin-banner admin-banner--info" style="margin-bottom:var(--espacio-tres);">
                <i data-lucide="info" class="icono admin-banner__icon"></i>
                <div class="admin-banner__content">
                    No hay destinos guardados: ahora se envía a la URL de la configuración (respaldo).
                    <form method="POST" style="display:inline;margin-left:.5rem;">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="accion" value="destino_importar">
                        <button type="submit" class="boton dos">Importar a la tabla para poder editarla</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($destinos): ?>
            <table class="wh-tabla">
                <thead><tr><th>Destino</th><th>Eventos</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($destinos as $d):
                    $sus = ($d['eventos'] === null || $d['eventos'] === '') ? null : (json_decode($d['eventos'], true) ?: []);
                ?>
                <tr>
                    <td>
                        <strong><?= $e($d['nombre']) ?></strong><br>
                        <span class="wh-url"><?= $e($d['url']) ?></span>
                        <?php if ($d['secreto']): ?><br><span class="wh-etiqueta">firmado (X-Firma)</span><?php endif; ?>
                    </td>
                    <td><?= $sus === null ? 'Todos' : count($sus) . ' de ' . count($catalogo) ?></td>
                    <td><span class="wh-etiqueta <?= $d['activo'] ? 'wh-etiqueta--ok' : 'wh-etiqueta--off' ?>"><?= $d['activo'] ? 'Activo' : 'Pausado' ?></span></td>
                    <td>
                        <div class="wh-acciones">
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="accion" value="destino_probar"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="boton dos"><i data-lucide="send" class="icono"></i> Probar</button></form>
                            <form method="POST"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="accion" value="destino_toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="boton dos"><?= $d['activo'] ? 'Pausar' : 'Activar' ?></button></form>
                            <form method="POST" onsubmit="return confirm('¿Eliminar este destino? Dejará de recibir eventos.');"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><input type="hidden" name="accion" value="destino_borrar"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <button type="submit" class="boton dos"><i data-lucide="trash-2" class="icono"></i></button></form>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding-top:0;">
                        <details>
                            <summary class="wh-nota" style="cursor:pointer;">Editar «<?= $e($d['nombre']) ?>»</summary>
                            <form method="POST" class="wh-form">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="accion" value="destino_guardar">
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <div class="field"><label class="field__label">Nombre</label><input type="text" name="nombre" class="field__input" value="<?= $e($d['nombre']) ?>" maxlength="80" required></div>
                                <div class="field"><label class="field__label">Secreto (opcional)</label><input type="text" name="secreto" class="field__input" value="<?= $e($d['secreto']) ?>" autocomplete="off"></div>
                                <div class="field wh-form__ancho"><label class="field__label">URL</label><input type="url" name="url" class="field__input" value="<?= $e($d['url']) ?>" required></div>
                                <div class="wh-form__ancho">
                                    <label class="field__label">Eventos que recibe</label>
                                    <div class="wh-checks">
                                        <?php foreach ($catalogo as $k => $def): ?>
                                        <label class="field__opcion"><input type="checkbox" class="field__check" name="eventos[]" value="<?= $e($k) ?>" <?= ($sus === null || in_array($k, $sus, true)) ? 'checked' : '' ?>><span><?= $e($def['titulo']) ?> <small class="admin-text-muted">· <?= $e($k) ?></small></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <label class="field__opcion"><input type="checkbox" class="field__check" name="activo" value="1" <?= $d['activo'] ? 'checked' : '' ?>><span>Activo</span></label>
                                <div class="wh-form__ancho"><button type="submit" class="boton tres"><i data-lucide="save" class="icono"></i> Guardar destino</button></div>
                            </form>
                        </details>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php elseif ($migrado): ?>
            <p class="wh-nota">Todavía no hay destinos guardados.</p>
            <?php endif; ?>

            <?php if ($migrado): ?>
            <details style="margin-top:var(--espacio-cuatro);" <?= $destinos ? '' : 'open' ?>>
                <summary class="boton dos" style="display:inline-flex;cursor:pointer;"><i data-lucide="plus" class="icono"></i> Añadir destino</summary>
                <form method="POST" class="wh-form">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                    <input type="hidden" name="accion" value="destino_guardar">
                    <div class="field"><label class="field__label">Nombre</label><input type="text" name="nombre" class="field__input" maxlength="80" placeholder="n8n, CRM, Zapier…" required></div>
                    <div class="field"><label class="field__label">Secreto (opcional)</label><input type="text" name="secreto" class="field__input" autocomplete="off" placeholder="Para firmar el cuerpo con HMAC-SHA256"></div>
                    <div class="field wh-form__ancho"><label class="field__label">URL</label><input type="url" name="url" class="field__input" placeholder="https://…" required></div>
                    <div class="wh-form__ancho">
                        <label class="field__label">Eventos que recibe</label>
                        <div class="wh-checks">
                            <?php foreach ($catalogo as $k => $def): ?>
                            <label class="field__opcion"><input type="checkbox" class="field__check" name="eventos[]" value="<?= $e($k) ?>" checked><span><?= $e($def['titulo']) ?> <small class="admin-text-muted">· <?= $e($k) ?></small></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <label class="field__opcion"><input type="checkbox" class="field__check" name="activo" value="1" checked><span>Activo</span></label>
                    <div class="wh-form__ancho"><button type="submit" class="boton tres"><i data-lucide="save" class="icono"></i> Añadir destino</button></div>
                </form>
                <p class="wh-nota">Todos los destinos reciben exactamente el mismo JSON. Con secreto, cada envío lleva la cabecera <code>X-Firma: sha256=…</code> (HMAC del cuerpo) y siempre <code>X-Evento</code> y <code>X-Evento-Id</code>.</p>
            </details>
            <?php endif; ?>
        </section>

        <!-- ═════════ EVENTOS ═════════ -->
        <section class="ficha-card">
            <h2 class="ficha-card__title"><i data-lucide="zap" class="icono"></i> Eventos y variables</h2>
            <p class="wh-nota" style="margin-bottom:var(--espacio-tres);">
                «Heredado» = el JSON que Make ya recibía, intacto, más las variables extra que actives (se añaden al final y nunca pisan una clave existente).
                «Sobre» = formato normalizado nuevo <code>{evento, version, id, fecha, origen, data}</code>. Más adelante se normalizarán los nombres.
            </p>
            <?php foreach ($catalogo as $k => $def):
                $cfg = $cfgEventos[$k];
                $modo = $def['webhook']['modo'] ?? 'sobre';
                $w = notifMaquetaWebhook($pdo, $k, $negEjemplo);
            ?>
            <details class="wh-evento">
                <summary>
                    <strong><?= $e($def['titulo']) ?></strong>
                    <code class="admin-text-muted"><?= $e($k) ?></code>
                    <span class="wh-etiqueta"><?= $modo === 'heredado' ? 'JSON heredado' : 'Sobre normalizado' ?></span>
                    <span class="wh-etiqueta <?= $cfg['activo'] ? 'wh-etiqueta--ok' : 'wh-etiqueta--off' ?>" style="margin-left:auto;"><?= $cfg['activo'] ? 'Activo' : 'Desactivado' ?></span>
                </summary>
                <div class="wh-evento__cuerpo">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                        <input type="hidden" name="accion" value="evento_guardar">
                        <input type="hidden" name="evento" value="<?= $e($k) ?>">
                        <label class="field__opcion" style="margin-bottom:var(--espacio-tres);"><input type="checkbox" class="field__check" name="activo" value="1" <?= $cfg['activo'] ? 'checked' : '' ?>><span><strong>Enviar este evento a los destinos</strong></span></label>
                        <?php if ($modo === 'heredado'): ?>
                        <label class="field__label">Variables extra en el JSON</label>
                        <div class="wh-checks">
                            <?php foreach ($extrasCatalogo as $nombre => $desc): ?>
                            <label class="field__opcion"><input type="checkbox" class="field__check" name="extras[]" value="<?= $e($nombre) ?>" <?= in_array($nombre, $cfg['extras'], true) ? 'checked' : '' ?>><span><code><?= $e($nombre) ?></code> <small class="admin-text-muted">— <?= $e($desc) ?></small></span></label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="wh-nota">Este evento usa el sobre normalizado: sus campos son fijos.</p>
                        <?php endif; ?>
                        <div style="margin-top:var(--espacio-tres);"><button type="submit" class="boton tres"><i data-lucide="save" class="icono"></i> Guardar</button></div>
                    </form>
                    <?php if ($w): ?>
                    <p class="wh-nota" style="margin-top:var(--espacio-tres);">Ejemplo de lo que recibe cada destino (datos ficticios):</p>
                    <pre><?= $e($w['json']) ?></pre>
                    <?php endif; ?>
                </div>
            </details>
            <?php endforeach; ?>
        </section>

        <!-- ═════════ REGISTRO ═════════ -->
        <section class="ficha-card">
            <h2 class="ficha-card__title"><i data-lucide="list" class="icono"></i> Últimos envíos</h2>
            <?php if ($log): ?>
            <table class="wh-tabla">
                <thead><tr><th>Fecha</th><th>Evento</th><th>Destino</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($log as $l): ?>
                <tr>
                    <td><?= $e($l['created_at']) ?></td>
                    <td><code><?= $e($l['evento']) ?></code></td>
                    <td><?= $e($nombreDest[$l['destinatario']] ?? $l['destinatario']) ?></td>
                    <td>
                        <span class="wh-etiqueta <?= $l['estado'] === 'enviada' ? 'wh-etiqueta--ok' : ($l['estado'] === 'fallida' ? 'wh-etiqueta--error' : 'wh-etiqueta--off') ?>"><?= $e($l['estado']) ?></span>
                        <?php if ($l['error']): ?><br><small class="admin-text-muted"><?= $e($l['error']) ?></small><?php endif; ?>
                        <?php if ($l['estado'] === 'fallida'): ?><br><small class="admin-text-muted">Intentos: <?= (int)$l['intentos'] ?>/3 (se reintenta si el evento se repite)</small><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="wh-nota">Aún no hay envíos registrados.</p>
            <?php endif; ?>
        </section>

    </div>
</div>

<?php include 'footer.php'; ?>
