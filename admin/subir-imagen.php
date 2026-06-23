<?php
/**
 * ═══════════════════════════════════════════════════════════
 * SUBIR IMAGEN(ES) A CREMATORIO - PANEL ADMIN
 * ═══════════════════════════════════════════════════════════
 * POST: crematorio_id, slug, tipo (logo|foto|portada), categoria, imagenes[] (files)
 * Redirige a editar-ficha-negocio.php?id=X con resumen
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: fichas-negocios.php');
    exit;
}

$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$slug         = preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['slug'] ?? ''));
$tipo         = in_array($_POST['tipo'] ?? '', ['logo', 'foto', 'portada']) ? $_POST['tipo'] : 'foto';
$categoria    = trim($_POST['categoria'] ?? '');

if ($tipo === 'logo')    $categoria = 'logo';
if ($tipo === 'portada') $categoria = 'portada';
$categoriaFinal = ($categoria !== '') ? $categoria : null;

$redir = BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId;

if (!$crematorioId || !$slug) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Parámetros inválidos'));
    exit;
}

// Normalizar $_FILES para manejar tanto campo único (imagen) como múltiple (imagenes[])
$archivosRaw = $_FILES['imagenes'] ?? $_FILES['imagen'] ?? null;

if (!$archivosRaw || (isset($archivosRaw['error']) && !is_array($archivosRaw['error']) && $archivosRaw['error'] === UPLOAD_ERR_NO_FILE)) {
    header('Location: ' . $redir . '&img_error=' . urlencode('No se seleccionó ningún archivo'));
    exit;
}

// Convertir estructura de $_FILES a array de archivos individuales
$archivos = [];
if (is_array($archivosRaw['name'])) {
    for ($i = 0; $i < count($archivosRaw['name']); $i++) {
        if ($archivosRaw['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $archivos[] = [
            'name'     => $archivosRaw['name'][$i],
            'type'     => $archivosRaw['type'][$i],
            'tmp_name' => $archivosRaw['tmp_name'][$i],
            'error'    => $archivosRaw['error'][$i],
            'size'     => $archivosRaw['size'][$i],
        ];
    }
} else {
    $archivos[] = $archivosRaw;
}

if (empty($archivos)) {
    header('Location: ' . $redir . '&img_error=' . urlencode('No se seleccionó ningún archivo'));
    exit;
}

$pdo = obtenerConexion();

// Índice base: total de imágenes locales existentes para este crematorio
$baseIdx = (int) $pdo->query(
    "SELECT COUNT(*) FROM crematorio_imagenes WHERE crematorio_id = $crematorioId AND ruta NOT LIKE 'http%'"
)->fetchColumn();

$tipoHelper = ($tipo === 'logo') ? 'logo' : (($tipo === 'portada') ? 'portada' : 'galeria');

$okCount  = 0;
$errores  = [];

foreach ($archivos as $i => $archivo) {
    $indice   = $baseIdx + $i + 1;
    $procesado = ImagenHelper::procesar($archivo, $tipoHelper, $slug, 'crematorios', $indice, $crematorioId);

    if (!$procesado['ok']) {
        $errores[] = ($archivo['name'] ?? "Archivo " . ($i + 1)) . ': ' . $procesado['error'];
        continue;
    }

    // tipo en DB: logo → logo, portada → portada, foto → foto
    $tipoDB = $tipo;

    $imgId = ImagenHelper::guardarEnDB($crematorioId, $tipoDB, $procesado['ruta'], $procesado['nombre'], $categoriaFinal, null, null, 'manual_admin');

    if (!$imgId) {
        $errores[] = ($archivo['name'] ?? "Archivo " . ($i + 1)) . ': Error al guardar en base de datos';
        continue;
    }

    $okCount++;

    // Si es logo, actualizar campo logo en crematorios con la última subida
    if ($tipo === 'logo') {
        $pdo->prepare("UPDATE crematorios SET logo = :ruta WHERE id = :id")
            ->execute([':ruta' => $procesado['ruta'], ':id' => $crematorioId]);
    }
}

// Notificar si quedaron imágenes pendientes de LLM
if ($categoriaFinal === null && $okCount > 0) {
    $pendientes = (int) $pdo->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE estado_llm = 'pendiente'")->fetchColumn();
    if ($pendientes > 0) {
        ImagenHelper::notificarAdminImagenesPendientes($pendientes);
    }
}

// Redirigir con resumen
if ($okCount > 0 && empty($errores)) {
    $msg = $okCount === 1 ? 'Imagen subida correctamente' : "$okCount imágenes subidas correctamente";
    header('Location: ' . $redir . '&img_ok=' . urlencode($msg));
} elseif ($okCount > 0) {
    $msg = "$okCount subida(s) OK — " . count($errores) . " error(es): " . implode('; ', $errores);
    header('Location: ' . $redir . '&img_ok=' . urlencode($msg));
} else {
    header('Location: ' . $redir . '&img_error=' . urlencode(implode('; ', $errores)));
}
exit;
