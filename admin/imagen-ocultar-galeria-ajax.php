<?php
/**
 * AJAX — Visibilidad pública de una foto de cliente (4 niveles anidados).
 *
 * Aplica solo a imágenes con tipo='cliente'. Ver migración add_visibilidad_cliente.sql.
 *   completa | solo_galerias_cliente | solo_resena | oculta
 *
 * POST (nuevo):  imagen_id (int), visibilidad (uno de los 4 niveles)
 * POST (legacy): imagen_id (int), valor ('1'|'0')  → 1=solo_galerias_cliente, 0=completa
 *                (compat con el checkbox viejo de S7 hasta migrarlo a los 4 niveles)
 *
 * Mantiene ocultar_de_galeria_principal sincronizado (legacy, no se borró):
 *   completa → 0 · resto → 1.
 *
 * Respuesta JSON: { ok: bool, visibilidad: string, error: ?string }
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

$imagenId = (int) ($_POST['imagen_id'] ?? 0);
if (!$imagenId) {
    echo json_encode(['ok' => false, 'error' => 'imagen_id requerido']);
    exit;
}

$NIVELES = ['completa', 'solo_galerias_cliente', 'solo_resena', 'oculta'];

if (isset($_POST['visibilidad'])) {
    $vis = (string) $_POST['visibilidad'];
    if (!in_array($vis, $NIVELES, true)) {
        echo json_encode(['ok' => false, 'error' => 'Nivel de visibilidad inválido']);
        exit;
    }
} else {
    // Compat legacy: checkbox booleano viejo
    $vis = (($_POST['valor'] ?? '') === '1') ? 'solo_galerias_cliente' : 'completa';
}

$ogpLegacy = ($vis === 'completa') ? 0 : 1;

$pdo = obtenerConexion();

try {
    $stmt = $pdo->prepare("SELECT id, tipo FROM crematorio_imagenes WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $imagenId]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$img) {
        echo json_encode(['ok' => false, 'error' => 'Imagen no encontrada']);
        exit;
    }
    if ($img['tipo'] !== 'cliente') {
        echo json_encode(['ok' => false, 'error' => 'Esta opción solo aplica a imágenes de cliente']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE crematorio_imagenes
                              SET visibilidad = :vis, ocultar_de_galeria_principal = :ogp
                            WHERE id = :id");
    $stmt->execute([':vis' => $vis, ':ogp' => $ogpLegacy, ':id' => $imagenId]);

    echo json_encode(['ok' => true, 'visibilidad' => $vis, 'error' => null]);

} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
