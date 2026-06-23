<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ELIMINAR IMAGEN DE UNA SOLICITUD DE REGISTRO (AJAX)
 * ═══════════════════════════════════════════════════════════
 * Borra el archivo físico + la fila de solicitud_imagenes.
 * Solo permitido sobre solicitudes en estado 'pendiente'.
 *
 * POST:
 *   - imagen_id   (int, requerido)
 *   - solicitud_id (int, requerido) — validación cruzada
 *
 * Respuesta JSON: { ok: bool, mensaje?: string, error?: string }
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');

if (!estaAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}

requierePermiso('solicitudes');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$imagenId    = (int)($_POST['imagen_id']    ?? 0);
$solicitudId = (int)($_POST['solicitud_id'] ?? 0);

if (!$imagenId || !$solicitudId) {
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

$pdo = obtenerConexion();

try {
    // Verificar que la imagen existe y pertenece a la solicitud
    $stmt = $pdo->prepare("
        SELECT si.id, si.ruta, si.tipo, s.estado
        FROM solicitud_imagenes si
        INNER JOIN solicitudes_registro s ON si.solicitud_id = s.id
        WHERE si.id = :img AND si.solicitud_id = :sol
        LIMIT 1
    ");
    $stmt->execute([':img' => $imagenId, ':sol' => $solicitudId]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$img) {
        echo json_encode(['ok' => false, 'mensaje' => 'Imagen no encontrada']);
        exit;
    }

    if ($img['estado'] !== 'pendiente') {
        echo json_encode(['ok' => false, 'mensaje' => 'Solo se pueden eliminar imágenes de solicitudes pendientes']);
        exit;
    }

    // Borrar archivo físico
    $rutaRelativa = str_replace('\\', '/', $img['ruta']);
    $rutaAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);
    $archivoBorrado = false;
    if (is_file($rutaAbs) && is_writable($rutaAbs)) {
        $archivoBorrado = @unlink($rutaAbs);
    } elseif (is_file($rutaAbs)) {
        // no escribible — log y seguir; la BD se limpia igual
        error_log('solicitud-imagen-eliminar: archivo no writable: ' . $rutaAbs);
    }

    // Borrar fila
    $stmtDel = $pdo->prepare("DELETE FROM solicitud_imagenes WHERE id = :id");
    $stmtDel->execute([':id' => $imagenId]);

    if ($stmtDel->rowCount() === 0) {
        echo json_encode(['ok' => false, 'mensaje' => 'No se pudo eliminar la fila']);
        exit;
    }

    echo json_encode([
        'ok'              => true,
        'mensaje'         => 'Imagen eliminada',
        'archivo_borrado' => $archivoBorrado,
        'tipo'            => $img['tipo'],
    ]);

} catch (PDOException $e) {
    error_log('solicitud-imagen-eliminar: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
