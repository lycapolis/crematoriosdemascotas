<?php
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('tiers');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tiers.php');
    exit;
}

$pdo        = obtenerConexion();
$idOriginal = trim($_POST['id_original'] ?? '');
$esNuevo    = ($idOriginal === '');

$id          = trim($_POST['id']             ?? '');
$nombre      = trim($_POST['nombre']         ?? '');
$descripcion = trim($_POST['descripcion']    ?? '');
$precio      = $_POST['precio_mensual'] !== '' ? floatval($_POST['precio_mensual']) : null;
$activo      = isset($_POST['activo']) && $_POST['activo'] == '1' ? 1 : 0;

$redir = BASE_URL . '/admin/editar-tier.php' . (!$esNuevo ? '?id=' . urlencode($idOriginal) : '');

// Validaciones
if ($esNuevo && $id === '') {
    header('Location: ' . $redir . '&form_error=' . urlencode('El ID del plan es obligatorio'));
    exit;
}
if ($nombre === '') {
    header('Location: ' . $redir . '&form_error=' . urlencode('El nombre es obligatorio'));
    exit;
}
if ($esNuevo) {
    $existe = $pdo->prepare("SELECT id FROM tiers WHERE id = :id");
    $existe->execute([':id' => $id]);
    if ($existe->fetch()) {
        header('Location: ' . $redir . '&form_error=' . urlencode('Ya existe un plan con ese ID'));
        exit;
    }
}

// Parsear reglas por sección
function parsearSeccion(string $key): array {
    $mostrar = isset($_POST[$key . '_mostrar']) && $_POST[$key . '_mostrar'] == '1' ? 1 : 0;
    $fuentes = [];
    if ($mostrar && isset($_POST[$key . '_fuentes']) && is_array($_POST[$key . '_fuentes'])) {
        $validas = ['local', 'url'];
        foreach ($_POST[$key . '_fuentes'] as $f) {
            if (in_array($f, $validas)) $fuentes[] = $f;
        }
    }
    return ['mostrar' => $mostrar, 'fuentes' => json_encode($fuentes)];
}

$logo    = parsearSeccion('logo');
$portada = parsearSeccion('portada');
$galP    = parsearSeccion('galeria_principal');
$galC    = parsearSeccion('galeria_categorias');

if ($esNuevo) {
    $pdo->prepare(
        "INSERT INTO tiers
            (id, nombre, descripcion, precio_mensual,
             logo_mostrar, logo_fuentes,
             portada_mostrar, portada_fuentes,
             galeria_principal_mostrar, galeria_principal_fuentes,
             galeria_categorias_mostrar, galeria_categorias_fuentes,
             activo)
         VALUES
            (:id, :nombre, :desc, :precio,
             :lm, :lf,
             :pm, :pf,
             :gpm, :gpf,
             :gcm, :gcf,
             :activo)"
    )->execute([
        ':id'     => $id,
        ':nombre' => $nombre,
        ':desc'   => $descripcion ?: null,
        ':precio' => $precio,
        ':lm'  => $logo['mostrar'],    ':lf'  => $logo['fuentes'],
        ':pm'  => $portada['mostrar'], ':pf'  => $portada['fuentes'],
        ':gpm' => $galP['mostrar'],    ':gpf' => $galP['fuentes'],
        ':gcm' => $galC['mostrar'],    ':gcf' => $galC['fuentes'],
        ':activo' => $activo,
    ]);
    header('Location: ' . BASE_URL . '/admin/tiers.php?ok=' . urlencode('Plan "' . $id . '" creado correctamente'));
} else {
    $pdo->prepare(
        "UPDATE tiers SET
            nombre = :nombre, descripcion = :desc, precio_mensual = :precio,
            logo_mostrar = :lm, logo_fuentes = :lf,
            portada_mostrar = :pm, portada_fuentes = :pf,
            galeria_principal_mostrar = :gpm, galeria_principal_fuentes = :gpf,
            galeria_categorias_mostrar = :gcm, galeria_categorias_fuentes = :gcf,
            activo = :activo
         WHERE id = :id"
    )->execute([
        ':id'     => $idOriginal,
        ':nombre' => $nombre,
        ':desc'   => $descripcion ?: null,
        ':precio' => $precio,
        ':lm'  => $logo['mostrar'],    ':lf'  => $logo['fuentes'],
        ':pm'  => $portada['mostrar'], ':pf'  => $portada['fuentes'],
        ':gpm' => $galP['mostrar'],    ':gpf' => $galP['fuentes'],
        ':gcm' => $galC['mostrar'],    ':gcf' => $galC['fuentes'],
        ':activo' => $activo,
    ]);
    header('Location: ' . BASE_URL . '/admin/tiers.php?ok=' . urlencode('Plan "' . $idOriginal . '" actualizado correctamente'));
}
exit;
