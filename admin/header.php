<?php
/**
 * Header del Panel Admin
 */
$base_url = BASE_URL;
$admin = obtenerAdminActual();

// Fecha en castellano (no depende del locale del sistema)
$dias_es  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
$meses_es = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fechaHoy = $dias_es[(int)date('w')] . ', ' . (int)date('j') . ' de ' . $meses_es[(int)date('n') - 1] . ' de ' . date('Y');
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

    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/variables.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/componentes.css'); ?>">

    <!-- Form controls compartidos: capa base + tema Tom Select + Notyf.
         Cargado ANTES de admin.css para que admin.css pueda sobrescribir tokens.
         forms.css va último (tematiza Tom Select, Notyf y Micromodal). -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/librerias/tom-select/tom-select.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/librerias/notyf/notyf.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/forms.css'); ?>">

    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

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
            font-size: 0.9375rem; /* 15px — base del header */
        }
        .admin-header__left {
            display: flex;
            align-items: center;
            gap: var(--espacio-cuatro);
        }
        .admin-header__logo {
            display: flex;
            align-items: center;
            gap: var(--espacio-dos);
            color: var(--color-ocho);
            text-decoration: none;
            font-weight: var(--peso-negrita);
            font-size: 1rem; /* 16px */
            letter-spacing: -0.005em;
        }
        .admin-header__fecha {
            font-size: 0.8125rem; /* 13px */
            color: var(--color-ocho);
            opacity: 0.7;
            padding-left: var(--espacio-tres);
            border-left: 1px solid rgba(255, 255, 255, .18);
            line-height: 1.3;
        }
        .admin-header__nav {
            display: flex;
            align-items: center;
            gap: var(--espacio-cuatro);
            font-size: 0.9375rem; /* 15px */
        }
        .admin-header__link {
            color: var(--color-ocho);
            text-decoration: none;
            opacity: 0.88; /* mejor contraste — antes 0.78 */
            transition: opacity 0.2s;
            font-size: 0.9375rem; /* 15px */
            font-weight: 500;
            padding: 0.25rem 0;
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
            font-size: 0.875rem; /* 14px */
            opacity: 0.92; /* mejor contraste */
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="admin-header__left">
            <a href="<?php echo $base_url; ?>/admin/dashboard.php" class="admin-header__logo">
                <i data-lucide="shield" class="icono"></i>
                Panel Admin
            </a>
            <span class="admin-header__fecha"><?php echo $fechaHoy; ?></span>
        </div>

        <nav class="admin-header__nav">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            function navLink(string $href, string $label, string $current): string {
                $active = $current === basename($href) ? 'opacity:1; font-weight:700;' : '';
                return "<a href=\"$href\" class=\"admin-header__link\" style=\"$active\">$label</a>";
            }
            $pdo_header = obtenerConexion();
            $pendientesSolicitudes = 0;
            $pendientesImagenes    = 0;
            $leadsNuevos           = 0;
            $leadsB2CNuevos        = 0;
            if ($pdo_header) {
                try {
                    $pendientesSolicitudes = (int) $pdo_header->query("SELECT COUNT(*) FROM solicitudes_registro WHERE estado = 'pendiente'")->fetchColumn();
                } catch (PDOException $e) { /* tabla puede no existir */ }
                try {
                    $pendientesImagenes = (int) $pdo_header->query("SELECT COUNT(*) FROM crematorio_imagenes WHERE estado_llm = 'pendiente'")->fetchColumn();
                } catch (PDOException $e) { /* idem */ }
                try {
                    $leadsNuevos = (int) $pdo_header->query("SELECT COUNT(*) FROM leads_comerciales WHERE estado = 'nuevo'")->fetchColumn();
                } catch (PDOException $e) { /* idem */ }
                try {
                    $leadsB2CNuevos = (int) $pdo_header->query("SELECT COUNT(*) FROM leads_b2c WHERE estado = 'nuevo'")->fetchColumn();
                } catch (PDOException $e) { /* idem */ }
            }
            function badgeContador(int $n, string $color = '#f59e0b'): string {
                if ($n <= 0) return '';
                return '<span style="background:' . $color . ';color:#fff;border-radius:999px;font-size:.65rem;padding:.1rem .4rem;margin-left:.3rem;font-weight:700;">' . $n . '</span>';
            }
            echo navLink("$base_url/admin/dashboard.php",     'Inicio',       $currentPage);
            echo navLink("$base_url/admin/fichas-negocios.php", 'Negocios',     $currentPage);

            if (tienePermiso($admin, 'solicitudes')): ?>
            <a href="<?php echo $base_url; ?>/admin/solicitudes.php" class="admin-header__link"
               style="<?php echo $currentPage === 'solicitudes.php' ? 'opacity:1;font-weight:700;' : ''; ?>">
                Solicitudes<?php echo badgeContador($pendientesSolicitudes, '#dc2626'); ?>
            </a>
            <a href="<?php echo $base_url; ?>/admin/leads-comerciales.php" class="admin-header__link"
               style="<?php echo $currentPage === 'leads-comerciales.php' ? 'opacity:1;font-weight:700;' : ''; ?>">
                Leads B2B<?php echo badgeContador($leadsNuevos, '#dc2626'); ?>
            </a>
            <a href="<?php echo $base_url; ?>/admin/leads-b2c.php" class="admin-header__link"
               style="<?php echo $currentPage === 'leads-b2c.php' ? 'opacity:1;font-weight:700;' : ''; ?>">
                Leads B2C<?php echo badgeContador($leadsB2CNuevos, '#dc2626'); ?>
            </a>
            <?php endif; ?>

            <?php if (tienePermiso($admin, 'tiers')) echo navLink("$base_url/admin/tiers.php", 'Planes', $currentPage); ?>

            <?php if (tienePermiso($admin, 'moderacion')) echo navLink("$base_url/admin/resenas.php", 'Reseñas', $currentPage); ?>

            <?php if (tienePermiso($admin, 'imagenes') || tienePermiso($admin, 'ia')): ?>
            <a href="<?php echo $base_url; ?>/admin/imagenes-cola.php" class="admin-header__link"
               style="<?php echo $currentPage === 'imagenes-cola.php' ? 'opacity:1;font-weight:700;' : ''; ?>">
                Imágenes LLM<?php echo badgeContador($pendientesImagenes); ?>
            </a>
            <?php endif; ?>

            <?php if (esSuperAdmin() || tienePermiso($admin, 'gestionar_admins')): ?>
            <a href="<?php echo $base_url; ?>/admin/admins.php" class="admin-header__link"
               style="<?php echo in_array($currentPage, ['admins.php','admin-editar.php']) ? 'opacity:1;font-weight:700;' : ''; ?>">
                Admins
            </a>
            <?php endif; ?>

            <?php if (esSuperAdmin()): ?>
            <a href="<?php echo $base_url; ?>/admin/configuracion-ia.php" class="admin-header__link"
               style="<?php echo $currentPage === 'configuracion-ia.php' ? 'opacity:1;font-weight:700;' : ''; ?>">
                Config IA
            </a>
            <a href="<?php echo $base_url; ?>/admin/configuracion-formularios.php" class="admin-header__link"
               style="<?php echo $currentPage === 'configuracion-formularios.php' ? 'opacity:1;font-weight:700;' : ''; ?>">
                Config Forms
            </a>
            <?php endif; ?>

            <a href="<?php echo $base_url; ?>/" class="admin-header__link" target="_blank">
                <i data-lucide="external-link" class="icono" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                Ver Sitio
            </a>

            <div class="admin-header__user">
                <a href="<?php echo $base_url; ?>/admin/mi-perfil.php"
                   class="admin-header__user-name"
                   style="text-decoration:none; color:inherit; cursor:pointer; display:inline-flex; align-items:center; gap:.3rem; padding:.25rem .5rem; border-radius:var(--radio-uno); transition:background .15s;<?php echo $currentPage === 'mi-perfil.php' ? 'background:rgba(255,255,255,.12);' : ''; ?>"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'"
                   onmouseout="this.style.background='<?php echo $currentPage === 'mi-perfil.php' ? 'rgba(255,255,255,.12)' : 'transparent'; ?>'"
                   title="Ver mi perfil — rol: <?php echo htmlspecialchars($admin['rol'] ?? 'admin'); ?>">
                    <i data-lucide="user" class="icono" style="width:14px; height:14px;"></i>
                    <?php echo htmlspecialchars($admin['nombre'] ?? 'Admin'); ?>
                    <?php if (!empty($admin['rol'])): ?>
                        <span style="font-size:.75rem; font-weight:700; letter-spacing:.04em; background:<?php echo $admin['rol'] === 'super_admin' ? '#dc2626' : ($admin['rol'] === 'user' ? '#6b7280' : '#3b82f6'); ?>; padding:.18rem .55rem; border-radius:9999px; line-height:1.2;">
                            <?php echo htmlspecialchars($admin['rol']); ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo $base_url; ?>/admin/logout.php" class="boton dos pequeno">
                    <i data-lucide="log-out" class="icono" style="width: 16px; height: 16px;"></i>
                    Salir
                </a>
            </div>
        </nav>
    </header>
