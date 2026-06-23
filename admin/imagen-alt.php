<?php
/**
 * Actualiza el alt_text de una imagen.
 * POST: imagen_id, crematorio_id, alt_text, redir (opcional)
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: fichas-negocios.php');
    exit;
}

$imagenId     = intval($_POST['imagen_id']     ?? 0);
$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$altText      = trim($_POST['alt_text']        ?? '');
$redirPost    = $_POST['redir'] ?? '';
$redir        = (str_starts_with($redirPost, BASE_URL . '/admin/'))
    ? $redirPost
    : BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId;

if (!$imagenId || !$crematorioId) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Parámetros inválidos'));
    exit;
}

$pdo = obtenerConexion();

$check = $pdo->prepare("SELECT id FROM crematorio_imagenes WHERE id = :id AND crematorio_id = :cid LIMIT 1");
$check->execute([':id' => $imagenId, ':cid' => $crematorioId]);
if (!$check->fetch()) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Imagen no encontrada'));
    exit;
}

$pdo->prepare("UPDATE crematorio_imagenes SET alt_text = :alt WHERE id = :id")
    ->execute([':alt' => $altText ?: null, ':id' => $imagenId]);

header('Location: ' . $redir . '&img_ok=' . urlencode('Alt text actualizado'));
exit;
