<?php
/**
 * AJAX — Procesar lote global de imágenes pendientes con Claude Vision
 * POST: limite (int, default 20, max 50)
 * Devuelve JSON: {ok, procesadas, errores, total}
 */
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();

header('Content-Type: application/json; charset=utf-8');

requierePermiso('ia');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$limite = min(50, max(1, intval($_POST['limite'] ?? 20)));

$apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';
if (empty($apiKey)) {
    echo json_encode(['ok' => false, 'error' => 'CLAUDE_API_KEY no configurada en config.php']);
    exit;
}

$pdo = obtenerConexion();
if (!$pdo) {
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT ci.id, ci.crematorio_id, ci.tipo, ci.ruta, ci.nombre_archivo, ci.resena_id,
           c.nombre AS crematorio_nombre, c.slug AS crematorio_slug,
           c.ciudad, p.nombre AS provincia_nombre,
           r.nombre AS resena_nombre
    FROM crematorio_imagenes ci
    JOIN crematorios c ON ci.crematorio_id = c.id
    LEFT JOIN provincias p ON c.provincia_id = p.id
    LEFT JOIN resenas r ON r.id = ci.resena_id
    WHERE ci.estado_llm = 'pendiente' AND ci.ruta NOT LIKE 'http%'
    ORDER BY ci.crematorio_id, ci.created_at ASC
    LIMIT :lim
");
$stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
$stmt->execute();
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($imagenes)) {
    echo json_encode(['ok' => true, 'procesadas' => 0, 'errores' => 0, 'total' => 0]);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function loteDescSeo(string $desc): string {
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'];
    $desc = mb_strtolower($desc, 'UTF-8');
    $desc = strtr($desc, $map);
    $desc = preg_replace('/[^a-z0-9-]/', '-', $desc);
    $desc = preg_replace('/-+/', '-', $desc);
    return trim($desc, '-');
}

function loteClaudeVision(string $base64, string $ctx, string $apiKey, bool $esCliente = false): ?array {
    $extraCliente = $esCliente
        ? "\nIMPORTANTE — esta imagen fue enviada por un CLIENTE del negocio junto con una reseña pública. Reglas especiales para alt_text:\n  • Describí literalmente lo que se VE (mascota, recuerdo, momento, etc.).\n  • NO incluyas el nombre del negocio en el alt_text — queda forzado.\n  • Podés mencionar la ciudad SOLO si encaja natural.\n  • La categoría más probable es 'fotos_clientes'.\n  • El sistema agrega automáticamente al final '— Foto enviada por [nombre del cliente]'. NO lo agregues tú."
        : '';

    $prompt = <<<P
Eres un experto en SEO y en el sector de crematorios de mascotas en España.
Analiza esta imagen y responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional.

Contexto del negocio: $ctx$extraCliente

Campos requeridos:
1. "categoria": una de: logo, exterior, interior_sala, interior_recepcion, interior_amenities, produccion_tecnologia, recuerdos_souvenires, equipo_personas, fotos_clientes, otro
2. "alt_text": texto alt SEO, claro y específico, 60-120 caracteres, incluye nombre del negocio y ciudad.
3. "descripcion_seo": 3-6 palabras en español, minúsculas, separadas por guiones, sin artículos.

Responde SOLO con JSON: {"categoria":"...","alt_text":"...","descripcion_seo":"..."}
P;

    $payload = [
        'model' => 'claude-sonnet-4-6', 'max_tokens' => 300,
        'messages' => [['role' => 'user', 'content' => [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/webp', 'data' => $base64]],
            ['type' => 'text', 'text' => $prompt],
        ]]],
    ];

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['x-api-key: '.$apiKey, 'anthropic-version: 2023-06-01', 'content-type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 60,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return null;
    $data  = json_decode($resp, true);
    $texto = $data['content'][0]['text'] ?? '';
    if (preg_match('/\{.*\}/s', $texto, $m)) {
        $p = json_decode($m[0], true);
        if (json_last_error() === JSON_ERROR_NONE) return $p;
    }
    return null;
}

// ── Procesar ──────────────────────────────────────────────────────────────────

$root       = dirname(__DIR__);
$procesadas = 0;
$errores    = 0;
$cremIdsConExito = []; // crematorios con al menos 1 imagen procesada — para bitácora

@set_time_limit(300);

foreach ($imagenes as $img) {
    $rutaAbs = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $img['ruta']), '/\\');

    if (!file_exists($rutaAbs)) {
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")->execute([':id' => $img['id']]);
        $errores++;
        continue;
    }

    $ext = strtolower(pathinfo($rutaAbs, PATHINFO_EXTENSION));
    if ($ext !== 'webp') {
        $tmp = sys_get_temp_dir() . '/llm_lote_' . $img['id'] . '.webp';
        try { ImagenHelper::convertirWebP($rutaAbs, $tmp, 1200); $rutaLeer = $tmp; }
        catch (Exception $e) { $rutaLeer = $rutaAbs; }
    } else {
        $rutaLeer = $rutaAbs;
    }

    $base64 = base64_encode(file_get_contents($rutaLeer));
    $ctx    = $img['crematorio_nombre'];
    if ($img['ciudad'])           $ctx .= ', ' . $img['ciudad'];
    if ($img['provincia_nombre']) $ctx .= ' (' . $img['provincia_nombre'] . ')';

    $esCliente = ($img['tipo'] === 'cliente');
    $analisis = loteClaudeVision($base64, $ctx, $apiKey, $esCliente);

    if (isset($tmp) && file_exists($tmp)) { @unlink($tmp); unset($tmp); }

    if (!$analisis) {
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")->execute([':id' => $img['id']]);
        $errores++;
        continue;
    }

    $categoria = in_array($analisis['categoria'] ?? '', ImagenHelper::CATEGORIAS_VALIDAS) ? $analisis['categoria'] : 'otro';
    $altText   = substr(trim($analisis['alt_text'] ?? ''), 0, 500);
    if ($esCliente) {
        // Quitar cualquier sufijo "Foto enviada por..." que haya agregado el LLM
        $altText = preg_replace('/\s*[—-]\s*Foto enviada por[^.]*\.?\s*$/i', '', $altText);
        $nombreCliente = trim($img['resena_nombre'] ?? '');
        $nombreCliente = mb_substr($nombreCliente, 0, 60);
        $sufijo = $nombreCliente !== ''
            ? ' — Foto enviada por ' . $nombreCliente . '.'
            : ' — Foto enviada por un cliente.';
        $altText = rtrim($altText, " .,!") . $sufijo;
        $altText = substr($altText, 0, 500);
    }
    $descSeo   = loteDescSeo($analisis['descripcion_seo'] ?? 'imagen');

    $cremId    = (int)$img['crematorio_id'];
    $orden     = (int)$pdo->query("SELECT COALESCE(MAX(orden_negocio), 0) FROM crematorio_imagenes WHERE crematorio_id=$cremId")->fetchColumn() + 1;
    $ordenPad  = str_pad($orden, 3, '0', STR_PAD_LEFT);

    // Si el LLM detecta logo, corregir tipo; si tipo estaba vacío, defaultear a 'foto'
    $tipoFinal   = ($categoria === 'logo') ? 'logo' : (empty($img['tipo']) ? 'galeria' : $img['tipo']);

    // Nombre — logos llevan prefijo "logo-" para agruparse visualmente.
    // Galería/portada/cliente: NNN-descSeo-slug-tipo.webp.
    $tipoSufijo   = $tipoFinal === 'logo' ? 'logo' : ($tipoFinal === 'portada' ? 'portada' : ($tipoFinal === 'cliente' ? 'cliente' : 'galeria'));
    $descCorta    = rtrim(substr($descSeo, 0, 30), '-');
    $slugCorto    = rtrim(substr($img['crematorio_slug'], 0, 30), '-');
    $nuevoNombre  = $tipoFinal === 'logo'
        ? "logo-{$ordenPad}-{$descCorta}-{$slugCorto}.webp"
        : "{$ordenPad}-{$descCorta}-{$slugCorto}-{$tipoSufijo}.webp";
    $idPad        = str_pad($cremId, 4, '0', STR_PAD_LEFT);
    $dirDest      = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'img-fichas' . DIRECTORY_SEPARATOR . $idPad . DIRECTORY_SEPARATOR;
    $nuevaRutaAbs = $dirDest . $nuevoNombre;
    $nuevaRutaRel = 'uploads/img-fichas/' . $idPad . '/' . $nuevoNombre;

    if (!is_dir($dirDest)) @mkdir($dirDest, 0755, true);
    if (file_exists($nuevaRutaAbs)) @unlink($nuevaRutaAbs);
    if (!copy($rutaAbs, $nuevaRutaAbs)) {
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")->execute([':id' => $img['id']]);
        $errores++;
        continue;
    }

    $pdo->prepare("UPDATE crematorio_imagenes SET
        tipo=:tipo, categoria=:cat, alt_text=:alt, estado_llm='procesada', categoria_origen='ia',
        orden_negocio=:orden, nombre_archivo=:nombre, ruta=:ruta
        WHERE id=:id")->execute([
        ':tipo' => $tipoFinal, ':cat' => $categoria, ':alt' => $altText, ':orden' => $orden,
        ':nombre' => $nuevoNombre, ':ruta' => $nuevaRutaRel, ':id' => $img['id'],
    ]);

    // Borrar el original — copia ya verificada y BD actualizada
    if ($rutaAbs !== $nuevaRutaAbs) @unlink($rutaAbs);

    $procesadas++;
    $cremIdsConExito[$cremId] = true;
}

// Registrar en bitácora IA — una entrada por crematorio con imágenes procesadas
foreach (array_keys($cremIdsConExito) as $cremIdReg) {
    registrarUsoIA($pdo, (int) $cremIdReg, 'imagenes', 'claude-sonnet-4-6');
}

echo json_encode(['ok' => true, 'procesadas' => $procesadas, 'errores' => $errores, 'total' => count($imagenes)]);
