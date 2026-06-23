<?php
/**
 * AJAX — Generar alt texts faltantes o duplicados con Claude Vision
 * POST: crematorio_id (int, requerido)
 *       modo: 'crematorio' (default) | 'global'
 *       limite: int (solo para modo global, default 20)
 * Devuelve JSON: {ok, actualizadas, errores, detalles[]}
 */
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();

ob_clean();
header('Content-Type: application/json; charset=utf-8');

requierePermiso('ia');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']); exit;
}

$modo         = $_POST['modo'] ?? 'crematorio';
$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$limite       = min(50, max(1, intval($_POST['limite'] ?? 20)));

$apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
if (empty($apiKey)) {
    echo json_encode(['ok' => false, 'error' => 'CLAUDE_API_KEY no configurada']); exit;
}

$pdo = obtenerConexion();
if (!$pdo) {
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos']); exit;
}

// ── Construir query según modo ────────────────────────────────────────────────

$whereExtra = $modo === 'crematorio' && $crematorioId
    ? "AND ci.crematorio_id = $crematorioId"
    : '';

// Imágenes sin alt_text (con categoría asignada, locales)
$sinAlt = $pdo->query("
    SELECT ci.id, ci.crematorio_id, ci.tipo, ci.categoria, ci.ruta, ci.alt_text,
           ci.nombre_archivo, c.nombre AS crematorio_nombre, c.ciudad,
           p.nombre AS provincia_nombre,
           c.descripcion, c.cremacion_individual, c.cremacion_colectiva,
           c.recogida_domicilio, c.entrega_domicilio, c.ciudades_cobertura,
           c.meta_description_seo, c.zona_cobertura
    FROM crematorio_imagenes ci
    JOIN crematorios c ON ci.crematorio_id = c.id
    LEFT JOIN provincias p ON c.provincia_id = p.id
    WHERE (ci.alt_text IS NULL OR ci.alt_text = '')
      AND ci.categoria IS NOT NULL AND ci.categoria != ''
      AND ci.ruta NOT LIKE 'http%'
      $whereExtra
    ORDER BY ci.crematorio_id, ci.id
    LIMIT $limite
")->fetchAll(PDO::FETCH_ASSOC);

// IDs de imágenes con alt_text duplicado (dentro de su crematorio)
$dupIds = $pdo->query("
    SELECT ci.id
    FROM crematorio_imagenes ci
    INNER JOIN (
        SELECT crematorio_id, alt_text
        FROM crematorio_imagenes
        WHERE alt_text IS NOT NULL AND alt_text != ''
          AND ruta NOT LIKE 'http%'
        GROUP BY crematorio_id, alt_text
        HAVING COUNT(*) > 1
    ) dups ON ci.crematorio_id = dups.crematorio_id AND ci.alt_text = dups.alt_text
    WHERE ci.ruta NOT LIKE 'http%'
      AND ci.categoria IS NOT NULL AND ci.categoria != ''
      $whereExtra
    ORDER BY ci.crematorio_id, ci.id
    LIMIT $limite
")->fetchAll(PDO::FETCH_COLUMN);

$conDup = [];
if (!empty($dupIds)) {
    $in = implode(',', array_map('intval', $dupIds));
    $conDup = $pdo->query("
        SELECT ci.id, ci.crematorio_id, ci.tipo, ci.categoria, ci.ruta, ci.alt_text,
               ci.nombre_archivo, c.nombre AS crematorio_nombre, c.ciudad,
               p.nombre AS provincia_nombre,
               c.descripcion, c.cremacion_individual, c.cremacion_colectiva,
               c.recogida_domicilio, c.entrega_domicilio, c.ciudades_cobertura,
               c.meta_description_seo, c.zona_cobertura
        FROM crematorio_imagenes ci
        JOIN crematorios c ON ci.crematorio_id = c.id
        LEFT JOIN provincias p ON c.provincia_id = p.id
        WHERE ci.id IN ($in)
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Unir, desduplicar por id (priorizar sin alt sobre duplicado)
$porId = [];
foreach ($sinAlt  as $img) $porId[$img['id']] = $img;
foreach ($conDup  as $img) $porId[$img['id']] ??= $img;
$imagenes = array_values($porId);

// Imágenes locales sin categoría (se omiten aquí, necesitan el botón amarillo primero)
$nSinCategoria = (int)$pdo->query("
    SELECT COUNT(*) FROM crematorio_imagenes ci
    WHERE (ci.alt_text IS NULL OR ci.alt_text = '')
      AND (ci.categoria IS NULL OR ci.categoria = '')
      AND ci.tipo NOT IN ('logo','portada')
      AND ci.ruta NOT LIKE 'http%'
      $whereExtra
")->fetchColumn();

if (empty($imagenes)) {
    $msg = 'No hay imágenes que necesiten alt text';
    if ($nSinCategoria > 0) $msg .= " ($nSinCategoria sin categorizar — usá el botón amarillo primero)";
    echo json_encode(['ok' => true, 'actualizadas' => 0, 'errores' => 0, 'detalles' => [], 'mensaje' => $msg, 'sin_categoria' => $nSinCategoria]);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function buildContextoNegocio(array $img): string {
    $ctx = $img['crematorio_nombre'];
    if ($img['ciudad'])           $ctx .= ', ' . $img['ciudad'];
    if ($img['provincia_nombre']) $ctx .= ' (' . $img['provincia_nombre'] . ')';

    if (!empty($img['descripcion'])) {
        $ctx .= '. ' . mb_substr(strip_tags($img['descripcion']), 0, 250);
    }

    $servicios = [];
    if (!empty($img['cremacion_individual'])) $servicios[] = 'cremación individual';
    if (!empty($img['cremacion_colectiva']))  $servicios[] = 'cremación colectiva';
    if (!empty($img['recogida_domicilio']))   $servicios[] = 'recogida a domicilio';
    if (!empty($img['entrega_domicilio']))    $servicios[] = 'entrega de urna a domicilio';
    if (!empty($servicios)) $ctx .= '. Servicios: ' . implode(', ', $servicios) . '.';

    if (!empty($img['ciudades_cobertura'])) {
        $ctx .= ' Cobertura: ' . mb_substr($img['ciudades_cobertura'], 0, 120) . '.';
    }
    if (!empty($img['meta_description_seo'])) {
        $ctx .= ' SEO: ' . mb_substr($img['meta_description_seo'], 0, 120) . '.';
    }

    return $ctx;
}

$etiquetasCategoria = [
    'logo'                  => 'logotipo o marca del negocio',
    'exterior'              => 'fachada exterior, entrada o aparcamiento',
    'interior_sala'         => 'sala de despedida o velatorio de mascotas',
    'interior_recepcion'    => 'recepción o sala de espera',
    'interior_amenities'    => 'zona de descanso, jardín interior o detalle decorativo',
    'produccion_tecnologia' => 'equipamiento técnico o instalaciones de cremación',
    'recuerdos_souvenires'  => 'urna, placa conmemorativa o souvenir',
    'equipo_personas'       => 'equipo humano o personas trabajando',
    'fotos_clientes'        => 'foto de mascota enviada por un cliente',
    'otro'                  => 'imagen del negocio',
];

function llamarClaudeAltText(string $base64, string $mime, string $contexto, string $categoria, array $altTextsUsados, string $apiKey): ?string {
    global $etiquetasCategoria;
    $tipoDesc = $etiquetasCategoria[$categoria] ?? 'imagen del negocio';

    $prohibidos = '';
    if (!empty($altTextsUsados)) {
        $prohibidos = "\n\nAlt texts YA USADOS para este negocio — el tuyo debe ser COMPLETAMENTE DIFERENTE a todos:\n";
        foreach ($altTextsUsados as $at) {
            $prohibidos .= '- "' . $at . '"' . "\n";
        }
    }

    $prompt = <<<PROMPT
Eres un experto en SEO para servicios funerarios de mascotas en España.
Genera un alt text para esta imagen.

Tipo de imagen: $tipoDesc
Contexto del negocio: $contexto
$prohibidos
Requisitos estrictos:
- Entre 60 y 120 caracteres
- Describe lo que se ve en la imagen de forma específica y visual
- Incluye el nombre del negocio o la ciudad cuando sea natural
- En español, sin mayúsculas innecesarias salvo nombres propios
- Relevante para SEO local de crematorios de mascotas
- PROHIBIDO repetir cualquier alt text de la lista anterior

Responde ÚNICAMENTE con el alt text, sin comillas, sin explicaciones.
PROMPT;

    $payload = [
        'model' => 'claude-sonnet-4-6', 'max_tokens' => 200,
        'messages' => [['role' => 'user', 'content' => [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $base64]],
            ['type' => 'text', 'text' => $prompt],
        ]]],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['x-api-key: '.$apiKey, 'anthropic-version: 2023-06-01', 'content-type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 45,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return null;

    $data  = json_decode($resp, true);
    $texto = trim($data['content'][0]['text'] ?? '');
    $texto = trim($texto, '"\'');

    if (mb_strlen($texto) < 20 || mb_strlen($texto) > 200) return null;
    return mb_substr($texto, 0, 120);
}

// ── Procesar ──────────────────────────────────────────────────────────────────

$root        = dirname(__DIR__);
$actualizadas = 0;
$errores      = 0;
$detalles     = [];

// Cache de alt texts usados por crematorio (se va llenando conforme procesamos)
$altTextsUsadosPor = [];

@set_time_limit(300);

foreach ($imagenes as $img) {
    $cremId = (int)$img['crematorio_id'];

    // Cargar alt texts existentes de este crematorio si no están en cache
    if (!isset($altTextsUsadosPor[$cremId])) {
        $altTextsUsadosPor[$cremId] = $pdo->query("
            SELECT DISTINCT alt_text FROM crematorio_imagenes
            WHERE crematorio_id = $cremId AND alt_text IS NOT NULL AND alt_text != ''
        ")->fetchAll(PDO::FETCH_COLUMN);
    }

    $rutaAbs = $root . '/' . ltrim(str_replace('\\', '/', $img['ruta']), '/');
    if (!file_exists($rutaAbs)) {
        $detalles[] = ['id' => $img['id'], 'nombre' => $img['nombre_archivo'], 'estado' => 'error', 'msg' => 'Archivo no encontrado'];
        $errores++;
        continue;
    }

    $ext = strtolower(pathinfo($rutaAbs, PATHINFO_EXTENSION));
    if ($ext !== 'webp') {
        $tmp = sys_get_temp_dir() . '/alt_' . $img['id'] . '.webp';
        try { ImagenHelper::convertirWebP($rutaAbs, $tmp, 1200); $rutaLeer = $tmp; }
        catch (Exception $e) { $rutaLeer = $rutaAbs; }
    } else {
        $rutaLeer = $rutaAbs;
    }

    $base64  = base64_encode(file_get_contents($rutaLeer));
    $contexto = buildContextoNegocio($img);

    // Para duplicados: excluir el alt text actual de la lista prohibida (ya que lo vamos a reemplazar)
    $prohibidos = array_filter($altTextsUsadosPor[$cremId], fn($a) => $a !== $img['alt_text']);

    $nuevoAlt = llamarClaudeAltText($base64, 'image/webp', $contexto, $img['categoria'], array_values($prohibidos), $apiKey);

    if (isset($tmp) && file_exists($tmp)) { @unlink($tmp); unset($tmp); }

    if (!$nuevoAlt) {
        $detalles[] = ['id' => $img['id'], 'nombre' => $img['nombre_archivo'], 'estado' => 'error', 'msg' => 'Fallo en API Claude'];
        $errores++;
        continue;
    }

    // Actualizar solo alt_text
    $pdo->prepare("UPDATE crematorio_imagenes SET alt_text = :alt WHERE id = :id")
        ->execute([':alt' => $nuevoAlt, ':id' => $img['id']]);

    // Añadir al cache para evitar duplicados en este batch
    $altTextsUsadosPor[$cremId][] = $nuevoAlt;

    $detalles[] = [
        'id'       => $img['id'],
        'nombre'   => $img['nombre_archivo'],
        'estado'   => 'ok',
        'alt_text' => $nuevoAlt,
        'tipo'     => empty($img['alt_text']) ? 'nuevo' : 'reemplazado',
    ];
    $actualizadas++;
}

echo json_encode([
    'ok'           => true,
    'actualizadas' => $actualizadas,
    'errores'      => $errores,
    'total'        => count($imagenes),
    'sin_categoria' => $nSinCategoria,
    'detalles'     => $detalles,
]);
