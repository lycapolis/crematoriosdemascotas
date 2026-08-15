<?php
/**
 * AJAX — Auto-guardar una versión generada por IA, apenas se genera, para no
 * perder el trabajo (y los tokens) por un refresh/olvido.
 *
 * SOLO agrega la entrada al JSON multi-fuente (descripciones_json | metas_json)
 * con activo=false. NO toca el campo plano público (descripcion /
 * meta_description_seo) ni la versión activa → no publica nada. El admin sigue
 * teniendo que marcar "Usar esta" + "Guardar cambios" para publicar.
 *
 * POST: crematorio_id (int), campo ('descripciones_json'|'metas_json'),
 *       entrada (JSON: {id?, origen, valor, modelo?, creado_at?})
 * Respuesta JSON: { ok: bool, id: string, error: ?string }
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

$crematorioId = (int) ($_POST['crematorio_id'] ?? 0);
$campo        = (string) ($_POST['campo'] ?? '');
$entradaRaw   = (string) ($_POST['entrada'] ?? '');

$CAMPOS_OK = ['descripciones_json', 'metas_json', 'mensajes_whatsapp_json'];
if (!$crematorioId || !in_array($campo, $CAMPOS_OK, true)) {
    echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
    exit;
}

$entrada = json_decode($entradaRaw, true);
if (!is_array($entrada) || !isset($entrada['valor']) || trim((string) $entrada['valor']) === '') {
    echo json_encode(['ok' => false, 'error' => 'Entrada vacía o inválida']);
    exit;
}

// Sanear la entrada — SIEMPRE inactiva (este endpoint nunca publica)
$id = (string) ($entrada['id'] ?? '');
if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
    $id = '_' . substr(bin2hex(random_bytes(6)), 0, 9);
}
$nueva = [
    'id'         => $id,
    'origen'     => preg_match('/^[a-z0-9_]{1,30}$/', (string)($entrada['origen'] ?? '')) ? $entrada['origen'] : 'llm_claude',
    'valor'      => (string) $entrada['valor'],
    'activo'     => false,
    'creado_at'  => preg_match('/^[\d \-:]{1,25}$/', (string)($entrada['creado_at'] ?? '')) ? $entrada['creado_at'] : date('Y-m-d H:i:s'),
    'editado_at' => null,
];
if (!empty($entrada['modelo']) && is_string($entrada['modelo'])) {
    $nueva['modelo'] = mb_substr($entrada['modelo'], 0, 60);
}

$pdo = obtenerConexion();

try {
    $stmt = $pdo->prepare("SELECT `$campo` AS j FROM crematorios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $crematorioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Ficha no encontrada']);
        exit;
    }

    $arr = json_decode((string) ($row['j'] ?? ''), true);
    if (!is_array($arr)) $arr = [];

    // Dedupe por id (idempotente si se reintenta); nunca activar acá.
    $reemplazado = false;
    foreach ($arr as $i => $e) {
        if (is_array($e) && ($e['id'] ?? null) === $id) {
            $nueva['activo'] = !empty($e['activo']) ? $e['activo'] : false; // respeta si ya estaba activa por otra vía
            $arr[$i] = $nueva;
            $reemplazado = true;
            break;
        }
    }
    if (!$reemplazado) $arr[] = $nueva;

    $stmt = $pdo->prepare("UPDATE crematorios SET `$campo` = :j WHERE id = :id");
    $stmt->execute([':j' => json_encode($arr, JSON_UNESCAPED_UNICODE), ':id' => $crematorioId]);

    echo json_encode(['ok' => true, 'id' => $id, 'error' => null]);

} catch (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
