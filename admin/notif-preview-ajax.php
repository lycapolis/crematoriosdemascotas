<?php
/**
 * Maqueta de notificaciones (AJAX) — usado por admin/notif-leads-test.php
 *
 * POST JSON + cabecera X-CSRF-Token:
 *   { accion:'preview', tipo, plan, crem_id, email, formato } → { ok, asunto, html, avisos }
 *   { accion:'enviar',  tipo, plan, crem_id, email, formato } → { ok, mensaje }   (envía la maqueta a `email`)
 *   formato: 'html' (diseñado, por defecto) | 'texto' (solo texto plano, sin HTML)
 *   Los tipos «webhook:<evento>» no son emails: el preview es el JSON que recibirían los destinos.
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
$formato = ((string)($in['formato'] ?? 'html')) === 'texto' ? 'texto' : 'html';

/** Documento HTML mínimo que muestra un texto tal cual (texto plano o JSON) dentro del iframe del preview. */
function documentoTextoPlano(string $texto): string
{
    return '<!doctype html><html><head><meta charset="UTF-8"><style>'
         . 'body{margin:0;padding:24px 28px;background:#fff;color:#2C2C2C;font:14px/1.55 ui-monospace,Menlo,Consolas,monospace;}'
         . 'pre{margin:0;white-space:pre-wrap;word-break:break-word;}</style></head><body><pre>'
         . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</pre></body></html>';
}

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

$m = notifMaqueta($pdo, $tipo, ['plan' => $plan, 'crem_id' => $cremId, 'entregas' => (int)($in['entregas'] ?? 0)], $email);
if (!$m['ok']) respuestaMaqueta(['ok' => false, 'error' => $m['avisos'][0] ?? 'Error'], 400);

$esWebhook = !empty($m['json']);

if ($accion === 'preview') {
    $html = ($esWebhook || $formato === 'texto') ? documentoTextoPlano($m['tpl']['texto']) : $m['tpl']['html'];
    $avisosP = $m['avisos'];
    if (!$esWebhook && $formato === 'texto') {
        array_unshift($avisosP, 'Versión en TEXTO PLANO: así llega si se envía sin diseño (sin imágenes ni botones; los enlaces van en claro).');
    }
    respuestaMaqueta(['ok' => true, 'asunto' => $m['tpl']['asunto'], 'html' => $html, 'avisos' => $avisosP]
        + ($esWebhook ? ['json' => $m['tpl']['texto']] : []));
}

if ($accion === 'enviar') {
    if ($esWebhook) {
        respuestaMaqueta(['ok' => false, 'error' => 'Los webhooks no se envían desde aquí: usa «Enviar prueba» en la página de Webhooks.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respuestaMaqueta(['ok' => false, 'error' => 'Escribe un email válido para recibir la maqueta.']);
    }
    // Cabecera List-Unsubscribe como en el envío real (para comprobar el botón de baja de Gmail)
    $tiposNegocio = ['lead_negocio', 'teaser', 'resena_negocio', 'respuesta_aprobada'];
    $urlBajaCab   = in_array($tipo, $tiposNegocio, true) ? notifUrlPreferencias($email) : notifUrlBaja($email);
    $envio = enviarMailHtml($email, '[MAQUETA] ' . $m['tpl']['asunto'], $m['tpl']['html'], $m['tpl']['texto'], 'info@crematoriosdemascotas.com', null, $formato, $urlBajaCab);
    respuestaMaqueta($envio['ok']
        ? ['ok' => true, 'mensaje' => 'Enviada a ' . $email . ' en ' . ($formato === 'texto' ? 'texto plano' : 'formato diseñado') . ' (transporte: ' . ($envio['transporte'] ?? '?') . ').']
        : ['ok' => false, 'error' => 'No se pudo enviar (' . ($envio['transporte'] ?? '?') . '): ' . ($envio['error'] ?? 'error desconocido')]);
}

respuestaMaqueta(['ok' => false, 'error' => 'Acción no válida.'], 400);
