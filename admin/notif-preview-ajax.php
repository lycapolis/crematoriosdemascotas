<?php
/**
 * Maqueta de notificaciones (AJAX) — usado por admin/notif-leads-test.php
 *
 * POST JSON + cabecera X-CSRF-Token:
 *   { accion:'preview', tipo, plan, crem_id, email }  → { ok, asunto, html, avisos }
 *   { accion:'enviar',  tipo, plan, crem_id, email }  → { ok, mensaje }   (envía la maqueta a `email`)
 *   { accion:'azar',    plan? }                       → { ok, negocio:{id,nombre,tier} }
 *
 * Nunca envía nada a negocios reales: el único destinatario posible es el email que se escriba.
 */

ob_start();
ini_set('display_errors', '0');
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/notificaciones/maquetas.php';

requerirAutenticacion();
requierePermiso('solicitudes');

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

function respuestaMaqueta(array $d, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respuestaMaqueta(['ok' => false, 'error' => 'Método no permitido'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!validarTokenCSRF((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf_token'] ?? '')))) {
    respuestaMaqueta(['ok' => false, 'error' => 'La sesión expiró. Recarga la página.'], 403);
}

$pdo    = obtenerConexion();
$accion = (string)($in['accion'] ?? '');
$tipo   = (string)($in['tipo'] ?? '');
$plan   = (string)($in['plan'] ?? '');
$cremId = (int)($in['crem_id'] ?? 0);
$email  = trim((string)($in['email'] ?? ''));

if ($accion === 'azar') {
    $sql    = "SELECT id, nombre, tier FROM crematorios WHERE activo = 1" . ($plan !== '' ? " AND tier = :t" : '') . " ORDER BY RAND() LIMIT 1";
    $st     = $pdo->prepare($sql);
    $st->execute($plan !== '' ? [':t' => $plan] : []);
    $n = $st->fetch(PDO::FETCH_ASSOC);
    respuestaMaqueta($n ? ['ok' => true, 'negocio' => $n] : ['ok' => false, 'error' => 'No hay negocios activos en ese plan.']);
}

if (!array_key_exists($tipo, notifMaquetaTipos())) {
    respuestaMaqueta(['ok' => false, 'error' => 'Tipo de maqueta no válido.'], 400);
}

$m = notifMaqueta($pdo, $tipo, ['plan' => $plan, 'crem_id' => $cremId], $email);
if (!$m['ok']) respuestaMaqueta(['ok' => false, 'error' => $m['avisos'][0] ?? 'Error'], 400);

if ($accion === 'preview') {
    respuestaMaqueta(['ok' => true, 'asunto' => $m['tpl']['asunto'], 'html' => $m['tpl']['html'], 'avisos' => $m['avisos']]);
}

if ($accion === 'enviar') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respuestaMaqueta(['ok' => false, 'error' => 'Escribe un email válido para recibir la maqueta.']);
    }
    $envio = enviarMailHtml($email, '[MAQUETA] ' . $m['tpl']['asunto'], $m['tpl']['html'], $m['tpl']['texto'], 'info@crematoriosdemascotas.com');
    respuestaMaqueta($envio['ok']
        ? ['ok' => true, 'mensaje' => 'Enviada a ' . $email . ' (transporte: ' . ($envio['transporte'] ?? '?') . ').']
        : ['ok' => false, 'error' => 'No se pudo enviar (' . ($envio['transporte'] ?? '?') . '): ' . ($envio['error'] ?? 'error desconocido')]);
}

respuestaMaqueta(['ok' => false, 'error' => 'Acción no válida.'], 400);
