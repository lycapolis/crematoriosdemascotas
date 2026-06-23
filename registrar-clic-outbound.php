<?php
/**
 * ═══════════════════════════════════════════════════════════
 * REGISTRAR CLIC OUTBOUND (tracking ligero, sin datos personales)
 * ═══════════════════════════════════════════════════════════
 *
 * Endpoint AJAX que loguea CADA clic en un botón saliente interceptado
 * por el widget lead-capture, AUNQUE el usuario NO complete el form.
 *
 * Útil para métricas anónimas: ¿qué fichas tienen más intención de contacto?
 * ¿qué CTAs convierten mejor? ¿skipped vs sent ratio?
 *
 * modal_action posibles:
 *   - click     → solo registró el clic (el modal aún no se mostró/decidió)
 *   - skipped   → presionó "Ir directo" (saltó el form)
 *   - cancelled → cerró el modal sin completar
 *   - sent      → completó el form (ese caso lo registra procesar-lead-b2c.php)
 *
 * Llamado vía fetch con keepalive/sendBeacon-style desde el JS del widget.
 * Devuelve { ok: true } siempre — es fire-and-forget, no debe bloquear al usuario.
 *
 * Autor: Facundo M. Campos | Lycapolis LLC
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

// El payload puede venir como JSON body o form-urlencoded
$input = file_get_contents('php://input');
$body  = json_decode($input, true);
$data  = is_array($body) ? $body : $_POST;

$accion        = trim($data['accion'] ?? '');
$modalAction   = trim($data['modal_action'] ?? 'click');
$crematorioId  = (int)($data['crematorio_id'] ?? 0) ?: null;
$destinoUrl    = trim($data['destino_url'] ?? '');
$paginaOrigen  = trim($data['pagina_origen'] ?? '');

// Validación mínima — solo aceptar acciones conocidas
if (!in_array($accion, ['tel', 'wa', 'maps', 'web'], true)) {
    echo json_encode(['ok' => false, 'mensaje' => 'accion inválida']);
    exit;
}
if (!in_array($modalAction, ['click', 'skipped', 'cancelled', 'sent'], true)) {
    $modalAction = 'click';
}

$ip        = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$referrer  = $_SERVER['HTTP_REFERER'] ?? null;

$pdo = obtenerConexion();
if ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO outbound_clicks
        (crematorio_id, accion, destino_url, pagina_origen, modal_action, ip, user_agent, referrer)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$crematorioId, $accion, $destinoUrl ?: null, $paginaOrigen ?: null, $modalAction, $ip, $userAgent, $referrer]);
}

echo json_encode(['ok' => true]);
