<?php
/**
 * Otorgar / quitar una insignia MANUAL a una ficha, con guardado inmediato (AJAX).
 *
 * POST JSON { crematorio_id, slug, activa } + cabecera X-CSRF-Token.
 * Responde con la fila de insignias ya renderizada (mismo partial que la ficha pública)
 * y el puntaje nuevo, porque al cambiar una manual pueden aparecer o desaparecer
 * automáticas (p. ej. «Destacado» exige «Verificado por humano»).
 */

ob_start();
ini_set('display_errors', '0');
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/insignias.php';

requerirAutenticacion();
requierePermiso('insignias');

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

function respuestaInsignia(array $d, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respuestaInsignia(['ok' => false, 'error' => 'Método no permitido'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!validarTokenCSRF((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf_token'] ?? '')))) {
    respuestaInsignia(['ok' => false, 'error' => 'La sesión expiró. Recarga la página.'], 403);
}

$pdo  = obtenerConexion();
$id   = (int) ($in['crematorio_id'] ?? 0);
$slug = (string) ($in['slug'] ?? '');
$on   = !empty($in['activa']);

$existe = $pdo->prepare("SELECT 1 FROM crematorios WHERE id = :id");
$existe->execute([':id' => $id]);
if (!$existe->fetchColumn()) respuestaInsignia(['ok' => false, 'error' => 'La ficha no existe.']);

$manual = $pdo->prepare("SELECT nombre FROM insignias WHERE slug = :s AND tipo = 'manual' AND activa = 1");
$manual->execute([':s' => $slug]);
$nombre = $manual->fetchColumn();
if ($nombre === false) respuestaInsignia(['ok' => false, 'error' => 'Esa insignia no se puede otorgar a mano.']);

try {
    $admin = obtenerAdminActual();
    insigniaFijarManual($pdo, $id, $slug, $on, (int) ($admin['id'] ?? 0) ?: null);
    recalcularInsignias($pdo, $id);
} catch (PDOException $e) {
    respuestaInsignia(['ok' => false, 'error' => 'Error de base de datos.'], 500);
}

// Fila renderizada con el partial compartido
$insigniasFila = insigniasDeFicha($pdo, $id, 'todas');
$insigniasMax = 0; $insigniasTam = ''; $insigniasAnidada = false;
ob_start();
include dirname(__DIR__) . '/includes/componentes/insignias-fila.php';
$html = ob_get_clean();

$puntaje = (float) $pdo->query("SELECT puntaje_ranking FROM crematorios WHERE id = " . $id)->fetchColumn();

respuestaInsignia([
    'ok'      => true,
    'mensaje' => $on ? "«{$nombre}» otorgada." : "«{$nombre}» retirada.",
    'html'    => $html,
    'puntaje' => number_format($puntaje, 1),
    'slugs'   => array_column($insigniasFila, 'slug'),
]);
