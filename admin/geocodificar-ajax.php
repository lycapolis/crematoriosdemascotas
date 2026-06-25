<?php
/**
 * ═══════════════════════════════════════════════════════════
 * AJAX — Geocodificar dirección de una ficha
 * ═══════════════════════════════════════════════════════════
 *
 * POST: id (int del crematorio)
 *
 * Lee la dirección + ciudad de la ficha y llama a Google Geocoding API.
 * Si encuentra coordenadas → UPDATE latitud/longitud (+ google_place_id si
 * no está seteado).
 *
 * Respuesta JSON:
 *   { ok: bool, lat, lng, place_id?, formatted?, error? }
 */
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('crematorios');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['ok' => false, 'error' => 'id requerido']);
    exit;
}

$pdo = obtenerConexion();
if (!$pdo) {
    echo json_encode(['ok' => false, 'error' => 'Sin conexión BD']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, direccion_completa, ciudad, codigo_postal FROM crematorios WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$ficha = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ficha) {
    echo json_encode(['ok' => false, 'error' => 'Ficha no encontrada']);
    exit;
}

$direccion = trim(($ficha['direccion_completa'] ?? '') . ' ' . ($ficha['codigo_postal'] ?? ''));
if ($direccion === '') {
    echo json_encode(['ok' => false, 'error' => 'La ficha no tiene dirección cargada']);
    exit;
}

$geo = geocodificarDireccion($direccion, $ficha['ciudad'] ?? '', 'ES');
if (empty($geo['ok'])) {
    echo json_encode(['ok' => false, 'error' => $geo['error'] ?? 'No se pudieron obtener coordenadas']);
    exit;
}

// UPDATE — guardar lat/lng y, si google_place_id está vacío, también el place_id.
$pdo->prepare(
    "UPDATE crematorios SET
        latitud  = :lat,
        longitud = :lng,
        google_place_id = COALESCE(NULLIF(google_place_id, ''), :pid),
        updated_at = NOW()
     WHERE id = :id"
)->execute([
    ':lat' => $geo['lat'],
    ':lng' => $geo['lng'],
    ':pid' => $geo['place_id'] ?: null,
    ':id'  => $id,
]);

echo json_encode([
    'ok'        => true,
    'lat'       => $geo['lat'],
    'lng'       => $geo['lng'],
    'place_id'  => $geo['place_id'] ?? null,
    'formatted' => $geo['formatted'] ?? null,
]);
