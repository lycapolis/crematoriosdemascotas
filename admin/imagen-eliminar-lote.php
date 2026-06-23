<?php
/**
 * AJAX — Eliminar imágenes de galería en lote
 * POST: crematorio_id, ids[] (array)
 */
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
header('Content-Type: application/json; charset=utf-8');
requierePermiso('eliminacion');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit;
}

$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$ids          = array_map('intval', (array)($_POST['ids'] ?? []));

if (!$crematorioId || empty($ids)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros requeridos']); exit;
}

$pdo = obtenerConexion();
$root = dirname(__DIR__);
$eliminadas = 0;

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare(
    "SELECT id, ruta FROM crematorio_imagenes
     WHERE id IN ($placeholders) AND crematorio_id = ? AND tipo = 'galeria'"
);
$stmt->execute(array_merge($ids, [$crematorioId]));
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($imagenes as $img) {
    $ruta = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $img['ruta']);
    $rutaAbs = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . ltrim($ruta, DIRECTORY_SEPARATOR);
    if (file_exists($rutaAbs)) @unlink($rutaAbs);

    $pdo->prepare("DELETE FROM crematorio_imagenes WHERE id = ? AND crematorio_id = ? AND tipo = 'galeria'")
        ->execute([$img['id'], $crematorioId]);
    $eliminadas++;
}

echo json_encode(['ok' => true, 'eliminadas' => $eliminadas]);
