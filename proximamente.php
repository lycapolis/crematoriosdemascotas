<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PAÍS - PLANTILLA GENÉRICA - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 04
 * Fecha: Enero 2026
 *
 * Plantilla genérica para países (excepto España que usa espana.php)
 * URL: /mexico/, /argentina/, etc.
 * ═══════════════════════════════════════════════════════════
 */

// Base URL (necesaria antes del header para redirects)
$base_url = '/crematoriosdemascotas';

// Obtener slug del país desde URL
$pais_slug = isset($_GET['slug']) ? $_GET['slug'] : 'mexico';

// Diccionario de países disponibles (en producción vendría de BD)
$paises = [
    'mexico' => ['nombre' => 'México', 'bandera' => '🇲🇽'],
    'argentina' => ['nombre' => 'Argentina', 'bandera' => '🇦🇷'],
    'colombia' => ['nombre' => 'Colombia', 'bandera' => '🇨🇴'],
    'chile' => ['nombre' => 'Chile', 'bandera' => '🇨🇱'],
    'peru' => ['nombre' => 'Perú', 'bandera' => '🇵🇪'],
    'ecuador' => ['nombre' => 'Ecuador', 'bandera' => '🇪🇨'],
    'venezuela' => ['nombre' => 'Venezuela', 'bandera' => '🇻🇪'],
    'uruguay' => ['nombre' => 'Uruguay', 'bandera' => '🇺🇾'],
    'paraguay' => ['nombre' => 'Paraguay', 'bandera' => '🇵🇾'],
    'bolivia' => ['nombre' => 'Bolivia', 'bandera' => '🇧🇴'],
    'costa-rica' => ['nombre' => 'Costa Rica', 'bandera' => '🇨🇷'],
    'panama' => ['nombre' => 'Panamá', 'bandera' => '🇵🇦'],
    'portugal' => ['nombre' => 'Portugal', 'bandera' => '🇵🇹'],
    'andorra' => ['nombre' => 'Andorra', 'bandera' => '🇦🇩'],
];

// Verificar si el país existe
if (!isset($paises[$pais_slug])) {
    // País no encontrado - redirigir a lista de países
    header('Location: ' . $base_url . '/paises/');
    exit;
}

$pais_nombre = $paises[$pais_slug]['nombre'];
$pais_bandera = $paises[$pais_slug]['bandera'];

$titulo_pagina = 'Crematorios de Mascotas en ' . $pais_nombre;
$pagina_actual = 'directorio';
include 'includes/header.php';
?>

    <!-- ═══════════════════════════════════════════════════════════
         BREADCRUMBS
         ═══════════════════════════════════════════════════════════ -->
    <nav class="breadcrumbs" aria-label="Breadcrumb" style="padding: var(--espacio-tres) 0; background: var(--color-cinco);">
        <div class="contenedor">
            <ol style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--espacio-dos); list-style: none; padding: 0; margin: 0; font-size: var(--fs-uno);">
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo $base_url; ?>/" style="color: var(--color-seis-claro); text-decoration: none;">Inicio</a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="display: flex; align-items: center; gap: var(--espacio-dos);">
                    <a href="<?php echo $base_url; ?>/paises/" style="color: var(--color-seis-claro); text-decoration: none;">Países</a>
                    <i data-lucide="chevron-right" class="icono" style="width: 14px; height: 14px; color: var(--color-seis-claro);"></i>
                </li>
                <li style="color: var(--color-seis); font-weight: var(--peso-medio);">
                    <span><?php echo htmlspecialchars($pais_nombre); ?></span>
                </li>
            </ol>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <main class="seccion">
        <div class="contenedor">

            <!-- Header -->
            <header style="text-align: center; margin-bottom: var(--espacio-siete);">
                <span style="font-size: 4rem; display: block; margin-bottom: var(--espacio-tres);"><?php echo $pais_bandera; ?></span>
                <h1 style="font-size: var(--fs-cuatro); color: var(--color-dos); margin-bottom: var(--espacio-dos);">Crematorios de Mascotas en <?php echo htmlspecialchars($pais_nombre); ?></h1>
                <p class="seccion__descripcion">
                    Directorio de servicios de cremación
                </p>
            </header>

            <!-- Mensaje próximamente -->
            <div style="text-align: center; padding: var(--espacio-ocho) var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); margin-bottom: var(--espacio-siete);">
                <i data-lucide="construction" class="icono" style="width: 64px; height: 64px; color: var(--color-uno); margin-bottom: var(--espacio-cuatro);"></i>
                <h2 style="font-size: var(--fs-tres); color: var(--color-dos); margin-bottom: var(--espacio-tres);">Próximamente</h2>
                <p style="color: var(--color-seis); line-height: 1.7; max-width: 500px; margin: 0 auto var(--espacio-cuatro);">
                    Estamos trabajando para incorporar crematorios de mascotas en <?php echo htmlspecialchars($pais_nombre); ?>.
                    Pronto tendrás disponible el directorio completo con todos los servicios de tu zona.
                </p>
                <a href="<?php echo $base_url; ?>/paises/" class="boton uno">
                    <i data-lucide="arrow-left" class="icono"></i>
                    Ver otros países
                </a>
            </div>

            <!-- Información SEO -->
            <section style="background: var(--color-blanco); padding: var(--espacio-seis); border-radius: var(--radio-dos); border: 1px solid var(--color-cuatro);">
                <h2 style="font-size: var(--fs-dos); color: var(--color-dos); margin-bottom: var(--espacio-cuatro);">Servicios de cremación de mascotas en <?php echo htmlspecialchars($pais_nombre); ?></h2>
                <p style="color: var(--color-seis); line-height: 1.7; margin-bottom: var(--espacio-tres);">
                    En nuestro directorio encontrarás los mejores crematorios de mascotas en <?php echo htmlspecialchars($pais_nombre); ?>.
                    Todos los servicios listados ofrecen un trato digno y respetuoso para tu compañero fiel.
                </p>
                <p style="color: var(--color-seis); line-height: 1.7; margin: 0;">
                    Si conoces algún crematorio de mascotas en <?php echo htmlspecialchars($pais_nombre); ?> que debería estar en nuestro directorio,
                    no dudes en contactarnos para agregarlo.
                </p>
            </section>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>
