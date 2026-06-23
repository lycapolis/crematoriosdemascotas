<?php
require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();
requierePermiso('tiers');

$tierId = trim($_GET['id'] ?? '');
if (!$tierId) {
    header('Location: tiers.php');
    exit;
}

$pdo = obtenerConexion();

// Verificar que no tiene crematorios asignados
$count = $pdo->prepare("SELECT COUNT(*) FROM crematorios WHERE tier = :id");
$count->execute([':id' => $tierId]);
if ($count->fetchColumn() > 0) {
    header('Location: tiers.php?error=' . urlencode('No se puede eliminar: hay fichas en este plan'));
    exit;
}

$pdo->prepare("DELETE FROM tiers WHERE id = :id")->execute([':id' => $tierId]);

header('Location: tiers.php?ok=' . urlencode('Plan "' . $tierId . '" eliminado'));
exit;
