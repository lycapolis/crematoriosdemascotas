<?php
/**
 * AJAX — Activar/desactivar imagen(es) de galería
 * POST: crematorio_id, ids[] (array), visible (0|1)
 */
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit;
}

$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$ids          = array_map('intval', (array)($_POST['ids'] ?? []));
$visible      = intval($_POST['visible'] ?? 1) ? 1 : 0;

if (!$crematorioId || empty($ids)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros requeridos']); exit;
}

$pdo = obtenerConexion();

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$params = array_merge($ids, [$crematorioId]);

// Solo permite actuar sobre tipo='galeria' y que pertenezcan al crematorio
$stmt = $pdo->prepare(
    "UPDATE crematorio_imagenes
     SET visible = ?
     WHERE id IN ($placeholders)
       AND crematorio_id = ?
       AND tipo = 'galeria'"
);
array_unshift($params, $visible);
$stmt->execute($params);

echo json_encode(['ok' => true, 'afectadas' => $stmt->rowCount()]);
