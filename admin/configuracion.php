<?php
/**
 * Panel Admin — HUB de Configuración.
 *
 * Página pública para cualquier admin logueado. Cada tarjeta se muestra
 * solo si el admin tiene acceso a esa sección:
 *   - Admins    → super_admin o etiqueta 'gestionar_admins'
 *   - Config IA → super_admin
 *   - Config Forms → super_admin
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';

requerirAutenticacion();

$pdo = obtenerConexion();
$adminActual = obtenerAdminActual();

// ── Definición de las tarjetas del hub ─────────────────────────────────────
// Cada una: destino, regla de visibilidad, ícono, título, descripción.
$tarjetas = [
    [
        'href'         => BASE_URL . '/admin/admins.php',
        'icono'        => 'users',
        'titulo'       => 'Admins del sistema',
        'descripcion'  => 'Crear, editar y eliminar admins del panel.',
        'visible'      => esSuperAdmin() || tienePermiso($adminActual, 'gestionar_admins'),
    ],
    [
        'href'         => BASE_URL . '/admin/configuracion-ia.php',
        'icono'        => 'bot',
        'titulo'       => 'Configuración de IA',
        'descripcion'  => 'Proveedor y modelo para cada tarea de IA del panel.',
        'visible'      => esSuperAdmin(),
    ],
    [
        'href'         => BASE_URL . '/admin/configuracion-formularios.php',
        'icono'        => 'sliders',
        'titulo'       => 'Configuración de formularios',
        'descripcion'  => 'Throttling del widget lead-capture.',
        'visible'      => esSuperAdmin(),
    ],
];

$tarjetasVisibles = array_filter($tarjetas, fn($t) => $t['visible']);

$titulo_pagina = 'Configuración — Admin';
include 'header.php';
?>

<style>
    .config-hub {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: var(--espacio-cuatro);
    }
    .config-card {
        background: var(--admin-superficie);
        border: 1px solid var(--admin-linea);
        border-radius: var(--admin-r-md);
        box-shadow: var(--admin-sombra-suave);
        padding: var(--espacio-cuatro);
        text-decoration: none;
        color: inherit;
        transition: border-color 0.2s;
    }
    .config-card:hover {
        border-color: var(--color-uno);
    }
    .config-card__title {
        font-family: var(--fuente-texto);
        font-size: var(--admin-h4);
        font-weight: 700;
        color: var(--admin-tinta-fuerte);
        margin: 0 0 var(--espacio-dos);
        display: flex;
        align-items: center;
        gap: var(--espacio-dos);
    }
    .config-card__title .icono {
        width: 20px;
        height: 20px;
        color: var(--admin-brand);
    }
    .config-card__desc {
        margin: 0;
        font-size: var(--admin-body-sm);
        color: var(--admin-tinta-suave);
        line-height: 1.5;
    }
</style>

<div class="admin-page">

    <header class="admin-page-header">
        <div class="admin-page-header__intro">
            <h1 class="admin-page-title">Configuración</h1>
            <p class="admin-page-subtitle">Acceso a las secciones de configuración del panel.</p>
        </div>
    </header>

    <?php if (empty($tarjetasVisibles)): ?>
    <div class="admin-banner admin-banner--warning">
        <i data-lucide="alert-triangle" class="icono admin-banner__icon"></i>
        <div class="admin-banner__content">
            No tenés acceso a ninguna sección de configuración. Si creés que deberías, contactá a un super_admin.
        </div>
    </div>
    <?php else: ?>
    <div class="config-hub">
        <?php foreach ($tarjetasVisibles as $t): ?>
        <a href="<?php echo htmlspecialchars($t['href']); ?>" class="config-card">
            <h2 class="config-card__title">
                <i data-lucide="<?php echo htmlspecialchars($t['icono']); ?>" class="icono"></i>
                <?php echo htmlspecialchars($t['titulo']); ?>
            </h2>
            <p class="config-card__desc"><?php echo htmlspecialchars($t['descripcion']); ?></p>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>
