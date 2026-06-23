<?php
/**
 * ═══════════════════════════════════════════════════════════
 * FICHA-ACCIÓN — endpoint AJAX de gestión de fichas
 * ═══════════════════════════════════════════════════════════
 *
 * Acciones (POST):
 *   accion=cambiar_estado  → cambia crematorios.estado (+ sincroniza activo)
 *                            Cualquier admin autenticado.
 *   accion=eliminar        → hard-delete de la ficha. SOLO super_admin.
 *                            Preserva registro histórico:
 *                              - leads_b2c        → crematorio_id=NULL (nombre ya guardado)
 *                              - outbound_clicks   → crematorio_id=NULL (nombre backfilleado)
 *                            Imágenes y reseñas caen por FK CASCADE.
 *
 * Responde JSON siempre.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$pdo    = obtenerConexion();
$accion = $_POST['accion'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if (!$pdo || $id < 1) {
    echo json_encode(['ok' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

// ─── Cambiar estado ──────────────────────────────────────────
if ($accion === 'cambiar_estado') {
    $estado = $_POST['estado'] ?? '';
    $estadosValidos = ['activa', 'pausada', 'cerrada', 'archivada'];
    if (!in_array($estado, $estadosValidos, true)) {
        echo json_encode(['ok' => false, 'mensaje' => 'Estado no válido']);
        exit;
    }

    // activo (cache) = 1 sólo si estado='activa'
    $activo = ($estado === 'activa') ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE crematorios SET estado = :estado, activo = :activo WHERE id = :id");
        $stmt->execute([':estado' => $estado, ':activo' => $activo, ':id' => $id]);
        echo json_encode(['ok' => true, 'estado' => $estado, 'activo' => $activo]);
    } catch (PDOException $e) {
        error_log('[ficha-accion cambiar_estado] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'mensaje' => 'Error al actualizar']);
    }
    exit;
}

// ─── Eliminar (hard-delete) ──────────────────────────────────
if ($accion === 'eliminar') {
    if (!esSuperAdmin()) {
        echo json_encode(['ok' => false, 'mensaje' => 'Solo un super_admin puede eliminar fichas']);
        exit;
    }

    // Nombre de la ficha (para backfill histórico + mensaje)
    $stmt = $pdo->prepare("SELECT nombre FROM crematorios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $nombre = $stmt->fetchColumn();
    if ($nombre === false) {
        echo json_encode(['ok' => false, 'mensaje' => 'La ficha no existe']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Preservar nombre en leads históricos antes de desvincular
        $pdo->prepare("UPDATE leads_b2c
                       SET crematorio_nombre = COALESCE(crematorio_nombre, :n), crematorio_id = NULL
                       WHERE crematorio_id = :id")
            ->execute([':n' => $nombre, ':id' => $id]);

        // 2. Preservar nombre en clicks outbound antes de desvincular
        $pdo->prepare("UPDATE outbound_clicks
                       SET crematorio_nombre = COALESCE(crematorio_nombre, :n), crematorio_id = NULL
                       WHERE crematorio_id = :id")
            ->execute([':n' => $nombre, ':id' => $id]);

        // 3. Borrar la ficha — crematorio_imagenes y resenas caen por FK CASCADE,
        //    solicitudes_registro queda con crematorio_id NULL por FK SET NULL.
        $pdo->prepare("DELETE FROM crematorios WHERE id = :id")
            ->execute([':id' => $id]);

        $pdo->commit();
        echo json_encode([
            'ok'      => true,
            'mensaje' => 'Ficha "' . $nombre . '" eliminada. Los leads y clics se conservaron como histórico.',
        ]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[ficha-accion eliminar] ' . $e->getMessage());
        echo json_encode(['ok' => false, 'mensaje' => 'Error al eliminar la ficha']);
    }
    exit;
}

echo json_encode(['ok' => false, 'mensaje' => 'Acción desconocida']);
