<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ACCIÓN SOBRE UNA RESPUESTA DEL NEGOCIO A UNA RESEÑA (AJAX)
 * ═══════════════════════════════════════════════════════════
 * Acciones: aprobar · rechazar · pausar (vuelve a pendiente) · eliminar (solo desde rechazada).
 * Aprobar avisa al negocio por email (una sola vez por respuesta).
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

$id     = (int)($_POST['id'] ?? 0);
$accion = (string)($_POST['accion'] ?? '');
$motivo = trim((string)($_POST['motivo'] ?? ''));

if (!$id || !in_array($accion, ['aprobar', 'rechazar', 'pausar', 'eliminar'], true)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

requierePermiso($accion === 'eliminar' ? 'eliminacion' : 'moderacion');
if ($accion === 'eliminar' && empty($_POST['confirmar'])) {
    echo json_encode(['ok' => false, 'mensaje' => 'Falta confirmación para eliminar']);
    exit;
}

$pdo   = obtenerConexion();
$admin = obtenerAdminActual();

try {
    $st = $pdo->prepare("SELECT id, resena_id, estado FROM resenas_respuestas WHERE id = ?");
    $st->execute([$id]);
    $resp = $st->fetch(PDO::FETCH_ASSOC);
    if (!$resp) {
        echo json_encode(['ok' => false, 'mensaje' => 'Respuesta no encontrada']);
        exit;
    }

    if ($accion === 'eliminar') {
        if ($resp['estado'] !== 'rechazada') {
            echo json_encode(['ok' => false, 'mensaje' => 'Solo se pueden eliminar respuestas en estado "rechazada". Recházala primero.']);
            exit;
        }
        $pdo->prepare("DELETE FROM resenas_respuestas WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true, 'mensaje' => 'Respuesta eliminada']);
        exit;
    }

    // Volver a pendiente / aprobar: no puede haber dos respuestas vivas para la misma reseña
    if (in_array($accion, ['aprobar', 'pausar'], true)) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM resenas_respuestas
                            WHERE resena_id = ? AND id <> ? AND estado IN ('pendiente','aprobada')");
        $q->execute([$resp['resena_id'], $id]);
        if ((int)$q->fetchColumn() > 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Esa reseña ya tiene otra respuesta pendiente o publicada.']);
            exit;
        }
    }

    if ($accion === 'aprobar') {
        $upd = $pdo->prepare("UPDATE resenas_respuestas
                              SET estado = 'aprobada', motivo_rechazo = NULL, moderado_por = ?, moderado_en = NOW()
                              WHERE id = ?");
        $upd->execute([$admin['id'], $id]);
        $msj = 'Respuesta aprobada: ya es pública';
    } elseif ($accion === 'rechazar') {
        $upd = $pdo->prepare("UPDATE resenas_respuestas
                              SET estado = 'rechazada', motivo_rechazo = ?, moderado_por = ?, moderado_en = NOW()
                              WHERE id = ?");
        $upd->execute([$motivo !== '' ? mb_substr($motivo, 0, 500) : null, $admin['id'], $id]);
        $msj = 'Respuesta rechazada';
    } else { // pausar
        $upd = $pdo->prepare("UPDATE resenas_respuestas
                              SET estado = 'pendiente', motivo_rechazo = NULL, moderado_por = NULL, moderado_en = NULL
                              WHERE id = ?");
        $upd->execute([$id]);
        $msj = 'Respuesta devuelta a pendiente';
    }

    echo json_encode(['ok' => true, 'mensaje' => $msj]);

    // Aviso al negocio: solo al aprobar (la clave de idempotencia evita reenvíos al pausar → aprobar)
    if ($accion === 'aprobar') {
        try {
            require_once dirname(__DIR__) . '/includes/notificaciones/despachador.php';
            notifCerrarRespuesta();
            notificarEvento($pdo, 'respuesta.aprobada', ['respuesta_id' => $id]);
        } catch (\Throwable $e) {
            error_log('[respuesta-accion] notificación aprobada: ' . $e->getMessage());
        }
    }
} catch (PDOException $e) {
    error_log('[respuesta-accion] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
