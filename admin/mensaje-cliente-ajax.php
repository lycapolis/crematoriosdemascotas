<?php
/**
 * AJAX — Toggle de estado del mensaje del cliente (leído / respondido / solucionado)
 *
 * POST:
 *   - crematorio_id (int, requerido)
 *   - flag (string, requerido): 'leido' | 'respondido' | 'solucionado'
 *   - valor (string '1'|'0', requerido)
 *
 * Respuesta JSON:
 *   { ok: bool, estado: {leido, respondido, solucionado, actualizado_at}, error: ?string }
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

ini_set('display_errors', '0');

requerirAutenticacion();

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$crematorioId = (int) ($_POST['crematorio_id'] ?? 0);
$flag         = trim($_POST['flag']  ?? '');
$valor        = ($_POST['valor'] ?? '') === '1';

$FLAGS_VALIDOS = ['leido', 'respondido', 'solucionado'];
if (!$crematorioId || !in_array($flag, $FLAGS_VALIDOS, true)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos. flag debe ser uno de: ' . implode(', ', $FLAGS_VALIDOS)]);
    exit;
}

$pdo = obtenerConexion();

try {
    // Leer estado actual
    $stmt = $pdo->prepare("SELECT mensaje_cliente_estado_json FROM crematorios WHERE id = :id");
    $stmt->execute([':id' => $crematorioId]);
    $raw = $stmt->fetchColumn();

    $estado = [
        'leido'         => false,
        'respondido'    => false,
        'solucionado'   => false,
        'actualizado_at'=> null,
    ];
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach (['leido', 'respondido', 'solucionado'] as $k) {
                if (isset($decoded[$k])) $estado[$k] = (bool) $decoded[$k];
            }
        }
    }

    // Aplicar el cambio
    $estado[$flag]          = $valor;
    $estado['actualizado_at'] = date('Y-m-d H:i:s');

    $jsonOut = json_encode($estado, JSON_UNESCAPED_UNICODE);
    $upd = $pdo->prepare("UPDATE crematorios SET mensaje_cliente_estado_json = :json WHERE id = :id");
    $upd->execute([':json' => $jsonOut, ':id' => $crematorioId]);

    echo json_encode(['ok' => true, 'estado' => $estado, 'error' => null]);

} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
