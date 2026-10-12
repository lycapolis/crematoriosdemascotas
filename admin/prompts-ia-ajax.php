<?php
/**
 * Panel de prompts de IA — probador y «Mejorar con IA» (AJAX, solo super_admin).
 *
 * POST JSON { accion, clave, texto, evitar, ... } + cabecera X-CSRF-Token:
 *   - probar : renderiza el texto del editor (sin guardar) con los datos de ejemplo del
 *              catálogo + contrato, lo envía a la IA de la tarea y devuelve la respuesta.
 *   - mejorar: { instruccion? } pide a la IA una versión mejorada de la parte editable.
 *              NO guarda nada: la propuesta se revisa (diff) y se pasa al editor a mano.
 */

ob_start();
ini_set('display_errors', '0');
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/ia-prompts.php';

requerirAutenticacion();
requiereSuperAdmin();

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');

function respuestaJson(array $d, int $http = 200): void
{
    http_response_code($http);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respuestaJson(['ok' => false, 'error' => 'Método no permitido'], 405);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!validarTokenCSRF((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf_token'] ?? '')))) {
    respuestaJson(['ok' => false, 'error' => 'La sesión expiró. Recarga la página.'], 403);
}

// Las llamadas gastan tokens reales: máximo 10 por minuto por sesión
$ahora = time();
$_SESSION['prompts_ia_llamadas'] = array_values(array_filter($_SESSION['prompts_ia_llamadas'] ?? [], fn($t) => $t > $ahora - 60));
if (count($_SESSION['prompts_ia_llamadas']) >= 10) {
    respuestaJson(['ok' => false, 'error' => 'Demasiadas pruebas seguidas. Espera un minuto.'], 429);
}
$_SESSION['prompts_ia_llamadas'][] = $ahora;

$pdo      = obtenerConexion();
$catalogo = iaPromptsCatalogo();
$clave    = (string) ($in['clave'] ?? '');
if (!isset($catalogo[$clave])) respuestaJson(['ok' => false, 'error' => 'Prompt desconocido.']);
$cat      = $catalogo[$clave];
$texto    = trim(str_replace("\r\n", "\n", (string) ($in['texto'] ?? '')));
$evitar   = trim(str_replace("\r\n", "\n", (string) ($in['evitar'] ?? '')));
$accion   = (string) ($in['accion'] ?? '');

$errores = iaPromptValidar($clave, $texto);
if ($errores) respuestaJson(['ok' => false, 'error' => implode(' ', $errores)]);

if ($accion === 'probar') {
    if (!iaPromptProbable($pdo, $clave)) respuestaJson(['ok' => false, 'error' => 'Este prompt no se puede probar desde aquí.']);

    $prompt = iaPromptRenderTexto($clave, $texto, $evitar, $cat['ejemplo']);
    $t0 = microtime(true);
    $r  = llamarLLM($pdo, $cat['tarea'], $prompt);
    $seg = round(microtime(true) - $t0, 1);
    if (!$r['ok']) respuestaJson(['ok' => false, 'error' => 'La IA falló: ' . ($r['error'] ?? 'error desconocido')]);

    $j = extraerJsonDeRespuesta((string) $r['texto']);
    respuestaJson([
        'ok'        => true,
        'prompt'    => $prompt,
        'respuesta' => (string) $r['texto'],
        'json'      => $j ? json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
        'segundos'  => $seg,
        'modelo'    => obtenerConfigIA($pdo, $cat['tarea'])['modelo'],
    ]);
}

if ($accion === 'mejorar') {
    $st = $pdo->prepare("SELECT 1 FROM ia_config_secciones WHERE seccion = 'prompts_mejorar'");
    $st->execute();
    if (!$st->fetchColumn()) {
        respuestaJson(['ok' => false, 'error' => 'Falta correr la migración admin/migrations/add_prompts_mejorar.sql en la base de datos.']);
    }

    $r = llamarLLM($pdo, 'prompts_mejorar', iaPromptMejorarMeta($clave, $texto, (string) ($in['instruccion'] ?? '')));
    if (!$r['ok']) respuestaJson(['ok' => false, 'error' => 'La IA falló: ' . ($r['error'] ?? 'error desconocido')]);

    $j = extraerJsonDeRespuesta((string) $r['texto']);
    $nuevo = trim(str_replace("\r\n", "\n", (string) ($j['texto'] ?? '')));
    if ($nuevo === '') respuestaJson(['ok' => false, 'error' => 'La IA no devolvió una propuesta legible. Inténtalo de nuevo.']);

    // Salvaguarda: no se acepta una propuesta que pierda variables obligatorias
    $fallos = iaPromptValidar($clave, $nuevo);
    if ($fallos) respuestaJson(['ok' => false, 'error' => 'La propuesta de la IA se descartó: ' . implode(' ', $fallos) . ' Pide otra.']);

    // Aviso (no bloqueante) por variables opcionales que la propuesta dejó fuera
    $perdidas = [];
    foreach (array_keys($cat['variables']) as $v) {
        if (str_contains($texto, '{' . $v . '}') && !str_contains($nuevo, '{' . $v . '}')) $perdidas[] = '{' . $v . '}';
    }

    respuestaJson([
        'ok'       => true,
        'texto'    => $nuevo,
        'resumen'  => trim((string) ($j['resumen'] ?? '')),
        'perdidas' => $perdidas,
        'modelo'   => obtenerConfigIA($pdo, 'prompts_mejorar')['modelo'],
    ]);
}

respuestaJson(['ok' => false, 'error' => 'Acción desconocida.']);
