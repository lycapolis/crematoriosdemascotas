<?php
/**
 * AJAX — Anclar/desanclar logo principal de un crematorio
 * POST: crematorio_id, imagen_id (0 = desanclar)
 */
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit;
}

$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$imagenId     = intval($_POST['imagen_id']     ?? 0); // 0 = desanclar

if (!$crematorioId) {
    echo json_encode(['ok' => false, 'error' => 'crematorio_id requerido']); exit;
}

$pdo = obtenerConexion();

// Verificar que la imagen pertenece al crematorio y es tipo logo
if ($imagenId > 0) {
    $check = $pdo->prepare("SELECT id FROM crematorio_imagenes WHERE id = :img AND crematorio_id = :crem AND tipo = 'logo'");
    $check->execute([':img' => $imagenId, ':crem' => $crematorioId]);
    if (!$check->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Imagen no válida']); exit;
    }
}

$nuevoValor = $imagenId > 0 ? $imagenId : null;
$pdo->prepare("UPDATE crematorios SET logo_principal_id = :val WHERE id = :id")
    ->execute([':val' => $nuevoValor, ':id' => $crematorioId]);

echo json_encode(['ok' => true, 'logo_principal_id' => $nuevoValor]);
