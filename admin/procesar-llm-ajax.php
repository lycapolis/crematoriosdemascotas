<?php
/**
 * AJAX — Procesar imágenes pendientes con Claude Vision
 * POST: crematorio_id (int, requerido)
 * Devuelve JSON: {ok, procesadas, errores, detalles[]}
 */
// Suprimir warnings para que no contaminen el JSON
error_reporting(0);
ini_set('display_errors', '0');
ob_start();

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();

ob_clean(); // Limpiar cualquier output previo (warnings de requires)
header('Content-Type: application/json; charset=utf-8');

requierePermiso('ia');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$crematorioId = intval($_POST['crematorio_id'] ?? 0);
if (!$crematorioId) {
    echo json_encode(['ok' => false, 'error' => 'crematorio_id requerido']);
    exit;
}

$limite    = min(10, max(1, intval($_POST['limite'] ?? 10)));
$soloTipo  = $_POST['solo_tipo'] ?? ''; // 'cliente' → solo procesa tipo=cliente; vacío → excluye cliente

$pdo = obtenerConexion();
if (!$pdo) {
    echo json_encode(['ok' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

// Proveedor/modelo configurables en admin/configuracion-ia.php (sección 'vision_categoria').
$cfgVision = obtenerConfigIA($pdo, 'vision_categoria');
$apiKeyOk = ($cfgVision['proveedor'] === 'openrouter')
    ? (defined('OPENROUTER_API_KEY') && OPENROUTER_API_KEY !== '')
    : (defined('CLAUDE_API_KEY') && CLAUDE_API_KEY !== '');
if (!$apiKeyOk) {
    echo json_encode(['ok' => false, 'error' => strtoupper($cfgVision['proveedor']) . '_API_KEY no configurada en .env']);
    exit;
}

// Todas las imágenes locales sin categoría (independiente del estado_llm)
// Para tipo='cliente' joineamos resenas para personalizar el sufijo del alt_text con el nombre del cliente.
$stmt = $pdo->prepare("
    SELECT ci.id, ci.tipo, ci.ruta, ci.nombre_archivo, ci.resena_id,
           c.nombre AS crematorio_nombre, c.slug AS crematorio_slug,
           c.ciudad, p.nombre AS provincia_nombre,
           r.nombre AS resena_nombre
    FROM crematorio_imagenes ci
    JOIN crematorios c ON ci.crematorio_id = c.id
    LEFT JOIN provincias p ON c.provincia_id = p.id
    LEFT JOIN resenas r ON r.id = ci.resena_id
    WHERE (ci.categoria IS NULL OR ci.categoria = '')
      AND ci.crematorio_id = :id
      AND ci.ruta NOT LIKE 'http%'
      AND (
          (:solo_tipo_a = 'cliente' AND ci.tipo = 'cliente')
          OR
          (:solo_tipo_b != 'cliente' AND ci.tipo NOT IN ('logo', 'portada', 'cliente'))
      )
    ORDER BY ci.created_at ASC
    LIMIT :lim
");
$stmt->bindValue(':id',        $crematorioId, PDO::PARAM_INT);
$stmt->bindValue(':lim',       $limite,       PDO::PARAM_INT);
$stmt->bindValue(':solo_tipo_a', $soloTipo,   PDO::PARAM_STR);
$stmt->bindValue(':solo_tipo_b', $soloTipo,   PDO::PARAM_STR);
$stmt->execute();
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($imagenes)) {
    echo json_encode(['ok' => true, 'procesadas' => 0, 'errores' => 0, 'detalles' => [], 'mensaje' => 'No hay imágenes pendientes']);
    exit;
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function limpiarDescSeo(string $desc): string {
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
            'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u'];
    $desc = mb_strtolower($desc, 'UTF-8');
    $desc = strtr($desc, $map);
    $desc = preg_replace('/[^a-z0-9-]/', '-', $desc);
    $desc = preg_replace('/-+/', '-', $desc);
    return trim($desc, '-');
}

function siguienteOrdenAjax(PDO $pdo, int $crematorioId): int {
    return (int)$pdo->prepare("SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id = :id AND estado_llm = 'procesada'")
        ->execute([':id' => $crematorioId]) ? (int)$pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id = $crematorioId AND estado_llm = 'procesada'")->fetchColumn() + 1 : 1;
}

function llamarClaudeVisionAjax(PDO $pdo, string $base64, string $mime, string $contexto, bool $esCliente = false): ?array {
    $extraCliente = $esCliente
        ? "\nIMPORTANTE — esta imagen fue enviada por un CLIENTE del negocio junto con una reseña pública. Reglas especiales para tu alt_text:\n  • Describí literalmente lo que se VE en la imagen (la mascota, un recuerdo, un momento, etc.).\n  • NO incluyas el nombre del negocio en el alt_text — queda forzado y poco natural.\n  • Podés mencionar la ciudad SOLO si encaja de forma natural en la descripción.\n  • La categoría más probable es 'fotos_clientes'.\n  • El sistema agregará automáticamente al final del alt el texto '— Foto enviada por [nombre del cliente]'. NO lo agregues tú."
        : '';

    $prompt = <<<PROMPT
Eres un experto en SEO y en el sector de crematorios de mascotas en España.
Analiza esta imagen y responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional.

Contexto del negocio: $contexto$extraCliente

Campos requeridos:
1. "categoria": clasifica la imagen en UNA de estas categorías exactas:
   - "logo" → logotipo, isotipo o marca del negocio
   - "exterior" → fachada exterior, entrada, aparcamiento, foto estilo Street View
   - "interior_sala" → sala de velatorio, sala de despedida, capilla ardiente
   - "interior_recepcion" → recepción, sala de espera, mostrador, zona de atención al público
   - "interior_amenities" → baños, zona de descanso, jardín interior, detalles decorativos
   - "produccion_tecnologia" → horno crematorio, equipamiento técnico, instalaciones de producción
   - "recuerdos_souvenires" → urnas, placas conmemorativas, souvenires, productos de recuerdo
   - "equipo_personas" → fotos del equipo, personas trabajando
   - "fotos_clientes" → fotos enviadas por clientes, mascotas de clientes
   - "otro" → cualquier imagen que no encaje en las anteriores

2. "alt_text": texto alt descriptivo para SEO. Claro, específico, 60-120 caracteres.
   Incluye el nombre del negocio y la ciudad si es relevante. Describe lo que se ve en la imagen.

3. "descripcion_seo": 3-6 palabras en español, minúsculas, separadas por guiones, sin artículos.
   Ejemplo: "sala-despedida-mascotas-madrid"

Responde SOLO con el JSON, ejemplo:
{"categoria":"interior_sala","alt_text":"Sala de despedida de mascotas en Crematorio Huella Amiga, Madrid","descripcion_seo":"sala-despedida-mascotas"}
PROMPT;

    $resp = llamarLLM($pdo, 'vision_categoria', $prompt, $base64, $mime);
    if (!$resp['ok']) return null;

    $texto = $resp['texto'] ?? '';
    if (preg_match('/\{.*\}/s', $texto, $m)) {
        $parsed = json_decode($m[0], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $parsed['_modelo'] = $resp['modelo'];
            return $parsed;
        }
    }
    return null;
}

// ── Procesar ──────────────────────────────────────────────────────────────────

$root      = dirname(__DIR__);
$procesadas = 0;
$errores    = 0;
$detalles   = [];

// Aumentar tiempo de ejecución para lotes grandes
@set_time_limit(300);

foreach ($imagenes as $img) {
    $rutaRel = $img['ruta'];
    $esUrl   = preg_match('/^https?:\/\//', $rutaRel);

    if ($esUrl) {
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")
            ->execute([':id' => $img['id']]);
        $detalles[] = ['id' => $img['id'], 'nombre' => $img['nombre_archivo'], 'estado' => 'error', 'msg' => 'URL externa — sin archivo en disco'];
        $errores++;
        continue;
    }

    $rutaAbs = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRel), '/\\');

    if (!file_exists($rutaAbs)) {
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")
            ->execute([':id' => $img['id']]);
        $detalles[] = ['id' => $img['id'], 'nombre' => $img['nombre_archivo'], 'estado' => 'error', 'msg' => 'Archivo no encontrado en disco'];
        $errores++;
        continue;
    }

    // Convertir a WebP temporal si hace falta
    $ext = strtolower(pathinfo($rutaAbs, PATHINFO_EXTENSION));
    if ($ext !== 'webp') {
        $tmp = sys_get_temp_dir() . '/llm_ajax_' . $img['id'] . '.webp';
        try { ImagenHelper::convertirWebP($rutaAbs, $tmp, 1200); $rutaLeer = $tmp; }
        catch (Exception $e) { $rutaLeer = $rutaAbs; }
    } else {
        $rutaLeer = $rutaAbs;
    }

    $base64 = base64_encode(file_get_contents($rutaLeer));

    $ctx  = $img['crematorio_nombre'];
    if ($img['ciudad'])           $ctx .= ', ' . $img['ciudad'];
    if ($img['provincia_nombre']) $ctx .= ' (' . $img['provincia_nombre'] . ')';

    $esCliente = ($img['tipo'] === 'cliente');
    $analisis = llamarClaudeVisionAjax($pdo, $base64, 'image/webp', $ctx, $esCliente);

    // Limpiar temp
    if (isset($tmp) && file_exists($tmp)) { @unlink($tmp); unset($tmp); }

    if (!$analisis) {
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")
            ->execute([':id' => $img['id']]);
        $detalles[] = ['id' => $img['id'], 'nombre' => $img['nombre_archivo'], 'estado' => 'error', 'msg' => 'Fallo en la llamada al LLM'];
        $errores++;
        continue;
    }

    $modeloUsadoVision = $analisis['_modelo'] ?? 'desconocido';
    $categoria = in_array($analisis['categoria'] ?? '', ImagenHelper::CATEGORIAS_VALIDAS)
        ? $analisis['categoria'] : 'otro';
    $altText  = substr(trim($analisis['alt_text']      ?? ''), 0, 500);
    // Si es imagen de cliente (reseña), agregar el sufijo de origen con el nombre del cliente
    if ($esCliente) {
        // Si el LLM agregó el sufijo a pesar de la instrucción, quitarlo primero
        $altText = preg_replace('/\s*[—-]\s*Foto enviada por[^.]*\.?\s*$/i', '', $altText);
        $nombreCliente = trim($img['resena_nombre'] ?? '');
        $nombreCliente = mb_substr($nombreCliente, 0, 60);
        $sufijo = $nombreCliente !== ''
            ? ' — Foto enviada por ' . $nombreCliente . '.'
            : ' — Foto enviada por un cliente.';
        $altText = rtrim($altText, " .,!") . $sufijo;
        $altText = substr($altText, 0, 500);
    }
    $descSeo  = limpiarDescSeo($analisis['descripcion_seo'] ?? 'imagen');

    // MAX evita colisión si se borraron imágenes anteriores (COUNT daría número ya usado)
    $orden    = (int)$pdo->query("SELECT COALESCE(MAX(orden_negocio), 0) FROM crematorio_imagenes WHERE crematorio_id = $crematorioId")->fetchColumn() + 1;
    $ordenPad = str_pad($orden, 3, '0', STR_PAD_LEFT);

    // Si el LLM detecta logo, corregir tipo; si tipo estaba vacío, defaultear a 'foto'
    $tipoFinal   = ($categoria === 'logo') ? 'logo' : (empty($img['tipo']) ? 'galeria' : $img['tipo']);

    // Nombre — logos llevan prefijo "logo-" para agruparse visualmente.
    // Galería/portada/cliente: NNN-descSeo-slug-tipo.webp.
    $tipoSufijo  = $tipoFinal === 'logo' ? 'logo' : ($tipoFinal === 'portada' ? 'portada' : ($tipoFinal === 'cliente' ? 'cliente' : 'galeria'));
    $descCorta   = rtrim(substr($descSeo, 0, 30), '-');
    $slugCorto   = rtrim(substr($img['crematorio_slug'], 0, 30), '-');
    $nuevoNombre = $tipoFinal === 'logo'
        ? "logo-{$ordenPad}-{$descCorta}-{$slugCorto}.webp"
        : "{$ordenPad}-{$descCorta}-{$slugCorto}-{$tipoSufijo}.webp";
    $idPad        = str_pad($crematorioId, 4, '0', STR_PAD_LEFT);
    $subDir       = $tipoFinal === 'cliente' ? 'img-clientes' . DIRECTORY_SEPARATOR : '';
    $subRel       = $tipoFinal === 'cliente' ? 'img-clientes/' : '';
    $dirDest      = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'img-fichas' . DIRECTORY_SEPARATOR . $idPad . DIRECTORY_SEPARATOR . $subDir;
    $nuevaRutaAbs = $dirDest . $nuevoNombre;
    $nuevaRutaRel = 'uploads/img-fichas/' . $idPad . '/' . $subRel . $nuevoNombre;

    if (!is_dir($dirDest)) @mkdir($dirDest, 0755, true);

    // Si el destino ya existe (intento anterior), eliminarlo antes
    if (file_exists($nuevaRutaAbs)) @unlink($nuevaRutaAbs);

    if (!copy($rutaAbs, $nuevaRutaAbs)) {
        $phpErr = error_get_last();
        $pdo->prepare("UPDATE crematorio_imagenes SET estado_llm='error' WHERE id=:id")
            ->execute([':id' => $img['id']]);
        $detalles[] = ['id' => $img['id'], 'nombre' => $img['nombre_archivo'], 'estado' => 'error',
            'msg' => 'No se pudo copiar: ' . ($phpErr['message'] ?? 'error desconocido') .
                     ' | src=' . $rutaAbs . ' | readable=' . (is_readable($rutaAbs) ? 'si' : 'no')];
        $errores++;
        continue;
    }

    $pdo->prepare("UPDATE crematorio_imagenes SET
        tipo=:tipo, categoria=:cat, alt_text=:alt, estado_llm='procesada', categoria_origen='ia',
        orden_negocio=:orden, nombre_archivo=:nombre, ruta=:ruta
        WHERE id=:id")->execute([
        ':tipo'   => $tipoFinal,
        ':cat'    => $categoria,
        ':alt'    => $altText,
        ':orden'  => $orden,
        ':nombre' => $nuevoNombre,
        ':ruta'   => $nuevaRutaRel,
        ':id'     => $img['id'],
    ]);

    // Borrar el original — copia ya verificada y BD actualizada
    if ($rutaAbs !== $nuevaRutaAbs) @unlink($rutaAbs);

    $detalles[] = ['id' => $img['id'], 'nombre' => $nuevoNombre, 'estado' => 'ok', 'categoria' => $categoria, 'alt_text' => $altText];
    $procesadas++;
}

// Registrar en bitácora IA si al menos una imagen fue procesada con éxito
if ($procesadas > 0) {
    registrarUsoIA($pdo, $crematorioId, 'imagenes', $modeloUsadoVision ?? 'desconocido');
}

echo json_encode([
    'ok'         => true,
    'procesadas' => $procesadas,
    'errores'    => $errores,
    'total'      => count($imagenes),
    'detalles'   => $detalles,
]);
