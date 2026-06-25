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


    <!-- Leaflet (solo en páginas con mapa) -->
    <?php if (!empty($usar_leaflet) || !empty($usar_leaflet_mapa)): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php endif; ?>
    <?php if (!empty($usar_leaflet_mapa)): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="<?php echo assetUrl('assets/js/mapa-leaflet-pines.js'); ?>"></script>
    <?php endif; ?>

    <!-- CSS (con cache-busting filemtime para que el browser baje versión nueva al actualizar) -->
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/variables.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/componentes.css'); ?>">

    <!-- Form controls + Tom Select (capa compartida admin + público, paleta cálida) -->
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/librerias/tom-select/tom-select.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/librerias/notyf/notyf.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/forms.css'); ?>">

    <!-- Fuentes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-TDLMC4BH');</script>
    <!-- End Google Tag Manager -->

    <!-- UTM Tracker: Preserva SOLO params de attribution (utm_*, cmas_*, gclid, fbclid)
         durante la navegación. NO propaga filtros internos (orden, geo, ciudad, etc.). -->
    <script>
    (function() {
        // Solo estos prefijos/nombres se persisten en sessionStorage
        function esParamPersistible(key) {
            return key.startsWith('utm_')
                || key.startsWith('cmas_')
                || key === 'gclid'
                || key === 'fbclid'
                || key === 'msclkid';
        }

        // LIMPIEZA: si sessionStorage tiene basura de versiones anteriores
        // (params de filtros internos), descartarla.
        try {
            var stored = sessionStorage.getItem('url_params');
            if (stored) {
                var parsed = JSON.parse(stored);
                var limpio = {};
                Object.keys(parsed).forEach(function(k) {
                    if (esParamPersistible(k) && parsed[k] !== '') limpio[k] = parsed[k];
                });
                if (Object.keys(limpio).length === 0) {
                    sessionStorage.removeItem('url_params');
                } else {
                    sessionStorage.setItem('url_params', JSON.stringify(limpio));
                }
            }
        } catch (e) { sessionStorage.removeItem('url_params'); }

        var currentParams = new URLSearchParams(window.location.search);
        var storedParams  = sessionStorage.getItem('url_params');

        // Capturar params persistibles de la URL actual (con value NO vacío)
        if (currentParams.toString()) {
            var paramsObj = storedParams ? JSON.parse(storedParams) : {};
            var changed   = false;
            currentParams.forEach(function(value, key) {
                if (esParamPersistible(key) && value !== '') {
                    paramsObj[key] = value;
                    changed = true;
                }
            });
            if (changed) {
                storedParams = JSON.stringify(paramsObj);
                sessionStorage.setItem('url_params', storedParams);
            }
        }

        // Aplicar params guardados a links internos (sin pisar los que ya tienen value propio)
        if (storedParams) {
            var paramsObj   = JSON.parse(storedParams);
            var currentHost = window.location.host;

            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('a[href]').forEach(function(link) {
                    try {
                        var url = new URL(link.href, window.location.origin);
                        if (url.host === currentHost
                            || link.getAttribute('href').startsWith('/')
                            || link.getAttribute('href').startsWith('?')) {
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

    <!-- WhatsApp Chat Widget (Lycapolis) — DESACTIVADO 2026-05-21.
         Reemplazado por widget interno propio (modal lead-capture).
         Ver: assets/js/lead-capture.js + includes/componentes/modal-lead-capture.php
         Mantiene mismo webhook (Make), mismo JSON estructurado + campos contextuales nuevos. -->
    <!--<script defer src="https://whatsapp.lycapolis.com/install-widget/bundle.js?key=1169a891-ad22-4a8e-9587-c3ee25af72a5"></script>-->     
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
                <!-- Búsqueda compacta -->
                <form class="header__buscador" action="<?php echo $base_url; ?>/directorio.php" method="GET" role="search">
                    <i data-lucide="search" class="icono"></i>
                    <input
                        type="text"
                        name="busqueda"
                        class="header__buscador-campo"
                        placeholder="Buscar crematorio..."
                        aria-label="Buscar crematorio"
                    >
                </form>

                <a href="<?php echo $base_url; ?>/directorio.php" class="menu__enlace <?php echo $pagina_actual === 'directorio' ? 'activo' : ''; ?>">Directorio</a>

                <button id="btn-cerca-header" type="button" class="boton dos pequeno" onclick="irACerca(this)">
                    <i data-lucide="map-pin" class="icono"></i>
                    Cerca de mí
                </button>

                <a href="<?php echo $base_url; ?>/nosotros.php" class="menu__enlace <?php echo $pagina_actual === 'nosotros' ? 'activo' : ''; ?>">Nosotros</a>
            </nav>

            <!-- Botón Menú Móvil — toggle entre hamburguesa y X (lo cambia toggleMenu) -->
            <button id="btn-menu-movil" class="header__boton-movil" onclick="toggleMenu()" aria-label="Abrir menú">
                <i data-lucide="menu" class="icono"></i>
            </button>
        </div>
    </header>

    <!-- Menú Móvil (overlay) -->
    <nav id="menu-movil" class="menu movil">
        <!-- El botón cerrar interno (X) se eliminó: el header sigue visible
             por encima del menú overlay (z-index 1600 vs 1500), así que el
             botón hamburguesa del header se convierte en X y hace toggle. -->

        <!-- Acciones: las 2 formas de encontrar un crematorio -->
        <form class="header__buscador" action="<?php echo $base_url; ?>/directorio.php" method="GET" role="search" style="width: 100%; max-width: 320px;">
            <i data-lucide="search" class="icono"></i>
            <input type="text" name="busqueda" class="header__buscador-campo" placeholder="Buscar crematorio..." aria-label="Buscar crematorio" style="width: 100%;">
        </form>

        <button id="btn-cerca-movil" type="button" class="boton dos" onclick="irACerca(this)" style="width: 100%; max-width: 320px;">
            <i data-lucide="map-pin" class="icono"></i>
            Cerca de mí
        </button>

        <div class="menu__sep"></div>

        <!-- Navegación -->
        <a href="<?php echo $base_url; ?>/directorio.php" class="menu__enlace <?php echo $pagina_actual === 'directorio' ? 'activo' : ''; ?>">Directorio</a>
        <a href="<?php echo $base_url; ?>/nosotros.php" class="menu__enlace <?php echo $pagina_actual === 'nosotros' ? 'activo' : ''; ?>">Nosotros</a>
    </nav>

    <script>
    /**
     * irACerca — handler global para todos los botones "Cerca de mí" del sitio
     * (header desktop, header móvil, home hero, sidebar directorio).
     * Bloquea el tamaño del botón antes de cambiar el contenido para que no
     * se mueva el resto del layout, y muestra spinner + "Buscando".
     */
    function irACerca(btnEl) {
        if (!navigator.geolocation) {
            var m1 = 'Tu navegador no soporta la geolocalización.';
            if (window.toast) { toast.error(m1); } else { alert(m1); }
            return;
        }
        if (btnEl) {
            // Lock dimensiones para evitar layout shift cuando cambia el contenido
            var r = btnEl.getBoundingClientRect();
            btnEl.style.width  = r.width  + 'px';
            btnEl.style.height = r.height + 'px';
            btnEl.dataset.origInner = btnEl.innerHTML;
            btnEl.disabled = true;
            btnEl.innerHTML = '<i data-lucide="loader-2" class="icono" style="animation:spin 1s linear infinite"></i> Buscando';
            if (window.lucide) lucide.createIcons({ nodes: [btnEl] });
        }
        navigator.geolocation.getCurrentPosition(
            function(p) { window.location.href = '<?php echo $base_url; ?>/cerca.php?lat=' + p.coords.latitude + '&lng=' + p.coords.longitude; },
            function() {
                var m2 = 'No se pudo obtener tu ubicación. Permite el acceso a la geolocalización.';
                if (window.toast) { toast.error(m2); } else { alert(m2); }
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.style.width = '';
                    btnEl.style.height = '';
                    btnEl.innerHTML = btnEl.dataset.origInner || btnEl.innerHTML;
                    if (window.lucide) lucide.createIcons({ nodes: [btnEl] });
                }
            },
            { timeout: 8000 }
        );
    }
    </script>
