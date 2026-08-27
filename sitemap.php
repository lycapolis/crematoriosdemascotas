<?php
/**
 * ═══════════════════════════════════════════════════════════
 * SITEMAP XML DINÁMICO
 * ═══════════════════════════════════════════════════════════
 *
 * Proyecto: Crematorios de Mascotas
 * Autor: Lycapolis LLC
 * Fecha: Enero 2026
 *
 * Genera sitemap.xml dinámicamente desde la base de datos
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';  // slugificar()

// Header XML
header('Content-Type: application/xml; charset=utf-8');

// URL base para producción
$baseUrl = (ENTORNO === 'produccion') ? BASE_URL : 'https://crematoriosdemascotas.com';

// Fecha actual para lastmod
$hoy = date('Y-m-d');

// Iniciar XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

// ═══════════════════════════════════════════════════════════
// PÁGINAS ESTÁTICAS
// ═══════════════════════════════════════════════════════════

$paginasEstaticas = [
    ['url' => '/',                    'priority' => '1.0',  'changefreq' => 'daily'],
    ['url' => '/directorio.php',      'priority' => '0.9',  'changefreq' => 'daily'],
    ['url' => '/espana',              'priority' => '0.9',  'changefreq' => 'weekly'],
    ['url' => '/nosotros.php',        'priority' => '0.6',  'changefreq' => 'monthly'],
    ['url' => '/contacto.php',        'priority' => '0.6',  'changefreq' => 'monthly'],
    
    ['url' => '/privacidad.php',      'priority' => '0.3',  'changefreq' => 'yearly'],
    ['url' => '/terminos.php',        'priority' => '0.3',  'changefreq' => 'yearly'],
    ['url' => '/cookies.php',         'priority' => '0.3',  'changefreq' => 'yearly'],
];

foreach ($paginasEstaticas as $pagina) {
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . htmlspecialchars($baseUrl . $pagina['url']) . '</loc>' . PHP_EOL;
    echo '    <lastmod>' . $hoy . '</lastmod>' . PHP_EOL;
    echo '    <changefreq>' . $pagina['changefreq'] . '</changefreq>' . PHP_EOL;
    echo '    <priority>' . $pagina['priority'] . '</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}

// ═══════════════════════════════════════════════════════════
// URLS DINÁMICAS DESDE BASE DE DATOS
// ═══════════════════════════════════════════════════════════

$pdo = obtenerConexion();

try {
if ($pdo) {

    // -----------------------------------------------------------
    // COMUNIDADES AUTÓNOMAS
    // -----------------------------------------------------------
    $sql = "SELECT c.slug, c.updated_at FROM comunidades_autonomas c INNER JOIN provincias p ON p.comunidad_id = c.id INNER JOIN crematorios cr ON cr.provincia_id = p.id WHERE cr.estado = 'activa' GROUP BY c.slug, c.updated_at ORDER BY c.nombre";
    $stmt = $pdo->query($sql);
    $comunidades = $stmt->fetchAll();

    foreach ($comunidades as $comunidad) {
        $lastmod = !empty($comunidad['updated_at']) ? date('Y-m-d', strtotime($comunidad['updated_at'])) : $hoy;
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . '/espana/comunidad/' . $comunidad['slug']) . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>0.8</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }

    // -----------------------------------------------------------
    // PROVINCIAS
    // -----------------------------------------------------------
    $sql = "SELECT p.slug, p.updated_at FROM provincias p INNER JOIN crematorios cr ON cr.provincia_id = p.id WHERE cr.estado = 'activa' GROUP BY p.slug, p.updated_at ORDER BY p.nombre";
    $stmt = $pdo->query($sql);
    $provincias = $stmt->fetchAll();

    foreach ($provincias as $provincia) {
        $lastmod = !empty($provincia['updated_at']) ? date('Y-m-d', strtotime($provincia['updated_at'])) : $hoy;
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . '/espana/' . $provincia['slug']) . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>0.8</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }

    // -----------------------------------------------------------
    // CIUDADES (únicas desde crematorios) — solo con fichas activas.
    // El slug se calcula con slugificar() en PHP (normaliza acentos);
    // el viejo REPLACE SQL generaba URLs rotas como /barcelona/polinyà.
    // -----------------------------------------------------------
    $sql = "SELECT DISTINCT
                c.ciudad,
                p.slug AS provincia_slug,
                MAX(c.updated_at) AS updated_at
            FROM crematorios c
            INNER JOIN provincias p ON c.provincia_id = p.id
            WHERE c.ciudad IS NOT NULL AND c.ciudad != ''
              AND c.estado = 'activa'
            GROUP BY c.ciudad, p.slug
            ORDER BY p.slug, c.ciudad";
    $stmt = $pdo->query($sql);
    $ciudades = $stmt->fetchAll();

    foreach ($ciudades as $ciudad) {
        $lastmod    = !empty($ciudad['updated_at']) ? date('Y-m-d', strtotime($ciudad['updated_at'])) : $hoy;
        $ciudadSlug = slugificar($ciudad['ciudad']);
        if ($ciudadSlug === '') continue;
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . '/espana/' . $ciudad['provincia_slug'] . '/' . $ciudadSlug) . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>0.7</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }

    // -----------------------------------------------------------
    // FICHAS DE CREMATORIOS — solo activas y cerradas.
    // Las pausadas/archivadas dan 404 → no deben ir al sitemap.
    // Las cerradas siguen visibles (con aviso) → se mantienen indexables.
    // -----------------------------------------------------------
    $sql = "SELECT slug, updated_at FROM crematorios
            WHERE estado IN ('activa', 'cerrada')
            ORDER BY nombre";
    $stmt = $pdo->query($sql);
    $crematorios = $stmt->fetchAll();

    foreach ($crematorios as $crematorio) {
        $lastmod = !empty($crematorio['updated_at']) ? date('Y-m-d', strtotime($crematorio['updated_at'])) : $hoy;
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . '/' . $crematorio['slug']) . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>0.9</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }
}
} catch (Exception $e) {
    // Error en BD - continuar sin URLs dinámicas
    // El XML tendrá al menos las páginas estáticas
}

// Cerrar XML
echo '</urlset>' . PHP_EOL;
