<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ELIMINAR IMAGEN DE CREMATORIO - PANEL ADMIN
 * ═══════════════════════════════════════════════════════════
 * POST: imagen_id, crematorio_id
 * Redirige a editar-ficha-negocio.php?id=X
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();
requierePermiso('eliminacion');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: fichas-negocios.php');
    exit;
}

$imagenId     = intval($_POST['imagen_id'] ?? 0);
$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$redirPost    = $_POST['redir'] ?? '';
$redir        = (str_starts_with($redirPost, BASE_URL . '/admin/'))
    ? $redirPost
    : BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId;

if (!$imagenId || !$crematorioId) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Parámetros inválidos'));
    exit;
}

$pdo = obtenerConexion();

// Verificar que la imagen pertenece al crematorio (seguridad)
$img = $pdo->prepare("SELECT * FROM crematorio_imagenes WHERE id = :id AND crematorio_id = :cid LIMIT 1");
$img->execute([':id' => $imagenId, ':cid' => $crematorioId]);
$imagen = $img->fetch(PDO::FETCH_ASSOC);

if (!$imagen) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Imagen no encontrada'));
    exit;
}

// Eliminar archivo de disco (solo rutas locales, no URLs externas)
if (!empty($imagen['ruta']) && !str_starts_with($imagen['ruta'], 'http')) {
    ImagenHelper::eliminar($imagen['ruta']);
}

// Eliminar de DB
$pdo->prepare("DELETE FROM crematorio_imagenes WHERE id = :id")->execute([':id' => $imagenId]);

// Si esta imagen era la portada/logo principal, limpiar la referencia para
// que la lógica de auto-asignación tome la siguiente disponible.
limpiarReferenciasImagenesBorradas([$imagenId]);

header('Location: ' . $redir . '&img_ok=' . urlencode('Imagen eliminada'));
exit;
