<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ACCIÓN SOBRE RESEÑA (AJAX) - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!estaAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';

if (!$id || !in_array($accion, ['aprobar', 'rechazar'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

$pdo = obtenerConexion();
$admin = obtenerAdminActual();

$nuevo_estado = $accion === 'aprobar' ? 'aprobada' : 'rechazada';

try {
    $sql = "UPDATE resenas SET
            estado = :estado,
            moderado_por = :admin_id,
            moderado_en = NOW()
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':estado' => $nuevo_estado,
        ':admin_id' => $admin['id'],
        ':id' => $id
    ]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['ok' => true, 'mensaje' => 'Reseña ' . $nuevo_estado]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Reseña no encontrada']);
    }

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
