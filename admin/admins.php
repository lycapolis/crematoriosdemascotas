<?php
/**
 * Panel Admin — Gestión de admins (listado).
 * Accesible para super_admin o admins con etiqueta 'gestionar_admins'.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

// super_admin siempre puede; otros necesitan la etiqueta
if (!esSuperAdmin()) {
    requierePermiso('gestionar_admins');
}

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

$ok    = $_GET['ok']    ?? null;
$error = $_GET['error'] ?? null;

$admins = $pdo->query("
    SELECT id, nombre, email, rol, etiquetas, activo, ultimo_login, created_at
    FROM admins
    ORDER BY FIELD(rol, 'super_admin', 'admin', 'user'), nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Stats para subtitle
$totalAdmins   = count($admins);
$activosAdmins = 0;
foreach ($admins as $a) {
    if ($a['activo']) $activosAdmins++;
}

// Mapeo de rol → variante de pill
function adminRolPill(string $rol): string {
    return match ($rol) {
        'super_admin' => 'admin-pill--error',
        'admin'       => 'admin-pill--info',
        'user'        => '',
        default       => '',
    };
}

$titulo_pagina = 'Admins — Panel';
include 'header.php';
?>

<div class="admin-page">

    <!-- ═══ Page header ═══════════════════════════════════════════════════ -->
    <header class="admin-page-header">
        <div style="display:flex; align-items:center; gap: var(--espacio-tres); flex-wrap: wrap;">
            <h1 class="admin-page-title">Admins del sistema</h1>
            <a href="admin-editar.php" class="boton uno pequeno">
                <i data-lucide="plus" class="icono" style="width:14px; height:14px;"></i>
                Nuevo admin
            </a>
        </div>
        <p class="admin-page-subtitle">
            <span class="admin-num"><?php echo $totalAdmins; ?></span> en total
            <span class="admin-dash"></span>
            <strong style="color: var(--admin-tinta-fuerte); font-variant-numeric: tabular-nums;"><?php echo $activosAdmins; ?></strong> activos
        </p>
    </header>

    <!-- Feedback ?ok/?error → toast (puente en footer). Banner inline removido. -->

    <!-- ═══ Tabla de admins ═══════════════════════════════════════════════ -->
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Rol</th>
                    <th>Etiquetas</th>
                    <th>Último login</th>
                    <th class="admin-table__acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a):
                    $rol     = $a['rol'] ?? 'admin';
                    $tags    = !empty($a['etiquetas']) ? (json_decode($a['etiquetas'], true) ?: []) : [];
                    $esYo    = (int)$a['id'] === (int)$adminActual['id'];
                    $rolCls  = adminRolPill($rol);
                ?>
                <tr<?php echo !$a['activo'] ? ' style="opacity:.55;"' : ''; ?>>
                    <td>
                        <div class="admin-table__principal" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                            <?php echo htmlspecialchars($a['nombre']); ?>
                            <?php if ($esYo): ?>
                                <span class="admin-pill admin-pill--info">vos</span>
                            <?php endif; ?>
                            <?php if (!$a['activo']): ?>
                                <span class="admin-pill">inactivo</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-table__secundario"><?php echo htmlspecialchars($a['email']); ?></div>
                    </td>
                    <td>
                        <span class="admin-pill <?php echo $rolCls; ?>" style="font-family: monospace;">
                            <?php echo htmlspecialchars($rol); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($rol === 'super_admin'): ?>
                            <span class="admin-text-muted" style="font-style:italic; font-size: var(--admin-body-sm);">todas (ignora etiquetas)</span>
                        <?php elseif (empty($tags)): ?>
                            <span style="color: var(--admin-tone-alerta-fg); font-size: var(--admin-body-sm);">Sin etiquetas <span class="admin-dash"></span> solo lectura</span>
                        <?php else: ?>
                            <div style="display:flex; flex-wrap:wrap; gap:.3rem; max-width: 360px;">
                                <?php foreach ($tags as $tag): ?>
                                <span class="admin-pill" style="font-family: monospace;"
                                      title="<?php echo htmlspecialchars(ETIQUETAS_DISPONIBLES[$tag] ?? ''); ?>"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-text-muted" style="font-size: var(--admin-body-sm); font-variant-numeric: tabular-nums;">
                            <?php echo $a['ultimo_login'] ? date('d/m/Y H:i', strtotime($a['ultimo_login'])) : '—'; ?>
                        </span>
                    </td>
                    <td class="admin-table__acciones">
                        <a href="admin-editar.php?id=<?php echo $a['id']; ?>" class="boton dos pequeno">
                            <i data-lucide="pencil" class="icono" style="width:14px; height:14px;"></i>
                            Editar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ═══ Catálogo de etiquetas disponibles ═════════════════════════════ -->
    <section class="admin-section" style="margin-top: var(--espacio-cinco); margin-bottom: 0;">
        <div class="admin-section__heading">
            <h2 class="admin-section__title">
                Catálogo de etiquetas
                <span class="admin-section__count"><?php echo count(ETIQUETAS_DISPONIBLES); ?> disponibles</span>
            </h2>
            <p class="admin-section__hint">Cada etiqueta habilita una capacidad específica. Asignalas en la edición del admin.</p>
        </div>
        <div class="admin-section__body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: .6rem 1.5rem;">
                <?php foreach (ETIQUETAS_DISPONIBLES as $key => $desc): ?>
                <div style="display: flex; align-items: flex-start; gap: .65rem; padding: .35rem 0;">
                    <span class="admin-pill" style="font-family: monospace; flex-shrink: 0; margin-top: .1rem;">
                        <?php echo htmlspecialchars($key); ?>
                    </span>
                    <span style="font-size: var(--admin-body-sm); color: var(--admin-tinta-suave); line-height: 1.45;">
                        <?php echo htmlspecialchars($desc); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</div>

<?php include 'footer.php'; ?>
