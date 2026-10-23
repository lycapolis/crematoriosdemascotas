<?php
/**
 * ═══════════════════════════════════════════════════════════
 * REPORTAR TELÉFONO — aviso anónimo «¿No funciona este teléfono?»
 * ═══════════════════════════════════════════════════════════
 *
 * Lo llama el mini formulario .ficha-reporte-tel de ficha.php. Sin login.
 * Aviso NEUTRO de calidad de datos: no cambia la ficha, solo avisa al equipo.
 * Excepción documentada a «nombre + email + teléfono obligatorios»: es un aviso anónimo de un clic.
 *
 * Anti-spam: honeypot, time-trap (2 s) y máximo REPORTE_TEL_MAX_POR_IP_HORA avisos por IP y hora.
 * Responde JSON {ok, mensaje}. El evento telefono.reportado se lanza DESPUÉS de responder.
 * ═══════════════════════════════════════════════════════════
 */

require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';
require_once 'includes/notificaciones.php';

const REPORTE_TEL_MAX_POR_IP_HORA = 3;

header('Content-Type: application/json; charset=utf-8');

function reporteTelResponder(bool $ok, string $mensaje, int $http = 200): void {
    http_response_code($http);
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reporteTelResponder(false, 'Método no permitido', 405);
    exit;
}

// Honeypot: a un bot le decimos que todo fue bien
if (!empty($_POST['website_url'])) {
    reporteTelResponder(true, 'Gracias, lo revisaremos.');
    exit;
}
$renderTs = (int)($_POST['form_render_ts'] ?? 0);
if ($renderTs && (time() - $renderTs) < 2) {
    reporteTelResponder(false, 'Has enviado el aviso demasiado rápido. Inténtalo de nuevo.');
    exit;
}

$fichaId = (int)($_POST['crematorio_id'] ?? 0);
$motivo  = $_POST['motivo'] ?? '';
$motivo  = in_array($motivo, ['no_contesta', 'numero_equivocado', 'otro'], true) ? $motivo : null;

$pdo = obtenerConexion();
if (!$pdo || !$fichaId) {
    reporteTelResponder(false, 'No hemos podido enviar el aviso. Inténtalo más tarde.');
    exit;
}

try {
    // El teléfono NO se fía del navegador: se toma de la ficha (el público de la ficha)
    $st = $pdo->prepare("SELECT telefono, telefonos_json FROM crematorios WHERE id = ? LIMIT 1");
    $st->execute([$fichaId]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f) {
        reporteTelResponder(false, 'No hemos encontrado esta ficha.');
        exit;
    }
    $tels = array_values(array_filter(json_decode($f['telefonos_json'] ?? '', true) ?: [],
        fn($t) => !empty($t['visible']) && !empty($t['numero'])));
    $telefono = $tels ? (string)$tels[0]['numero'] : (string)($f['telefono'] ?? '');
    if ($telefono === '') {
        reporteTelResponder(false, 'Esta ficha no tiene teléfono publicado.');
        exit;
    }

    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
    $q = $pdo->prepare("SELECT COUNT(*) FROM telefono_reportes WHERE ip_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $q->execute([$ipHash]);
    if ((int)$q->fetchColumn() >= REPORTE_TEL_MAX_POR_IP_HORA) {
        reporteTelResponder(false, 'Ya nos has enviado varios avisos. Vuelve a intentarlo dentro de un rato.');
        exit;
    }

    // Mismo número y misma ficha con un aviso pendiente: no se duplica, pero se agradece igual
    $q = $pdo->prepare("SELECT id FROM telefono_reportes WHERE crematorio_id = ? AND telefono = ? AND estado = 'pendiente' LIMIT 1");
    $q->execute([$fichaId, $telefono]);
    $reporteId = (int)$q->fetchColumn();
    $nuevo = false;
    if (!$reporteId) {
        $pdo->prepare("INSERT INTO telefono_reportes (crematorio_id, telefono, motivo, ip_hash) VALUES (?, ?, ?, ?)")
            ->execute([$fichaId, $telefono, $motivo, $ipHash]);
        $reporteId = (int)$pdo->lastInsertId();
        $nuevo = true;
    }
} catch (\Throwable $e) {
    error_log('[reportar-telefono] ' . $e->getMessage());
    reporteTelResponder(false, 'No hemos podido enviar el aviso. Inténtalo más tarde.');
    exit;
}

reporteTelResponder(true, 'Gracias, lo revisaremos.');

// Aviso al equipo tras responder; un fallo aquí se registra pero no afecta al visitante
if ($nuevo) {
    try {
        require_once __DIR__ . '/includes/notificaciones/despachador.php';
        notifCerrarRespuesta();
        notificarEvento($pdo, 'telefono.reportado', ['reporte_id' => $reporteId]);
    } catch (\Throwable $e) {
        error_log('[reportar-telefono] aviso al equipo: ' . $e->getMessage());
    }
}
