<?php
/**
 * Actualiza la categoría, tipo y estado_llm de una imagen.
 * POST: imagen_id, crematorio_id, categoria, tipo (opcional — si se omite se deriva de categoria)
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
require_once dirname(__DIR__) . '/includes/ImagenHelper.php';

requerirAutenticacion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: fichas-negocios.php');
    exit;
}

$imagenId     = intval($_POST['imagen_id']     ?? 0);
$crematorioId = intval($_POST['crematorio_id'] ?? 0);
$categoria    = trim($_POST['categoria']        ?? '');
$tipoPost     = trim($_POST['tipo']             ?? '');
// Form único: el editor de la card manda también alt_text. Solo se actualiza
// si la key viene en el POST (otros callers que no la mandan no lo tocan).
$tieneAlt     = array_key_exists('alt_text', $_POST);
$altText      = $tieneAlt ? trim((string)$_POST['alt_text']) : null;
$redirPost    = $_POST['redir'] ?? '';
$redir        = (str_starts_with($redirPost, BASE_URL . '/admin/'))
    ? $redirPost
    : BASE_URL . '/admin/editar-ficha-negocio.php?id=' . $crematorioId;

$tiposValidos      = ['logo', 'galeria', 'portada'];
$categoriasValidas = array_merge([''], ImagenHelper::CATEGORIAS_VALIDAS);
if (!$imagenId || !$crematorioId || !in_array($categoria, $categoriasValidas, true)) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Parámetros inválidos'));
    exit;
}

$pdo = obtenerConexion();

// Verificar que pertenece al crematorio
$check = $pdo->prepare("SELECT id FROM crematorio_imagenes WHERE id = :id AND crematorio_id = :cid LIMIT 1");
$check->execute([':id' => $imagenId, ':cid' => $crematorioId]);
if (!$check->fetch()) {
    header('Location: ' . $redir . '&img_error=' . urlencode('Imagen no encontrada'));
    exit;
}

// Si el admin asigna categoría → procesada; si la borra → vuelve a pendiente
$estadoLlm   = ($categoria !== '') ? 'procesada' : 'pendiente';
$categoriaBD = ($categoria !== '') ? $categoria : null;
// Trazabilidad: esta edición la hizo un admin a mano. Sin categoría → sin trazar.
$categoriaOrigen = ($categoria !== '') ? 'admin' : null;

// Tipo: si el admin lo especificó explícitamente, usarlo; si no, derivar de categoría
if (in_array($tipoPost, $tiposValidos, true)) {
    $tipoNuevo = $tipoPost;
} else {
    $tipoNuevo = ($categoriaBD === 'logo') ? 'logo' : 'galeria';
}

// Acoplamiento logo: tipo y categoría siempre sincronizados
if ($tipoNuevo === 'logo') $categoriaBD = 'logo';
if ($categoriaBD === 'logo') $tipoNuevo  = 'logo';

// Si el tipo resultante es portada, desmarcar la anterior del mismo crematorio
if ($tipoNuevo === 'portada') {
    $pdo->prepare("UPDATE crematorio_imagenes SET tipo = 'galeria' WHERE crematorio_id = :cid AND tipo = 'portada' AND id != :id")
        ->execute([':cid' => $crematorioId, ':id' => $imagenId]);
}

$sets   = "categoria = :cat, estado_llm = :estado, tipo = :tipo, categoria_origen = :corigen";
$params = [':cat' => $categoriaBD, ':estado' => $estadoLlm, ':tipo' => $tipoNuevo,
           ':corigen' => $categoriaOrigen, ':id' => $imagenId];
if ($tieneAlt) {
    $sets .= ", alt_text = :alt";
    $params[':alt'] = ($altText !== '') ? $altText : null;
}
$pdo->prepare("UPDATE crematorio_imagenes SET $sets WHERE id = :id")->execute($params);

// Si se asignó como logo, actualizar también crematorios.logo con la ruta de esta imagen
if ($categoriaBD === 'logo') {
    $ruta = $pdo->query("SELECT ruta FROM crematorio_imagenes WHERE id = $imagenId")->fetchColumn();
    if ($ruta) {
        $pdo->prepare("UPDATE crematorios SET logo = :ruta WHERE id = :id")
            ->execute([':ruta' => $ruta, ':id' => $crematorioId]);
    }
}

header('Location: ' . $redir . '&img_ok=' . urlencode('Imagen actualizada'));
exit;
