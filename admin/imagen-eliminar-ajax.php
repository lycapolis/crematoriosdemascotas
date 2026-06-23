<?php
/**
 * AJAX — Eliminar imagen (hard delete archivo + fila BD)
 *
 * POST:
 *   - imagen_id (int, requerido)
 *
 * Respuesta JSON:
 *   { ok: bool, error: ?string }
 *
 * Hard delete sin verificación de crematorio (igual que imagen-eliminar.php),
 * pero retorna JSON para que el caller AJAX no necesite redirección.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

ini_set('display_errors', '0');

requerirAutenticacion();

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

requierePermiso('eliminacion');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$imagenId = (int) ($_POST['imagen_id'] ?? 0);
if (!$imagenId) {
    echo json_encode(['ok' => false, 'error' => 'imagen_id requerido']);
    exit;
}

$pdo = obtenerConexion();

try {
    $stmt = $pdo->prepare("SELECT id, ruta FROM crematorio_imagenes WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $imagenId]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$img) {
        echo json_encode(['ok' => false, 'error' => 'Imagen no encontrada']);
        exit;
    }

    // Borrar archivo físico (solo si es local, no URL externa)
    if (!empty($img['ruta']) && !str_starts_with($img['ruta'], 'http')) {
        ImagenHelper::eliminar($img['ruta']);
    }

    // Borrar de BD
    $pdo->prepare("DELETE FROM crematorio_imagenes WHERE id = :id")->execute([':id' => $imagenId]);

    // Limpiar referencias de portada/logo principal que apuntaran a este ID.
    limpiarReferenciasImagenesBorradas([$imagenId]);

    echo json_encode(['ok' => true, 'error' => null]);

} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
