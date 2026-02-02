<?php
/**
 * ═══════════════════════════════════════════════════════════
 * HEADER - Componente Reutilizable
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Variables disponibles:
 * - $titulo_pagina: Título del tab (default: "Crematorios de Mascotas")
 * - $pagina_actual: Página activa (inicio|directorio|como-funciona|nosotros)
 * ═══════════════════════════════════════════════════════════
 */

// Incluir configuración y conexión a BD
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion_db.php';
require_once __DIR__ . '/funciones.php';

// Valores por defecto
$titulo_pagina = $titulo_pagina ?? SITIO_NOMBRE;
$pagina_actual = $pagina_actual ?? '';

// Base URL del proyecto (usa constante de config.php)
$base_url = BASE_URL;

// ═══════════════════════════════════════════════════════════
// CONFIGURACIÓN SEO (variables disponibles para cada página)
// ═══════════════════════════════════════════════════════════
$meta_descripcion = $meta_descripcion ?? SITIO_DESCRIPCION;
$meta_robots = $meta_robots ?? 'index, follow';
$meta_canonical = $meta_canonical ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
$og_image = $og_image ?? SEO_DEFAULT_IMAGE;
$og_type = $og_type ?? SEO_SITE_TYPE;
$schema_data = $schema_data ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titulo_pagina); ?></title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($meta_descripcion); ?>">
    <meta name="robots" content="<?php echo htmlspecialchars($meta_robots); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($meta_canonical); ?>">

    <!-- Open Graph (Facebook, LinkedIn, WhatsApp) -->
    <meta property="og:title" content="<?php echo htmlspecialchars($titulo_pagina); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_descripcion); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($meta_canonical); ?>">
    <meta property="og:type" content="<?php echo htmlspecialchars($og_type); ?>">
    <meta property="og:locale" content="<?php echo SEO_LOCALE; ?>">
    <meta property="og:site_name" content="<?php echo SITIO_NOMBRE; ?>">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($titulo_pagina); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_descripcion); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <meta name="twitter:site" content="<?php echo SEO_TWITTER_HANDLE; ?>">

    <!-- Schema.org JSON-LD -->
    <?php if ($schema_data): ?>
    <script type="application/ld+json">
    <?php echo json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>
    <?php else: ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "<?php echo SITIO_NOMBRE; ?>",
        "url": "<?php echo $base_url; ?>",
        "description": "<?php echo htmlspecialchars(SITIO_DESCRIPCION); ?>",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "<?php echo $base_url; ?>/buscador.php?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <?php endif; ?>

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>/assets/img/favicon/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="<?php echo $base_url; ?>/assets/img/favicon/favicon.svg">
    <link rel="shortcut icon" href="<?php echo $base_url; ?>/assets/img/favicon/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url; ?>/assets/img/favicon/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-title" content="<?php echo SITIO_NOMBRE; ?>">
    <link rel="manifest" href="<?php echo $base_url; ?>/assets/img/favicon/site.webmanifest">


    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/variables.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/componentes.css">

    <!-- Fuentes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-TDLMC4BH');</script>
    <!-- End Google Tag Manager -->

    <!-- UTM Tracker: Preserva TODOS los parámetros de query string durante la navegación -->
    <script>
    (function() {
        const currentParams = new URLSearchParams(window.location.search);
        let storedParams = sessionStorage.getItem('url_params');

        // Si la URL actual tiene parámetros, guardarlos (sobrescribe los anteriores)
        // Lista negra: parámetros que NO deben propagarse a otras páginas
        const blacklist = ['busqueda'];

        if (currentParams.toString()) {
            const paramsObj = {};
            currentParams.forEach(function(value, key) {
                if (!blacklist.includes(key)) {
                    paramsObj[key] = value;
                }
            });
            storedParams = JSON.stringify(paramsObj);
            sessionStorage.setItem('url_params', storedParams);
        }

        // Si hay parámetros guardados, modificar todos los enlaces internos
        if (storedParams) {
            const paramsObj = JSON.parse(storedParams);
            const currentHost = window.location.host;

            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('a[href]').forEach(function(link) {
                    try {
                        const url = new URL(link.href, window.location.origin);
                        // Solo enlaces internos (mismo host o relativos)
                        if (url.host === currentHost || link.getAttribute('href').startsWith('/') || link.getAttribute('href').startsWith('?')) {
                            // Añadir parámetros si no los tiene ya
                            Object.keys(paramsObj).forEach(function(key) {
                                if (!url.searchParams.has(key)) {
                                    url.searchParams.set(key, paramsObj[key]);
                                }
                            });
                            link.href = url.toString();
                        }
                    } catch(e) {}
                });
            });
        }
    })();
    </script>
    <!-- End UTM Tracker -->

    <!-- WhatsApp Chat Widget -->
    <script defer src="https://whatsapp.lycapolis.com/install-widget/bundle.js?key=1169a891-ad22-4a8e-9587-c3ee25af72a5"></script>
    <!-- End WhatsApp Chat Widget -->     
</head>
<body>

    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TDLMC4BH"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->

    <!-- Header -->
    <header class="header">
        <div class="header__contenedor">
            <!-- Logo -->
            <a href="<?php echo $base_url; ?>/" class="header__logo">
                <i data-lucide="paw-print" class="icono"></i>
                Crematorios de Mascotas
            </a>

            <!-- Navegación Desktop -->
            <nav class="header__nav">
                <ul class="menu">
                    
                    <li><a href="<?php echo $base_url; ?>/directorio.php" class="menu__enlace <?php echo $pagina_actual === 'directorio' ? 'activo' : ''; ?>">Directorio</a></li>                    
                    <li><a href="<?php echo $base_url; ?>/nosotros.php" class="menu__enlace <?php echo $pagina_actual === 'nosotros' ? 'activo' : ''; ?>">Nosotros</a></li>
                    <li><a href="<?php echo $base_url; ?>/contacto.php" class="menu__enlace <?php echo $pagina_actual === 'contacto' ? 'activo' : ''; ?>">Contacto</a></li>
                </ul>                
                
                <a href="<?php echo $base_url; ?>/registrar-negocio.php" class="boton uno <?php echo $pagina_actual === 'registrar-negocio' ? 'activo' : ''; ?>">Registrar Mi Negocio</a>
                
            </nav>

            <!-- Botón Menú Móvil -->
            <button class="header__boton-movil" onclick="toggleMenu()" aria-label="Abrir menú">
                <i data-lucide="menu" class="icono"></i>
            </button>
        </div>
    </header>

    <!-- Menú Móvil (overlay) -->
    <nav id="menu-movil" class="menu movil">
        <button class="menu__cerrar" onclick="toggleMenu()" aria-label="Cerrar menú">
            <i data-lucide="x" class="icono"></i>
        </button>

        <a href="<?php echo $base_url; ?>/" class="header__logo <?php echo $pagina_actual === 'inicio' ? 'activo' : ''; ?>">
            <i data-lucide="paw-print" class="icono"></i>
            Crematorios de Mascotas
        </a>
        <a href="<?php echo $base_url; ?>/directorio.php" class="menu__enlace <?php echo $pagina_actual === 'directorio' ? 'activo' : ''; ?>">Directorio</a>
        <a href="<?php echo $base_url; ?>/como-funciona.php" class="menu__enlace <?php echo $pagina_actual === 'como-funciona' ? 'activo' : ''; ?>">Cómo Funciona</a>
        <a href="<?php echo $base_url; ?>/nosotros.php" class="menu__enlace <?php echo $pagina_actual === 'nosotros' ? 'activo' : ''; ?>">Sobre Nosotros</a>
    </nav>
