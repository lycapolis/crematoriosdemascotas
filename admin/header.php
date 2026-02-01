<?php
/**
 * Header del Panel Admin
 */
$base_url = BASE_URL;
$admin = obtenerAdminActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo_pagina ?? 'Panel Admin'); ?></title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/img/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>/assets/img/favicon/favicon.svg">
    <link rel="shortcut icon" href="<?php echo $base_url; ?>/assets/img/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>/assets/img/favicon/apple-touch-icon.png">

    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/variables.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/componentes.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <style>
        .admin-header {
            background: var(--color-dos);
            color: var(--color-ocho);
            padding: var(--espacio-tres) var(--espacio-cinco);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-header__logo {
            display: flex;
            align-items: center;
            gap: var(--espacio-dos);
            color: var(--color-ocho);
            text-decoration: none;
            font-weight: var(--peso-negrita);
        }
        .admin-header__nav {
            display: flex;
            align-items: center;
            gap: var(--espacio-cuatro);
        }
        .admin-header__link {
            color: var(--color-ocho);
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .admin-header__link:hover {
            opacity: 1;
        }
        .admin-header__user {
            display: flex;
            align-items: center;
            gap: var(--espacio-tres);
        }
        .admin-header__user-name {
            font-size: var(--fs-uno);
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <a href="<?php echo $base_url; ?>/admin/resenas.php" class="admin-header__logo">
            <i data-lucide="shield" class="icono"></i>
            Panel Admin
        </a>

        <nav class="admin-header__nav">
            <a href="<?php echo $base_url; ?>/admin/resenas.php" class="admin-header__link">Reseñas</a>
            <a href="<?php echo $base_url; ?>/" class="admin-header__link" target="_blank">
                <i data-lucide="external-link" class="icono" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                Ver Sitio
            </a>

            <div class="admin-header__user">
                <span class="admin-header__user-name">
                    <?php echo htmlspecialchars($admin['nombre'] ?? 'Admin'); ?>
                </span>
                <a href="<?php echo $base_url; ?>/admin/logout.php" class="boton dos pequeno">
                    <i data-lucide="log-out" class="icono" style="width: 16px; height: 16px;"></i>
                    Salir
                </a>
            </div>
        </nav>
    </header>
