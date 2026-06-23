<?php
/**
 * Agrega una imagen vía URL a crematorio_imagenes.
 * POST: crematorio_id, url, tipo, categoria
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
$url          = trim($_POST['url'] ?? '');
$tipo         = trim($_POST['tipo'] ?? 'galeria');
$categoria    = trim($_POST['categoria'] ?? '');

$redir = BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId . '&saved=1';

if (!$crematorioId || !filter_var($url, FILTER_VALIDATE_URL)) {
    header('Location: ' . BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId . '&img_error=' . urlencode('URL inválida'));
    exit;
}

$tiposValidos = ['logo', 'galeria', 'portada'];
if (!in_array($tipo, $tiposValidos)) $tipo = 'galeria';

$categoriasValidas = array_merge([''], ImagenHelper::CATEGORIAS_VALIDAS);
if (!in_array($categoria, $categoriasValidas)) $categoria = '';
$categoriaBD = $categoria !== '' ? $categoria : null;

// Acoplamiento logo: tipo y categoría siempre sincronizados
if ($tipo === 'logo') $categoriaBD = 'logo';
if ($categoriaBD === 'logo') $tipo = 'logo';

$estadoLlm = $categoriaBD !== null ? 'procesada' : 'pendiente';

$pdo = obtenerConexion();

// Verificar que el crematorio existe
$check = $pdo->prepare("SELECT id FROM crematorios WHERE id = :id LIMIT 1");
$check->execute([':id' => $crematorioId]);
if (!$check->fetch()) {
    header('Location: ' . BASE_URL . '/admin/fichas-negocios.php');
    exit;
}

// Evitar duplicados
$dup = $pdo->prepare("SELECT id FROM crematorio_imagenes WHERE crematorio_id = :cid AND ruta = :url LIMIT 1");
$dup->execute([':cid' => $crematorioId, ':url' => $url]);
if ($dup->fetch()) {
    header('Location: ' . BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId . '&img_error=' . urlencode('Esa URL ya existe'));
    exit;
}

// Si es portada, desmarcar la anterior
if ($tipo === 'portada') {
    $pdo->prepare("UPDATE crematorio_imagenes SET tipo = 'galeria' WHERE crematorio_id = :cid AND tipo = 'portada'")
        ->execute([':cid' => $crematorioId]);
}

$pdo->prepare(
    "INSERT INTO crematorio_imagenes (crematorio_id, ruta, tipo, categoria, estado_llm, orden, origen)
     VALUES (:cid, :ruta, :tipo, :cat, :estado, 0, 'manual_admin')"
)->execute([
    ':cid'    => $crematorioId,
    ':ruta'   => $url,
    ':tipo'   => $tipo,
    ':cat'    => $categoriaBD,
    ':estado' => $estadoLlm,
]);

// Si es logo, actualizar también crematorios.logo
if ($tipo === 'logo') {
    $pdo->prepare("UPDATE crematorios SET logo = :url WHERE id = :id")
        ->execute([':url' => $url, ':id' => $crematorioId]);
}

header('Location: ' . $redir);
exit;
