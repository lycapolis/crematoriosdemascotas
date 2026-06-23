<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ELIMINAR EN LOTE TODAS LAS RESEÑAS MARCADAS COMO SPAM (AJAX)
 * ═══════════════════════════════════════════════════════════
 * Borra todas las reseñas con estado='rechazada' AND es_spam=1.
 * Incluye cascada de imágenes adjuntas (archivos + filas).
 * Requiere confirmación explícita: confirmar=1
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

header('Content-Type: application/json; charset=utf-8');

if (!estaAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'No autorizado']);
    exit;
}
requierePermiso('eliminacion');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

if (empty($_POST['confirmar'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Falta confirmación']);
    exit;
}

$pdo = obtenerConexion();

try {
    // 1. Recolectar IDs de reseñas spam rechazadas
    $stmtIds = $pdo->query("SELECT id FROM resenas WHERE estado = 'rechazada' AND es_spam = 1");
    $idsResenas = array_map('intval', array_column($stmtIds->fetchAll(PDO::FETCH_ASSOC), 'id'));

    if (empty($idsResenas)) {
        echo json_encode([
            'ok' => true,
            'mensaje' => 'No hay reseñas SPAM para eliminar',
            'resenas_eliminadas' => 0,
            'imagenes_eliminadas' => 0
        ]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($idsResenas), '?'));

    // 2. Recolectar imágenes vinculadas (por si todavía quedaban)
    $stmtImgs = $pdo->prepare("SELECT id, ruta FROM crematorio_imagenes WHERE resena_id IN ($placeholders)");
    $stmtImgs->execute($idsResenas);
    $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

    // 3. Borrar archivos físicos + filas de imágenes
    $imagenesEliminadas = 0;
    $stmtDelImg = $pdo->prepare("DELETE FROM crematorio_imagenes WHERE id = :id");
    foreach ($imgs as $img) {
        if (!empty($img['ruta']) && !str_starts_with($img['ruta'], 'http')) {
            ImagenHelper::eliminar($img['ruta']);
        }
        $stmtDelImg->execute([':id' => $img['id']]);
        $imagenesEliminadas++;
    }

    // 4. Borrar las reseñas
    $stmtDelRes = $pdo->prepare("DELETE FROM resenas WHERE id IN ($placeholders)");
    $stmtDelRes->execute($idsResenas);
    $resenasEliminadas = $stmtDelRes->rowCount();

    $msj = $resenasEliminadas . ' reseña' . ($resenasEliminadas === 1 ? '' : 's') . ' SPAM eliminada' . ($resenasEliminadas === 1 ? '' : 's');
    if ($imagenesEliminadas > 0) {
        $msj .= ' · ' . $imagenesEliminadas . ' imagen' . ($imagenesEliminadas === 1 ? '' : 'es');
    }

    echo json_encode([
        'ok' => true,
        'mensaje' => $msj,
        'resenas_eliminadas' => $resenasEliminadas,
        'imagenes_eliminadas' => $imagenesEliminadas
    ]);

} catch (PDOException $e) {
    error_log('resena-eliminar-spam-lote: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
