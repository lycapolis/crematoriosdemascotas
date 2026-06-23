<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ACCIÓN SOBRE RESEÑA (AJAX) - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
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

$id     = intval($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$eliminarImagenes = !empty($_POST['eliminar_imagenes']);
$confirmar        = !empty($_POST['confirmar']);
$esSpam           = !empty($_POST['es_spam']);

$accionesValidas = ['aprobar', 'rechazar', 'pausar', 'eliminar'];
if (!$id || !in_array($accion, $accionesValidas, true)) {
    echo json_encode(['ok' => false, 'mensaje' => 'Parámetros inválidos']);
    exit;
}

// Permisos por acción: moderar para aprobar/rechazar/pausar; eliminar requiere ambas
if ($accion === 'eliminar') {
    requierePermiso('eliminacion');
} else {
    requierePermiso('moderacion');
}

if ($accion === 'eliminar' && !$confirmar) {
    echo json_encode(['ok' => false, 'mensaje' => 'Falta confirmación para eliminar']);
    exit;
}

$pdo = obtenerConexion();
$admin = obtenerAdminActual();

// Eliminar definitivamente: solo permitido si la reseña ya está en estado 'rechazada'
if ($accion === 'eliminar') {
    $stmtChk = $pdo->prepare("SELECT estado FROM resenas WHERE id = :id");
    $stmtChk->execute([':id' => $id]);
    $estadoActual = $stmtChk->fetchColumn();
    if ($estadoActual === false) {
        echo json_encode(['ok' => false, 'mensaje' => 'Reseña no encontrada']);
        exit;
    }
    if ($estadoActual !== 'rechazada') {
        echo json_encode(['ok' => false, 'mensaje' => 'Solo se pueden eliminar reseñas en estado "rechazada". Pausá/rechazá primero.']);
        exit;
    }
}

$mapaEstados = [
    'aprobar'  => 'aprobada',
    'rechazar' => 'rechazada',
    'pausar'   => 'pendiente',
];

try {
    $imagenesEliminadas = 0;

    // Borrado en cascada de imágenes:
    //  - 'eliminar' siempre (delete final, individual desde rechazada)
    //  - 'rechazar' → solo si el admin lo pidió explícitamente
    // Las reseñas marcadas como SPAM conservan sus imágenes hasta que se eliminen
    // definitivamente (individual o bulk vía resena-eliminar-spam-lote.php).
    $debeBorrarImagenes = $accion === 'eliminar'
                       || ($accion === 'rechazar' && $eliminarImagenes);

    if ($debeBorrarImagenes) {
        require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

        $stmtImgs = $pdo->prepare("SELECT id, ruta FROM crematorio_imagenes WHERE resena_id = :id");
        $stmtImgs->execute([':id' => $id]);
        $imgs = $stmtImgs->fetchAll(PDO::FETCH_ASSOC);

        $stmtDel = $pdo->prepare("DELETE FROM crematorio_imagenes WHERE id = :id");
        foreach ($imgs as $img) {
            if (!empty($img['ruta']) && !str_starts_with($img['ruta'], 'http')) {
                ImagenHelper::eliminar($img['ruta']);
            }
            $stmtDel->execute([':id' => $img['id']]);
            $imagenesEliminadas++;
        }
    }

    if ($accion === 'eliminar') {
        $stmt = $pdo->prepare("DELETE FROM resenas WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            $msj = 'Reseña eliminada';
            if ($imagenesEliminadas > 0) {
                $msj .= ' · ' . $imagenesEliminadas . ' imagen' . ($imagenesEliminadas === 1 ? '' : 'es') . ' eliminada' . ($imagenesEliminadas === 1 ? '' : 's');
            }
            echo json_encode(['ok' => true, 'mensaje' => $msj, 'imagenes_eliminadas' => $imagenesEliminadas, 'eliminada' => true]);
        } else {
            echo json_encode(['ok' => false, 'mensaje' => 'Reseña no encontrada']);
        }
        exit;
    }

    $nuevo_estado = $mapaEstados[$accion];

    // 'pausar' devuelve a 'pendiente' y limpia la marca de moderación previa
    if ($accion === 'pausar') {
        $sql = "UPDATE resenas SET
                estado = :estado,
                moderado_por = NULL,
                moderado_en = NULL,
                motivo_rechazo = NULL,
                es_spam = 0
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':estado' => $nuevo_estado, ':id' => $id]);
    } elseif ($accion === 'rechazar') {
        // Rechazar setea la marca es_spam según lo que mandó el admin
        $sql = "UPDATE resenas SET
                estado = :estado,
                moderado_por = :admin_id,
                moderado_en = NOW(),
                es_spam = :es_spam
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':estado'   => $nuevo_estado,
            ':admin_id' => $admin['id'],
            ':es_spam'  => $esSpam ? 1 : 0,
            ':id'       => $id
        ]);
    } else {
        // 'aprobar': resetea es_spam por si la reseña venía de rechazada-spam y se reevaluó como genuina
        $sql = "UPDATE resenas SET
                estado = :estado,
                moderado_por = :admin_id,
                moderado_en = NOW(),
                es_spam = 0
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':estado'   => $nuevo_estado,
            ':admin_id' => $admin['id'],
            ':id'       => $id
        ]);
    }

    if ($stmt->rowCount() > 0) {
        if ($accion === 'pausar') {
            $msj = 'Reseña devuelta a pendiente';
        } elseif ($accion === 'rechazar' && $esSpam) {
            $msj = 'Reseña marcada como SPAM y rechazada';
        } else {
            $msj = 'Reseña ' . $nuevo_estado;
        }
        if ($imagenesEliminadas > 0) {
            $msj .= ' · ' . $imagenesEliminadas . ' imagen' . ($imagenesEliminadas === 1 ? '' : 'es') . ' eliminada' . ($imagenesEliminadas === 1 ? '' : 's');
        }
        echo json_encode(['ok' => true, 'mensaje' => $msj, 'imagenes_eliminadas' => $imagenesEliminadas, 'nuevo_estado' => $nuevo_estado, 'es_spam' => $esSpam ? 1 : 0]);
    } else {
        echo json_encode(['ok' => false, 'mensaje' => 'Reseña no encontrada']);
    }

} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'mensaje' => 'Error de base de datos']);
}
