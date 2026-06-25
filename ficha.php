<?php
/**
 * ═══════════════════════════════════════════════════════════
 * FICHA CREMATORIO - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Web: https://lycapolis.com
 *
 * Versión: 04
 * Fecha: Enero 2026
 *
 * Detalle de un crematorio específico
 * URL: /nombre-del-crematorio (raíz)
 * ═══════════════════════════════════════════════════════════
 */

// Incluir configuración para acceso a funciones (sin header aún)
require_once 'includes/config.php';
require_once 'includes/conexion_db.php';
require_once 'includes/funciones.php';

// ═══════════════════════════════════════════════════════════
// OBTENER CREMATORIO POR SLUG
// ═══════════════════════════════════════════════════════════
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Obtener crematorio de la base de datos
$crematorio = obtenerCrematorioSlug($slug);

// Si no existe, mostrar 404
if (!$crematorio) {
    http_response_code(404);
    $titulo_pagina = 'Crematorio no encontrado';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="search-x" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Crematorio no encontrado</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">El crematorio que buscas no existe o ha sido eliminado.</p>
            <a href="<?php echo BASE_URL; ?>/directorio.php" class="boton uno">Ver todos los crematorios</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// ═══════════════════════════════════════════════════════════
// CICLO DE VIDA DE LA FICHA (estado)
// ═══════════════════════════════════════════════════════════
//   activa     → se muestra normal
//   cerrada    → se muestra con badge "Cerrado permanentemente" (estilo Google)
//   pausada    → 404 (pausa temporal, no debe ser indexable ni visible)
//   archivada  → 404 (soft-delete)
$estadoFicha    = $crematorio['estado'] ?? 'activa';
$negocioCerrado = ($estadoFicha === 'cerrada');

if (in_array($estadoFicha, ['pausada', 'archivada'], true)) {
    http_response_code(404);
    $titulo_pagina = 'Crematorio no disponible';
    $pagina_actual = '';
    include 'includes/header.php';
    ?>
    <section class="seccion" style="text-align: center; padding: var(--espacio-siete) 0;">
        <div class="contenedor">
            <i data-lucide="search-x" style="width: 64px; height: 64px; color: var(--color-cinco); margin-bottom: var(--espacio-cuatro);"></i>
            <h1 style="color: var(--color-dos); margin-bottom: var(--espacio-tres);">Ficha no disponible</h1>
            <p style="color: var(--color-seis-claro); margin-bottom: var(--espacio-cinco);">Esta ficha no está disponible en este momento.</p>
            <a href="<?php echo BASE_URL; ?>/directorio.php" class="boton uno">Ver todos los crematorios</a>
        </div>
    </section>
    <?php
    include 'includes/footer.php';
    exit;
}

// Variables para facilitar el uso en la plantilla
$crematorio_nombre = $crematorio['nombre'];
$crematorio_slug = $crematorio['slug'];
$ciudad_nombre = $crematorio['ciudad'] ?? '';
$ciudad_slug = strtolower(str_replace([' ', ','], ['-', ''], $ciudad_nombre));
$provincia_nombre = $crematorio['provincia_nombre'] ?? '';
$provincia_slug = $crematorio['provincia_slug'] ?? '';
$comunidad_nombre = $crematorio['comunidad_nombre'] ?? '';
$comunidad_slug = $crematorio['comunidad_slug'] ?? '';

// ─── Datos de contacto ───────────────────────────────────────────────────────
$direccion   = $crematorio['direccion_completa'] ?? '';
$telefono    = $crematorio['telefono'] ?? '';
$whatsapp    = $crematorio['whatsapp'] ?? '';
$email       = $crematorio['email'] ?? '';
$web         = $crematorio['website'] ?? '';

// Nuevos JSON de contacto
$fTelefonos = json_decode($crematorio['telefonos_json'] ?? '', true) ?: [];
$fEmails    = json_decode($crematorio['emails_json']    ?? '', true) ?: [];
$fRedesRaw  = json_decode($crematorio['redes_json']     ?? '', true) ?: [];
$fRedes     = is_array($fRedesRaw['entries'] ?? null) ? $fRedesRaw['entries'] : (is_array($fRedesRaw) && !isset($fRedesRaw['entries']) ? $fRedesRaw : []);
$fRedesModo = $fRedesRaw['modo'] ?? 'iconos';
// Solo elementos marcados como visibles
$fTelefonosVis = array_values(array_filter($fTelefonos, fn($t) => !empty($t['visible']) && !empty($t['numero'])));
$fEmailsVis    = array_values(array_filter($fEmails,    fn($e) => !empty($e['visible']) && !empty($e['email'])));
$fRedesVis     = array_values(array_filter($fRedes,     fn($r) => !empty($r['visible']) && !empty($r['url'])));
// Fallback al campo plano si no hay JSON
if (empty($fTelefonosVis) && $telefono) $fTelefonosVis = [['label'=>'Teléfono','numero'=>$telefono,'tipo'=>'principal']];
if (empty($fEmailsVis)    && $email)    $fEmailsVis    = [['label'=>'Email','email'=>$email,'tipo'=>'general']];

// Mapa de iconos lucide por red social
$redesIconos = ['facebook'=>'facebook','instagram'=>'instagram','x'=>'twitter','tiktok'=>'music-2',
                'youtube'=>'youtube','linkedin'=>'linkedin','google'=>'map-pin',
                'pinterest'=>'image','vimeo'=>'video','custom'=>'link'];
$redesColores = ['facebook'=>'#1877f2','instagram'=>'#e4405f','x'=>'#000','tiktok'=>'#010101',
                 'youtube'=>'#ff0000','linkedin'=>'#0a66c2','google'=>'#4285f4',
                 'pinterest'=>'#e60023','vimeo'=>'#1ab7ea','custom'=>'var(--color-seis)'];
$valoracion  = $crematorio['rating'] ?? 0;
$num_resenas = $crematorio['reviews_total'] ?? 0;
$descripcion = $crematorio['descripcion'] ?? '';
$destacado   = !empty($crematorio['destacado']);

$horario_texto      = $crematorio['horario_texto'] ?? '';
$horarios_json_raw  = $crematorio['horarios'] ?? '';
$ciudades_cobertura = $crematorio['ciudades_cobertura'] ?? '';

// Tier y coordenadas
$tier            = $crematorio['tier'] ?? '01';
$lat_mapa        = !empty($crematorio['latitud'])  ? (float) $crematorio['latitud']  : null;
$lng_mapa        = !empty($crematorio['longitud']) ? (float) $crematorio['longitud'] : null;
$google_place_id = $crematorio['google_place_id'] ?? '';
$usar_leaflet    = ($lat_mapa !== null && $lng_mapa !== null && $tier === '01');

// Servicios y extras desde booleanos DB
$servicios_lista = [];
$extras_lista    = [];
foreach ([
    'atencion_24h'         => 'Atención 24 horas',
    'recogida_domicilio'   => 'Recogida a domicilio',
    'entrega_domicilio'    => 'Entrega a domicilio',
    'cremacion_individual' => 'Cremación individual',
    'cremacion_colectiva'  => 'Cremación colectiva',
    'sala_velatoria'       => 'Sala velatoria',
] as $col => $label) {
    if (!empty($crematorio[$col])) $servicios_lista[] = $label;
}
foreach ([
    'urna'       => 'Urna personalizada',
    'carta'      => 'Carta de recuerdo',
    'molde'      => 'Molde de huella',
    'souvenires' => 'Souvenires',
] as $col => $label) {
    if (!empty($crematorio[$col])) $extras_lista[] = $label;
}
// Fallback a columna de texto si no hay booleanos
if (empty($servicios_lista) && !empty($crematorio['servicios'])) {
    $servicios_lista = array_filter(array_map('trim', explode(',', $crematorio['servicios'])));
}
if (empty($extras_lista) && !empty($crematorio['prestaciones'])) {
    $extras_lista = array_filter(array_map('trim', explode(',', $crematorio['prestaciones'])));
}

// Precios (precios_json) — lista de ítems {tipo,nombre,descripcion,min,max,destacado}
$precios_lista = [];
if (!empty($crematorio['precios_json'])) {
    $tmpPrecios = json_decode($crematorio['precios_json'], true);
    if (is_array($tmpPrecios)) {
        // Sólo ítems con nombre — descartar filas vacías que quedaron del editor
        $precios_lista = array_values(array_filter($tmpPrecios, function ($p) {
            return is_array($p) && !empty(trim($p['nombre'] ?? ''));
        }));
    }
}

/**
 * Formatea el monto de un ítem de precio según su tipo.
 * Devuelve string vacío para tipo 'custom' (esos no muestran monto).
 */
function formatearPrecioItem(array $p): string {
    $simbolo = defined('MONEDA_SIMBOLO') ? MONEDA_SIMBOLO : '€';
    $fmt = function ($n) use ($simbolo) {
        return number_format((float)$n, (floor((float)$n) == (float)$n ? 0 : 2), ',', '.') . ' ' . $simbolo;
    };
    $min = $p['min'] ?? null;
    $max = $p['max'] ?? null;
    switch ($p['tipo'] ?? 'custom') {
        case 'fijo':  return ($min !== null && $min !== '') ? $fmt($min) : '';
        case 'desde': return ($min !== null && $min !== '') ? 'Desde ' . $fmt($min) : '';
        case 'rango':
            if ($min !== null && $min !== '' && $max !== null && $max !== '') return $fmt($min) . ' – ' . $fmt($max);
            if ($min !== null && $min !== '') return 'Desde ' . $fmt($min);
            return '';
        default: return ''; // custom
    }
}

// Helper local: ruta relativa → URL absoluta
function resolverUrlImagen(string $ruta): string {
    return str_starts_with($ruta, 'http') ? $ruta : BASE_URL . '/' . $ruta;
}

// Obtener imágenes y reglas del tier
$crematorio_imagenes = obtenerImagenesCrematorio($crematorio['id']);
$_all_tier_rules = obtenerTierRules();
$tier_rules = $_all_tier_rules[$tier] ?? $_all_tier_rules['01'] ?? [
    'logo'               => ['mostrar' => true,  'fuentes' => ['local','url']],
    'portada'            => ['mostrar' => true,  'fuentes' => ['local','url']],
    'galeria_principal'  => ['mostrar' => false, 'fuentes' => []],
    'galeria_categorias' => ['mostrar' => false, 'fuentes' => []],
];

// Helper: ¿la imagen cumple las fuentes permitidas por el tier para una sección?
$imagenPermitida = function(array $img, array $regla): bool {
    if (!$regla['mostrar'] || empty($regla['fuentes'])) return false;
    $esUrl = str_starts_with($img['ruta'], 'http');
    return ($esUrl && in_array('url', $regla['fuentes']))
        || (!$esUrl && in_array('local', $regla['fuentes']));
};

// ── LOGO ─────────────────────────────────────────────────────────────────────
$logo_url = null;
$reglaLogo = $tier_rules['logo'];
if ($reglaLogo['mostrar']) {
    // Respetar el orden de prioridad de fuentes definido en el tier
    // Si fuentes = ["local","url"], primero busca un logo local; si no hay, prueba URL
    foreach ($reglaLogo['fuentes'] as $fuente) {
        foreach ($crematorio_imagenes['logos'] as $img) {
            $esUrl = str_starts_with($img['ruta'], 'http');
            if (($fuente === 'local' && !$esUrl) || ($fuente === 'url' && $esUrl)) {
                $logo_url = resolverUrlImagen($img['ruta']);
                break 2;
            }
        }
    }
}

// ── PORTADA ───────────────────────────────────────────────────────────────────
$portada_url        = null;
$portada_alt        = $crematorio_nombre;
$portada_galeria_id = null;
$reglaPortada       = $tier_rules['portada'];

if ($reglaPortada['mostrar']) {
    // 1. Portada explícita (tipo=portada)
    $imgP = $crematorio_imagenes['portada'];
    if ($imgP && $imagenPermitida($imgP, $reglaPortada)) {
        $portada_url = resolverUrlImagen($imgP['ruta']);
        $portada_alt = $imgP['alt_text'] ?: $crematorio_nombre;
    }
    // 2. Primera imagen de galería respetando prioridad de fuentes del tier
    if (!$portada_url) {
        foreach ($reglaPortada['fuentes'] as $fuente) {
            foreach ($crematorio_imagenes['galeria'] as $img) {
                $esUrl = str_starts_with($img['ruta'], 'http');
                if (($fuente === 'local' && !$esUrl) || ($fuente === 'url' && $esUrl)) {
                    $portada_url        = resolverUrlImagen($img['ruta']);
                    $portada_alt        = $img['alt_text'] ?: $crematorio_nombre;
                    $portada_galeria_id = $img['id'];
                    break 2;
                }
            }
        }
    }
}

// ── GALERÍA PRINCIPAL ─────────────────────────────────────────────────────────
$galeria_display = [];
$reglaGaleria    = $tier_rules['galeria_principal'];

if ($reglaGaleria['mostrar']) {
    $pool = [];
    foreach ($crematorio_imagenes['galeria'] as $img) {
        if ($img['id'] === $portada_galeria_id) continue; // excluir la usada como portada
        if ($imagenPermitida($img, $reglaGaleria)) {
            $pool[] = $img;
        }
    }
    shuffle($pool);
    $galeria_display = $pool;
}

// ── GALERÍAS POR CATEGORÍA ────────────────────────────────────────────────────
$galeria_grupos_display = [];
$reglaGrupos            = $tier_rules['galeria_categorias'];

if ($reglaGrupos['mostrar']) {
    foreach (GALERIA_GRUPOS as $grupoKey => $grupoDef) {
        $imgs = [];
        foreach ($crematorio_imagenes['galeria'] as $img) {
            if ($img['id'] === $portada_galeria_id) continue;
            if (!in_array($img['categoria'] ?? '', $grupoDef['categorias'])) continue;
            if ($imagenPermitida($img, $reglaGrupos)) {
                $imgs[] = $img;
            }
        }
        if (!empty($imgs)) {
            if ($grupoKey === 'clientes') shuffle($imgs);
            $galeria_grupos_display[$grupoKey] = [
                'label'  => $grupoDef['label'],
                'images' => $imgs,
            ];
        }
    }
}

// ── LIGHTBOX (portada + galería principal + galerías por categoría + clientes) ─
// Helper: arma el item para el lightbox a partir de una imagen
$buildLbItem = function (array $img, string $fallbackAlt) {
    $item = [
        'url' => resolverUrlImagen($img['ruta']),
        'alt' => $img['alt_text'] ?: $fallbackAlt,
    ];
    // Si la imagen viene de un cliente (tipo=cliente o flag desde_cliente), incluir info de reseña
    if (($img['tipo'] ?? '') === 'cliente' || !empty($img['desde_cliente'])) {
        $item['esCliente'] = true;
        if (!empty($img['resena_nombre'])) {
            $item['resenaNombre']      = $img['resena_nombre'];
            $item['resenaComentario']  = $img['resena_comentario']  ?? '';
            $item['resenaCalificacion']= (int)($img['resena_calificacion'] ?? 0);
            $item['resenaFecha']       = $img['resena_fecha'] ?? '';
        }
    }
    return $item;
};

$lightbox_images = [];
if ($portada_url) {
    $lightbox_images[] = ['url' => $portada_url, 'alt' => $portada_alt];
}
foreach ($galeria_display as $i => $img) {
    $galeria_display[$i]['lb_idx'] = count($lightbox_images);
    $lightbox_images[] = $buildLbItem($img, $crematorio_nombre);
}
// Galerías por categoría
foreach ($galeria_grupos_display as $gKey => $grupo) {
    foreach ($galeria_grupos_display[$gKey]['images'] as $i => $img) {
        $galeria_grupos_display[$gKey]['images'][$i]['lb_idx'] = count($lightbox_images);
        $lightbox_images[] = $buildLbItem($img, $crematorio_nombre);
    }
}
// Galería "Fotos de clientes" (separada) — todas las imágenes tipo='cliente' visibles
$clientes_display = $crematorio_imagenes['clientes'] ?? [];
foreach ($clientes_display as $i => $img) {
    $clientes_display[$i]['lb_idx'] = count($lightbox_images);
    $lightbox_images[] = $buildLbItem($img, $crematorio_nombre);
}

// #3 Galería separada "Fotos de clientes": excluye las marcadas 'solo_resena'
// (esas solo deben verse bajo su reseña). 'oculta' ya fue filtrada en funciones.php;
// 'completa' y 'solo_galerias_cliente' sí van acá.
$clientes_galeria_display = array_values(array_filter(
    $clientes_display,
    fn($i) => ($i['visibilidad'] ?? 'completa') !== 'solo_resena'
));

// #4 Imágenes de cliente agrupadas por reseña — mini-galería bajo cada reseña.
// Usa el set completo (todo lo no-'oculta'): completa + solo_galerias_cliente + solo_resena.
$imagenes_por_resena = [];
foreach ($clientes_display as $img) {
    if (empty($img['resena_id'])) continue;
    $rid = (int) $img['resena_id'];
    if (!isset($imagenes_por_resena[$rid])) $imagenes_por_resena[$rid] = [];
    $imagenes_por_resena[$rid][] = $img;
}

$lightbox_json = json_encode($lightbox_images, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

// Título de página y header
$titulo_pagina    = $crematorio_nombre . ' - Crematorios de Mascotas';
$meta_descripcion = !empty($crematorio['meta_description_seo'])
    ? $crematorio['meta_description_seo']
    : ($crematorio['descripcion_google'] ?? SITIO_DESCRIPCION);
$og_image = $portada_url ?: SEO_DEFAULT_IMAGE;
$pagina_actual = 'directorio';

// Schema.org LocalBusiness
$schema_url = 'https://crematoriosdemascotas.com/' . $crematorio_slug;
$schema_data = [
    '@context' => 'https://schema.org',
    '@type'    => 'LocalBusiness',
    'name'     => $crematorio_nombre,
    'url'      => $schema_url,
    'image'    => $portada_url ?: null,
    'description' => !empty($crematorio['descripcion'])
        ? mb_substr(str_replace('**', '', strip_tags($crematorio['descripcion'])), 0, 300)
        : null,
    'address'  => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $crematorio['calle'] ?? '',
        'addressLocality' => $ciudad_nombre,
        'addressRegion'   => $provincia_nombre,
        'postalCode'      => $crematorio['codigo_postal'] ?? '',
        'addressCountry'  => 'ES',
    ],
    'telephone' => $telefono ?: null,
    'email'     => $email ?: null,
];

// Coordenadas
if (!empty($crematorio['latitud']) && !empty($crematorio['longitud'])) {
    $schema_data['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => (float) $crematorio['latitud'],
        'longitude' => (float) $crematorio['longitud'],
    ];
}

// Valoración agregada
if (!empty($crematorio['rating']) && $crematorio['rating'] > 0 && !empty($crematorio['reviews_total'])) {
    $schema_data['aggregateRating'] = [
        '@type'       => 'AggregateRating',
        'ratingValue' => number_format((float) $crematorio['rating'], 1, '.', ''),
        'reviewCount' => (int) $crematorio['reviews_total'],
        'bestRating'  => '5',
        'worstRating' => '1',
    ];
}

// Horario (si disponible)
$schema_horario_txt = $horario_texto;
if (!empty($horarios_json_raw)) {
    $hd = json_decode($horarios_json_raw, true);
    if (is_array($hd) && isset($hd['presencial'])) {
        $schema_horario_txt = ''; // se genera desde JSON
        $hPres = $hd['presencial'];
        $diasLvSchema = ['lunes','martes','miercoles','jueves','viernes'];
        $hasInd = !empty(array_intersect_key($hPres, array_flip($diasLvSchema)));
        $schemaSpecs = [];
        $mapDow = ['lunes'=>'Monday','martes'=>'Tuesday','miercoles'=>'Wednesday',
                   'jueves'=>'Thursday','viernes'=>'Friday','s'=>'Saturday','d'=>'Sunday'];
        $parseSchemaRange = function($val) {
            if (!$val || $val === null) return null;
            if ($val === '24h') return ['00:00','23:59'];
            $parts = explode('-', explode(' y ', $val)[0]);
            return count($parts) === 2 ? $parts : null;
        };
        $addSpec = function($dow, $val) use (&$schemaSpecs, $parseSchemaRange) {
            $range = $parseSchemaRange($val);
            if (!$range) return;
            $schemaSpecs[] = ['@type'=>'OpeningHoursSpecification','dayOfWeek'=>'https://schema.org/'.$dow,'opens'=>$range[0],'closes'=>$range[1]];
        };
        if (!$hasInd && isset($hPres['lv'])) {
            foreach (['Monday','Tuesday','Wednesday','Thursday','Friday'] as $dow) $addSpec($dow, $hPres['lv']);
        } else {
            foreach ($diasLvSchema as $d) { if (isset($hPres[$d])) $addSpec($mapDow[$d], $hPres[$d]); }
        }
        if (isset($hPres['s'])) $addSpec('Saturday', $hPres['s']);
        if (isset($hPres['d'])) $addSpec('Sunday',   $hPres['d']);
        if ($schemaSpecs) $schema_data['openingHoursSpecification'] = $schemaSpecs;
    }
}
if (!isset($schema_data['openingHoursSpecification']) && !empty($schema_horario_txt)) {
    $schema_data['openingHoursSpecification'] = ['@type' => 'OpeningHoursSpecification', 'description' => $schema_horario_txt];
}

// Limpiar nulls del schema
$schema_data = array_filter($schema_data, fn($v) => $v !== null && $v !== '');

include 'includes/header.php';
?>

<style>
    /* ═════════════════════════════════════════════════════════
       FICHA — refresh visual Fase 8
       ═════════════════════════════════════════════════════════ */

    /* ── Encabezado de ficha: badges + meta inline ── */
    .ficha-encabezado__badges {
        display: flex;
        flex-wrap: wrap;
        gap: var(--espacio-dos);
    }
    .ficha-encabezado__badge {
        display: inline-flex;
        align-items: center;
        gap: var(--espacio-uno);
        padding: var(--espacio-uno) var(--espacio-tres);
        border-radius: var(--radio-full);
        font-size: var(--fs-dos);
        font-weight: var(--peso-medio);
        color: #fff;
    }
    .ficha-encabezado__badge .icono { width: 14px; height: 14px; }
    .ficha-encabezado__badge--destacado  { background: var(--color-uno); color: var(--color-ocho); }
    .ficha-encabezado__badge--registrado { background: var(--color-tres); }
    .ficha-encabezado__badge--verificado { background: var(--color-diez); }

    .ficha-encabezado__meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--espacio-tres);
        align-items: center;
        font-size: var(--fs-dos);     /* 14px en vez del 12px heredado */
    }
    .ficha-encabezado__metaitem {
        display: inline-flex;
        align-items: center;
        gap: var(--espacio-uno);
    }
    .ficha-encabezado__metaitem .icono { width: 16px; height: 16px; }

    /* ── Sidebar sticky de contacto ──
       top: ~34px más abajo del header del sitio (header ~72px + 34 = 106). */
    .ficha-sidebar__sticky {
        position: sticky;
        top: 106px;
        background: var(--admin-superficie);
        border: 1px solid var(--admin-linea);
        border-radius: var(--radio-dos);
        box-shadow: var(--admin-sombra-suave);
        padding: var(--espacio-tres);
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
    }
    /* Header del sticky: logo izq + nombre + ciudad al lado */
    .ficha-sidebar__header {
        display: flex;
        align-items: center;
        gap: var(--espacio-tres);
        min-width: 0;
    }
    /* Logo: altura fija 100px, ancho ADAPTATIVO (max 100px).
       - Logo cuadrado (1:1)  → 100x100, ocupa todo el bloque.
       - Logo horizontal (2:1) → 100w × 50h centrado vertical.
       - Logo vertical (1:2)  → 50w × 100h → libera ~50px que el texto del nombre/ciudad
         a la derecha aprovecha automáticamente (flex se reajusta solo). */
    .ficha-sidebar__logo {
        flex-shrink: 0;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--color-cinco);
        border-radius: var(--radio-uno);
        overflow: hidden;
    }
    .ficha-sidebar__logo img {
        max-width: 100px;
        max-height: 100px;
        width: auto;
        height: auto;
        object-fit: contain;
        display: block;
    }
    .ficha-sidebar__nombre-wrap {
        min-width: 0;
        flex: 1;
    }
    .ficha-sidebar__nombre {
        font-size: var(--fs-tres);
        color: var(--color-dos);
        margin: 0;
        line-height: 1.25;
        font-weight: var(--peso-negrita);
        /* Permite romper palabras largas si hace falta (nombres compuestos sin espacios) */
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .ficha-sidebar__ubicacion {
        margin: 4px 0 0;
        font-size: var(--fs-dos);
        color: var(--color-seis-claro);
        line-height: 1.35;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .ficha-sidebar__ubicacion .icono { width: 12px; height: 12px; }

    /* 3 CTAs apilados verticalmente al final del sticky.
       Todos con mismo tamaño/forma, padding generoso para máxima clickeabilidad.
       Diferenciados solo por color de fondo. */
    .ficha-sidebar__ctas {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .ficha-cta {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--espacio-dos);
        padding: var(--espacio-tres) var(--espacio-cuatro);   /* más alto = más clickeable */
        border-radius: var(--radio-uno);
        text-decoration: none;
        font-family: inherit;
        font-size: var(--fs-dos);                              /* 14px en vez de 12px */
        font-weight: var(--peso-medio);
        min-width: 0;
        transition: background .15s ease, color .15s ease, transform .15s ease;
    }
    .ficha-cta:hover { transform: translateY(-1px); }
    .ficha-cta .icono { width: 18px; height: 18px; flex-shrink: 0; }

    /* Llamar — terracota (acción primaria, pero discreta) */
    .ficha-cta--llamar    { background: var(--color-uno); color: #fff; }
    .ficha-cta--llamar:hover { background: var(--color-dos); }

    /* WhatsApp — verde wa */
    .ficha-cta--whatsapp  { background: var(--color-nueve); color: #fff; }
    .ficha-cta--whatsapp:hover { filter: brightness(0.92); }

    /* Maps — arena neutro (terciaria) */
    .ficha-cta--maps {
        background: var(--color-cinco);
        color: var(--color-dos);
    }
    .ficha-cta--maps:hover {
        background: var(--color-uno);
        color: #fff;
    }

    /* Bloque de info compacta (dirección, tel, email, web, redes) — sin línea separadora */
    .ficha-info {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ficha-info__item {
        display: flex;
        align-items: center;
        gap: var(--espacio-dos);
        padding: 4px 0;
        font-size: var(--fs-dos);            /* 14px */
        color: var(--color-seis);
        text-decoration: none;
        line-height: 1.45;
        min-width: 0;
    }
    .ficha-info__item .icono {
        width: 18px; height: 18px;            /* +2px más visible */
        color: var(--color-uno);
        flex-shrink: 0;
    }
    .ficha-info__item--clickeable:hover {
        color: var(--color-uno);
    }
    .ficha-info__valor {
        flex: 1;
        min-width: 0;
        /* Sin truncate: que se muestre completo aunque haga 2+ líneas.
           overflow-wrap: anywhere permite romper URLs/emails largos sin espacios. */
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.35;
    }
    .ficha-info__valor--multilinea {
        /* Mantenida por compatibilidad; ya no aplica diferencia respecto al default */
    }
    .ficha-info__label {
        font-size: 0.7rem;
        color: var(--color-seis-claro);
        text-transform: uppercase;
        letter-spacing: .3px;
        margin-right: 4px;
        flex-shrink: 0;
    }

    /* Redes sociales — chips chiquitos */
    .ficha-info__redes {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 4px;
    }
    .ficha-info__red {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 32px; height: 32px;
        border-radius: var(--radio-uno);
        background: var(--color-cinco);
        transition: all .15s ease;
    }
    .ficha-info__red:hover {
        background: var(--color-uno);
        transform: translateY(-1px);
    }
    .ficha-info__red:hover .icono { color: #fff !important; }
    .ficha-info__red .icono { width: 16px; height: 16px; }

    /* Mobile: el sticky deja de ser sticky abajo, mantiene su lugar */
    @media (max-width: 1024px) {
        .ficha-sidebar__sticky { position: static; }
    }

    /* En anchos chicos, los 2 CTAs se apilan vertical */
    @media (max-width: 480px) {
        .ficha-sidebar__ctas { grid-template-columns: 1fr; }
    }

    /* ── Meta cards (Horarios / Provincia·Comunidad / Ciudades) ── */
    .ficha-meta-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--espacio-tres);
        margin-bottom: var(--espacio-cuatro);
    }
    .ficha-meta-card {
        background: var(--admin-superficie);
        border: 1px solid var(--admin-linea);
        border-radius: var(--radio-dos);
        box-shadow: var(--admin-sombra-suave);
        padding: var(--espacio-cuatro);
        display: flex;
        flex-direction: column;
        gap: var(--espacio-tres);
    }
    .ficha-meta-card__titulo {
        display: flex;
        align-items: center;
        gap: var(--espacio-dos);
        margin: 0;
    }
    .ficha-meta-card__titulo .icono {
        width: 16px; height: 16px;
        color: var(--color-uno);
        flex-shrink: 0;
    }
    .ficha-meta-card__titulo span {
        font-size: var(--fs-uno);
        font-weight: var(--peso-medio);
        color: var(--color-dos);
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    .ficha-meta-card__vacio {
        font-size: var(--fs-uno);
        color: var(--color-seis-claro);
        margin: 0;
        font-style: italic;
    }
    .ficha-meta-card__nota {
        margin: 0;
        font-size: 0.75rem;
        color: var(--color-seis-claro);
        font-style: italic;
        line-height: 1.5;
        padding-top: var(--espacio-dos);
        border-top: 1px dashed var(--color-cinco);
    }

    /* Chips geo diferenciados (CCAA / Provincia / Ciudad) */
    .ficha-chips {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px;
    }
    .ficha-chip-geo {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: var(--radio-full);
        font-size: 0.75rem;
        font-weight: var(--peso-medio);
        text-decoration: none;
        transition: all .15s ease;
        white-space: nowrap;
    }
    .ficha-chip-geo .icono { width: 12px; height: 12px; }

    /* Comunidad: más prominente */
    .ficha-chip-geo--ccaa {
        background: var(--color-uno);
        color: #fff;
    }
    .ficha-chip-geo--ccaa:hover { filter: brightness(1.08); transform: translateY(-1px); }

    /* Provincia: nivel intermedio */
    .ficha-chip-geo--prov {
        background: var(--color-uno-claro);
        color: var(--color-uno);
        border: 1px solid var(--color-uno);
    }
    .ficha-chip-geo--prov:hover {
        background: var(--color-uno);
        color: #fff;
    }

    /* Ciudad: nivel granular, neutro */
    .ficha-chip-geo--ciudad {
        background: var(--color-cinco);
        color: var(--color-seis);
        border: 1px solid var(--color-cinco);
    }
    .ficha-chip-geo--ciudad:hover {
        background: var(--color-uno-claro);
        color: var(--color-uno);
        border-color: var(--color-uno);
    }

    /* Horarios mejorados */
    .ficha-horarios {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .ficha-horarios__fila {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: var(--espacio-dos);
        font-size: var(--fs-uno);
    }
    .ficha-horarios__dia {
        font-weight: var(--peso-medio);
        color: var(--color-dos);
        white-space: nowrap;
    }
    .ficha-horarios__valor {
        color: var(--color-seis);
        text-align: right;
    }
    .ficha-horarios__valor--cerrado { opacity: .5; }
    .ficha-horarios__telefono {
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px solid var(--color-cinco);
    }

    /* Responsive de la meta-grid */
    @media (max-width: 1200px) {
        .ficha-meta-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 720px) {
        .ficha-meta-grid { grid-template-columns: 1fr; }
    }

    /* ── Servicios + Recuerdos: pills outline con icono semántico ── */
    .ficha-features {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: var(--espacio-dos);
    }
    .ficha-feature {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 14px 8px 10px;
        background: var(--admin-superficie);
        border: 1px solid var(--color-cinco);
        border-radius: 999px;
        font-size: 0.85rem;
        color: var(--color-dos);
        line-height: 1.2;
        transition: all .15s ease;
    }
    .ficha-feature:hover {
        background: var(--color-uno-claro);
        border-color: var(--color-uno);
    }
    .ficha-feature__ico {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px; height: 26px;
        border-radius: 50%;
        background: var(--color-uno-claro);
        color: var(--color-uno);
        flex-shrink: 0;
        transition: all .15s ease;
    }
    .ficha-feature:hover .ficha-feature__ico {
        background: var(--color-uno);
        color: #fff;
    }
    .ficha-feature__ico .icono { width: 14px; height: 14px; }

    /* ── Contador de caracteres del comentario (v2 — más sutil) ──
       Layout: label + mensaje motivacional en una fila de 2 columnas.
       Contador X/60 dentro del textarea (esquina inferior derecha). */
    .resena-label-fila {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: var(--espacio-tres);
        margin-bottom: 6px;
    }
    .resena-mensaje {
        font-size: var(--fs-uno);
        text-align: right;
        line-height: 1.4;
        min-height: 1em;            /* reserva espacio para evitar saltos al aparecer */
        transition: color .25s ease;
    }
    /* Estados del mensaje (sin fondo, solo color) */
    .resena-mensaje--silencio { color: transparent; }
    .resena-mensaje--rojo     { color: #b91c1c; }
    .resena-mensaje--verde    { color: #059669; }
    .resena-mensaje--exito    { color: #16a34a; font-weight: var(--peso-medio); }

    /* Wrapper del textarea (position relative para el contador absoluto) */
    .resena-textarea-wrap { position: relative; }
    .resena-contador {
        position: absolute;
        bottom: 8px;
        right: 10px;
        font-size: var(--fs-uno);
        color: var(--color-seis-claro);   /* neutro por default */
        background: rgba(255,255,255,0.85);
        padding: 2px 8px;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
        pointer-events: none;
        transition: color .25s ease, background .25s ease;
    }
    .resena-contador--ok {
        color: #059669;
        background: rgba(209,250,229,0.95);
    }
</style>


    <!-- ═══════════════════════════════════════════════════════════
         ENCABEZADO COMPACTO (sin background, patrón directorio-encabezado global)
         ═══════════════════════════════════════════════════════════ -->
    <div class="contenedor directorio-encabezado encabezado-geo">
        <?php
        $migas = [
            ['Inicio', BASE_URL . '/'],
            ['España', BASE_URL . '/espana/'],
        ];
        if ($comunidad_nombre && $comunidad_nombre !== $provincia_nombre) {
            $migas[] = [$comunidad_nombre, generarUrl('comunidad', $comunidad_slug)];
        }
        if ($provincia_nombre) {
            $migas[] = [$provincia_nombre, generarUrl('provincia', $provincia_slug)];
        }
        if ($ciudad_nombre) {
            $migas[] = [$ciudad_nombre, generarUrl('ciudad', $ciudad_slug, $provincia_slug)];
        }
        $migas[] = [$crematorio_nombre, null];
        include ROOT_PATH . '/includes/componentes/breadcrumb.php';
        ?>
        <?php if ($negocioCerrado): ?>
        <div role="alert" style="display:flex; align-items:center; gap:.6rem; background:#fdecea; border:1px solid #f5c2bd; color:#9b2c20; border-radius:var(--radio-dos); padding:var(--espacio-tres) var(--espacio-cuatro); margin-bottom:var(--espacio-tres); font-size:.95rem;">
            <i data-lucide="circle-slash" class="icono" style="width:20px; height:20px; flex-shrink:0;"></i>
            <span><strong>Cerrado permanentemente.</strong> Este negocio ya no está operativo. La información se mantiene a título informativo.</span>
        </div>
        <?php endif; ?>
        <div class="directorio-encabezado__fila">
            <h1 class="directorio-encabezado__titulo"><?php echo limpiar($crematorio_nombre); ?></h1>
            <?php if ($destacado || (!empty($crematorio['origen']) && $crematorio['origen'] === 'registro') || !empty($crematorio['verificado'])): ?>
            <div class="ficha-encabezado__badges">
                <?php if ($destacado): ?>
                <span class="ficha-encabezado__badge ficha-encabezado__badge--destacado">
                    <i data-lucide="award" class="icono"></i>
                    Destacado
                </span>
                <?php endif; ?>
                <?php if (!empty($crematorio['origen']) && $crematorio['origen'] === 'registro'): ?>
                <span class="ficha-encabezado__badge ficha-encabezado__badge--registrado">
                    <i data-lucide="user-check" class="icono"></i>
                    Registrado
                </span>
                <?php endif; ?>
                <?php if (!empty($crematorio['verificado'])): ?>
                <span class="ficha-encabezado__badge ficha-encabezado__badge--verificado">
                    <i data-lucide="badge-check" class="icono"></i>
                    Verificado
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <p class="directorio-encabezado__descripcion ficha-encabezado__meta">
            <span class="ficha-encabezado__metaitem">
                <i data-lucide="map-pin" class="icono"></i>
                <?php echo limpiar($ciudad_nombre); ?><?php echo $provincia_nombre ? ', ' . limpiar($provincia_nombre) : ''; ?>
            </span>
            <?php if ($valoracion > 0): ?>
            <span class="ficha-encabezado__metaitem">
                <i data-lucide="star" class="icono" style="color: var(--color-diez); fill: var(--color-diez);"></i>
                <?php echo number_format($valoracion, 1); ?> (<?php echo $num_resenas; ?> reseña<?php echo $num_resenas !== 1 ? 's' : ''; ?>)
            </span>
            <?php endif; ?>
        </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         CONTENIDO PRINCIPAL
         ═══════════════════════════════════════════════════════════ -->
    <div class="contenedor" style="padding: var(--espacio-tres) var(--espacio-cuatro) var(--espacio-cinco);">
        <div style="display: grid; grid-template-columns: 1fr 350px; gap: var(--espacio-cinco);">

            <!-- COLUMNA PRINCIPAL -->
            <main style="min-width:0;">

                <!-- Imagen Principal — altura reducida para que "Sobre el Crematorio"
                     entre en viewport al cargar (480px → 320px máximo) -->
                <?php if ($portada_url): ?>
                <section class="ficha__imagen" style="margin-bottom:var(--espacio-tres); text-align:center;">
                    <img
                        src="<?php echo htmlspecialchars($portada_url); ?>"
                        alt="<?php echo htmlspecialchars($portada_alt); ?>"
                        loading="lazy"
                        style="max-width:100%; max-height:360px; width:auto; height:auto; object-fit:contain; border-radius:var(--radio-dos);<?php echo !empty($lightbox_images) ? ' cursor:pointer;' : ''; ?>"
                        <?php if (!empty($lightbox_images)): ?>onclick="lightboxOpen(0)"<?php endif; ?>
                        onerror="this.style.display='none';"
                    >
                </section>
                <?php endif; ?>

                <!-- Galería principal (orden aleatorio) -->
                <?php if (!empty($galeria_display)): $totalGaleria = count($galeria_display); ?>
                <section class="ficha-galeria">
                    <div class="ficha-galeria__header">
                        <h3 class="ficha-eyebrow">
                            Galería de imágenes
                            <span class="ficha-eyebrow__contador"><?= $totalGaleria ?> <?= $totalGaleria === 1 ? 'foto' : 'fotos' ?></span>
                        </h3>
                        <?php if ($totalGaleria > 1): ?>
                        <button type="button" class="ficha-galeria__ver-todas" onclick="lightboxOpen(<?= (int)($galeria_display[0]['lb_idx'] ?? 1) ?>)">
                            <i data-lucide="maximize-2" class="icono"></i>
                            Ver todas
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="ficha-galeria__scroll" data-galeria-scroll>
                        <div class="ficha-galeria__track">
                            <?php foreach ($galeria_display as $idx => $img):
                                $img_url     = resolverUrlImagen($img['ruta']);
                                $img_alt     = htmlspecialchars($img['alt_text'] ?: $crematorio_nombre);
                                $lbIdx       = isset($img['lb_idx']) ? (int)$img['lb_idx'] : ($idx + 1);
                                $esDeCliente = !empty($img['desde_cliente']);
                            ?>
                            <div class="ficha-galeria__item">
                                <img src="<?php echo htmlspecialchars($img_url); ?>"
                                     alt="<?php echo $img_alt; ?>"
                                     loading="lazy"
                                     onclick="lightboxOpen(<?= $lbIdx ?>)"
                                     class="ficha-galeria__img"
                                     onerror="this.style.display='none'">
                                <?php if ($esDeCliente): ?>
                                <span class="ficha-galeria__badge-cliente" title="Foto enviada por un cliente">
                                    <i data-lucide="camera" class="icono" style="width:11px;height:11px;"></i> Cliente
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($totalGaleria >= 5): ?>
                            <button type="button" class="ficha-galeria__mas" onclick="lightboxOpen(<?= (int)($galeria_display[0]['lb_idx'] ?? 1) ?>)">
                                <span class="ficha-galeria__mas-num">+<?= $totalGaleria ?></span>
                                <span>Ver todas</span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($totalGaleria > 3): ?>
                        <div class="ficha-galeria__chevron" aria-hidden="true">
                            <i data-lucide="chevron-right" class="icono"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Descripción -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                    <h2 class="ficha-h2"><i data-lucide="info" class="icono"></i> Sobre el Crematorio de Mascotas</h2>
                    <div style="color: var(--color-seis); line-height: 1.8;">
                        <?php if ($descripcion): ?>
                        <?php echo formatearDescripcionPublica($descripcion); ?>
                        <?php else: ?>
                        <p style="margin: 0;">
                            <?php echo limpiar($crematorio_nombre); ?> ofrece servicios de cremación de mascotas con el máximo
                            respeto y profesionalismo. Entendemos que tu mascota es un miembro más de la familia, y por eso nos
                            comprometemos a brindar un servicio digno y compasivo en estos momentos difíciles.
                        </p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Meta cards: Horarios / Provincia·Comunidad / Ciudades -->
                <?php
                $ciudades_cob = trim($ciudades_cobertura);
                $zona_cob     = trim($crematorio['zona_cobertura'] ?? '');
                $mostrar_bloque = ($horario_texto || !empty($horarios_json_raw)) || $zona_cob || $ciudades_cob || $provincia_nombre || $comunidad_nombre;
                ?>
                <?php if ($mostrar_bloque): ?>
                <section class="ficha-meta-grid">

                    <!-- Horario de atención -->
                    <div class="ficha-meta-card">
                        <h3 class="ficha-meta-card__titulo">
                            <i data-lucide="clock" class="icono"></i>
                            <span>Horario</span>
                        </h3>
                        <?php
                        $hData     = $horarios_json_raw ? json_decode($horarios_json_raw, true) : null;
                        $hPresenc  = null;
                        $hTel      = null;
                        $hNota     = '';
                        if (is_array($hData)) {
                            if (isset($hData['presencial'])) {
                                $hPresenc = $hData['presencial'];
                                $hTel     = $hData['telefonica'] ?? null;
                                $hNota    = $hData['nota'] ?? '';
                            } else {
                                $hPresenc = $hData; // old flat format
                            }
                        }
                        $diasLvKeys = ['lunes','martes','miercoles','jueves','viernes'];
                        $diasLabels = ['lunes'=>'Lunes','martes'=>'Martes','miercoles'=>'Miércoles',
                                       'jueves'=>'Jueves','viernes'=>'Viernes','s'=>'Sábado','d'=>'Domingo'];
                        $fmtVal = function($val) {
                            if ($val === null)   return '<span style="opacity:.4;">Cerrado</span>';
                            if ($val === '')     return null; // no especificado — skip
                            if ($val === '24h') return '<strong>24h</strong>';
                            return htmlspecialchars(str_replace([' y ', '-'], [' / ', '–'], $val));
                        };
                        $hasIndividual = is_array($hPresenc) && !empty(array_intersect_key($hPresenc, array_flip($diasLvKeys)));
                        if (is_array($hPresenc) && !empty($hPresenc)):
                        ?>
                        <div style="display:flex; flex-direction:column; gap:.3rem;">
                            <?php if (!$hasIndividual && array_key_exists('lv', $hPresenc)):
                                $fmt = $fmtVal($hPresenc['lv']);
                                if ($fmt !== null): ?>
                            <div style="display:flex; justify-content:space-between; font-size:var(--fs-uno); gap:.5rem;">
                                <span style="font-weight:var(--peso-medio); color:var(--color-dos); white-space:nowrap;">Lun – Vie</span>
                                <span style="color:var(--color-seis); text-align:right;"><?php echo $fmt; ?></span>
                            </div>
                            <?php endif; else: ?>
                            <?php foreach ($diasLvKeys as $dk):
                                if (!array_key_exists($dk, $hPresenc)) continue;
                                $fmt = $fmtVal($hPresenc[$dk]);
                                if ($fmt === null) continue; ?>
                            <div style="display:flex; justify-content:space-between; font-size:var(--fs-uno); gap:.5rem;">
                                <span style="font-weight:var(--peso-medio); color:var(--color-dos); white-space:nowrap;"><?php echo $diasLabels[$dk]; ?></span>
                                <span style="color:var(--color-seis); text-align:right;"><?php echo $fmt; ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <?php foreach (['s'=>'Sábado','d'=>'Domingo'] as $dk=>$dlbl):
                                if (!array_key_exists($dk, $hPresenc)) continue;
                                $fmt = $fmtVal($hPresenc[$dk]);
                                if ($fmt === null) continue; ?>
                            <div style="display:flex; justify-content:space-between; font-size:var(--fs-uno); gap:.5rem;">
                                <span style="font-weight:var(--peso-medio); color:var(--color-dos); white-space:nowrap;"><?php echo $dlbl; ?></span>
                                <span style="color:var(--color-seis); text-align:right;"><?php echo $fmt; ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!empty($hTel['activa'])): ?>
                            <div style="margin-top:.4rem; padding-top:.4rem; border-top:1px solid var(--color-cinco); display:flex; justify-content:space-between; font-size:var(--fs-uno); gap:.5rem; align-items:flex-start;">
                                <span style="display:flex; align-items:center; gap:.3rem; white-space:nowrap; color:var(--color-dos); font-weight:var(--peso-medio);">
                                    <i data-lucide="phone" style="width:11px;height:11px;flex-shrink:0;"></i> Teléfono
                                </span>
                                <span style="color:var(--color-seis); text-align:right; line-height:1.5;">
                                    <?php
                                    $telModo = $hTel['modo'] ?? '24h';
                                    if ($telModo === '24h')           echo '<strong>24h</strong>';
                                    elseif ($telModo === 'presencial') echo 'Mismo horario';
                                    elseif ($telModo === 'horario' && !empty($hTel['horario'])) {
                                        $th    = $hTel['horario'];
                                        $fmtTH = function($v) {
                                            if ($v === null) return 'Cerrado';
                                            if ($v === '' || $v === null) return null;
                                            if ($v === '24h') return '24h';
                                            return str_replace([' y ','-'],['/','–'],$v);
                                        };
                                        $dLvKeys = ['lunes','martes','miercoles','jueves','viernes'];
                                        $hasInd  = !empty(array_intersect_key($th, array_flip($dLvKeys)));
                                        $pts = [];
                                        if (!$hasInd && array_key_exists('lv',$th)) {
                                            $f = $fmtTH($th['lv']); if ($f !== null) $pts[] = 'L-V: '.$f;
                                        } else {
                                            $pmap = ['lunes'=>'L','martes'=>'M','miercoles'=>'X','jueves'=>'J','viernes'=>'V'];
                                            foreach ($pmap as $dk=>$dp) { if (array_key_exists($dk,$th)) { $f=$fmtTH($th[$dk]); if($f!==null) $pts[]=$dp.': '.$f; } }
                                        }
                                        if (array_key_exists('s',$th)) { $f=$fmtTH($th['s']); if($f!==null) $pts[]='S: '.$f; }
                                        if (array_key_exists('d',$th)) { $f=$fmtTH($th['d']); if($f!==null) $pts[]='D: '.$f; }
                                        echo htmlspecialchars(implode(' · ', $pts));
                                    }
                                    /* El número de teléfono ya aparece en el sticky de contacto
                                       (sidebar derecha) — no lo repetimos aquí para ahorrar espacio. */
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($hNota)): ?>
                            <p style="margin:.4rem 0 0; font-size:calc(var(--fs-uno) - .05rem); color:var(--color-seis); opacity:.65; line-height:1.5; font-style:italic;"><?php echo htmlspecialchars($hNota); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php elseif ($horario_texto): ?>
                        <div style="display:flex; flex-direction:column; gap:.35rem;">
                            <?php foreach (explode('|', $horario_texto) as $tramo): ?>
                            <?php $partes = explode(':', $tramo, 2); ?>
                            <div style="display:flex; justify-content:space-between; font-size:var(--fs-uno); gap:.5rem;">
                                <span style="font-weight:var(--peso-medio); color:var(--color-dos); white-space:nowrap;"><?php echo limpiar(trim($partes[0])); ?></span>
                                <span style="color:var(--color-seis); text-align:right;"><?php echo limpiar(trim($partes[1] ?? '')); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p style="font-size:var(--fs-uno); color:var(--color-seis); opacity:.5; margin:0;">No disponible</p>
                        <?php endif; ?>
                    </div>

                    <!-- Provincia / Comunidad (chips diferenciados, clickeables a SEO geo) -->
                    <div class="ficha-meta-card">
                        <h3 class="ficha-meta-card__titulo">
                            <i data-lucide="map" class="icono"></i>
                            <span>Provincia / Comunidad</span>
                        </h3>
                        <?php if ($comunidad_nombre || $provincia_nombre): ?>
                        <div class="ficha-chips">
                            <?php if ($comunidad_nombre): ?>
                            <a href="<?php echo generarUrl('comunidad', $comunidad_slug); ?>" class="ficha-chip-geo ficha-chip-geo--ccaa" title="Ver crematorios en <?php echo limpiar($comunidad_nombre); ?>">
                                <i data-lucide="map" class="icono"></i>
                                <?php echo limpiar($comunidad_nombre); ?>
                            </a>
                            <?php endif; ?>
                            <?php if ($provincia_nombre): ?>
                            <a href="<?php echo generarUrl('provincia', $provincia_slug); ?>" class="ficha-chip-geo ficha-chip-geo--prov" title="Ver crematorios en <?php echo limpiar($provincia_nombre); ?>">
                                <i data-lucide="map" class="icono"></i>
                                <?php echo limpiar($provincia_nombre); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p class="ficha-meta-card__vacio">No disponible</p>
                        <?php endif; ?>
                    </div>

                    <!-- Ciudades de cobertura (chips clickeables, ordenadas alfabéticamente) -->
                    <div class="ficha-meta-card">
                        <h3 class="ficha-meta-card__titulo">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span>Ciudades de cobertura</span>
                        </h3>
                        <?php
                        $listaCiudades = [];
                        if ($ciudades_cob) {
                            foreach (preg_split('/[,;]+/', $ciudades_cob) as $ciudad) {
                                $ciudad = trim($ciudad);
                                if ($ciudad !== '') $listaCiudades[] = $ciudad;
                            }
                            // Orden alfabético (locale-aware para acentos)
                            usort($listaCiudades, function($a, $b) {
                                return strcoll(mb_strtolower($a), mb_strtolower($b));
                            });
                        }
                        ?>
                        <?php if (!empty($listaCiudades)): ?>
                        <div class="ficha-chips">
                            <?php foreach ($listaCiudades as $ciudad):
                                // Slug de la ciudad para link (heurística simple)
                                $slugCiu = strtolower(str_replace(['á','é','í','ó','ú','ñ',' ',','], ['a','e','i','o','u','n','-',''], $ciudad));
                            ?>
                            <a href="<?php echo generarUrl('ciudad', $slugCiu, $provincia_slug); ?>" class="ficha-chip-geo ficha-chip-geo--ciudad" title="Ver crematorios en <?php echo limpiar($ciudad); ?>">
                                <i data-lucide="map-pin" class="icono"></i>
                                <?php echo limpiar($ciudad); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="ficha-meta-card__vacio">No disponible</p>
                        <?php endif; ?>
                    </div>

                </section>
                <?php endif; ?>

                <!-- Ubicación / Mapa — solo si hay mapa REAL disponible.
                     Si no hay mapa, la sección se omite (la dirección ya vive en el sticky). -->
                <?php
                $hayMapaReal = ($tier >= '03' && $google_place_id)
                            || ($tier >= '02' && $lat_mapa)
                            || $usar_leaflet;
                ?>
                <?php if ($hayMapaReal): ?>
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro); overflow: visible;">
                    <h2 class="ficha-h2"><i data-lucide="map-pin" class="icono"></i> Ubicación</h2>
                    <div>
                        <?php if ($tier >= '03' && $google_place_id): ?>
                        <!-- Tier 03+: Google Maps con place_id (ficha real del negocio) -->
                        <iframe
                            src="https://www.google.com/maps/embed/v1/place?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&q=place_id:<?php echo urlencode($google_place_id); ?>"
                            width="100%" height="360"
                            style="border:0; border-radius:var(--radio-dos); margin-bottom:var(--espacio-tres);"
                            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                        <?php elseif ($tier >= '02' && $lat_mapa): ?>
                        <!-- Tier 02+: Google Maps embed con coordenadas -->
                        <iframe
                            src="https://maps.google.com/maps?q=<?php echo $lat_mapa; ?>,<?php echo $lng_mapa; ?>&z=15&output=embed"
                            width="100%" height="360"
                            style="border:0; border-radius:var(--radio-dos); margin-bottom:var(--espacio-tres);"
                            allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                        <?php elseif ($usar_leaflet): ?>
                        <!-- Tier 01: Leaflet + OpenStreetMap -->
                        <div id="mapa-crematorio" style="width:100%; height:360px; border-radius:var(--radio-dos); margin-bottom:var(--espacio-tres); position:relative;"></div>
                        <script>
                        (function() {
                            var lat = <?php echo $lat_mapa; ?>;
                            var lng = <?php echo $lng_mapa; ?>;
                            var map = L.map('mapa-crematorio', { scrollWheelZoom: false }).setView([lat, lng], 15);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                                maxZoom: 19
                            }).addTo(map);
                            var icono = L.divIcon({
                                className: '',
                                html: '<div style="background:#c0705a;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4);"></div>',
                                iconSize: [22, 22],
                                iconAnchor: [11, 11]
                            });
                            L.marker([lat, lng], { icon: icono })
                                .addTo(map)
                                .bindPopup('<strong><?php echo addslashes(htmlspecialchars($crematorio_nombre)); ?></strong><?php echo $direccion ? "<br>" . addslashes(htmlspecialchars($direccion)) : ""; ?>')
                                .openPopup();
                        })();
                        </script>
                        <?php endif; ?>
                        <!-- Dirección debajo del mapa (la del sticky es lateral; esta refuerza el contexto) -->
                        <p style="display: flex; align-items: center; gap: var(--espacio-dos); color: var(--color-seis); font-size: var(--fs-dos); padding: var(--espacio-tres); background: var(--color-cinco); border-radius: var(--radio-dos); margin: 0;">
                            <i data-lucide="map-pin" class="icono" style="width: 16px; height: 16px; color: var(--color-uno);"></i>
                            <?php echo limpiar($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre); ?>
                        </p>
                    </div>
                </section>
                <?php endif; ?>

                <?php
                // Mapa de iconos semánticos por servicio/extra (lucide)
                $iconosFeatures = [
                    'Atención 24 horas'      => 'clock',
                    'Recogida a domicilio'   => 'truck',
                    'Entrega a domicilio'    => 'package-2',
                    'Cremación individual'   => 'flame',
                    'Cremación colectiva'    => 'users',
                    'Sala velatoria'         => 'home',
                    'Urna personalizada'     => 'archive',
                    'Carta de condolencias'  => 'mail',
                    'Molde de huella'        => 'paw-print',
                    'Souvenires'             => 'gift',
                ];
                ?>

                <!-- Servicios -->
                <?php if (!empty($servicios_lista)): ?>
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                    <h2 class="ficha-h2">
                        <i data-lucide="check-circle" class="icono"></i>
                        Servicios
                    </h2>
                    <div class="ficha-features">
                        <?php foreach ($servicios_lista as $svc):
                            $ico = $iconosFeatures[$svc] ?? 'check';
                        ?>
                        <div class="ficha-feature">
                            <span class="ficha-feature__ico"><i data-lucide="<?php echo $ico; ?>" class="icono"></i></span>
                            <span><?php echo limpiar($svc); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Recuerdos y memoriales -->
                <?php if (!empty($extras_lista)): ?>
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                    <h2 class="ficha-h2">
                        <i data-lucide="heart" class="icono"></i>
                        Recuerdos y memoriales
                    </h2>
                    <div class="ficha-features">
                        <?php foreach ($extras_lista as $extra):
                            $ico = $iconosFeatures[$extra] ?? 'heart';
                        ?>
                        <div class="ficha-feature">
                            <span class="ficha-feature__ico"><i data-lucide="<?php echo $ico; ?>" class="icono"></i></span>
                            <span><?php echo limpiar($extra); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Precios -->
                <?php if (!empty($precios_lista)): ?>
                <style>
                .ficha-precios {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
                    gap: var(--espacio-tres);
                }
                .ficha-precio {
                    border: 1px solid var(--color-cinco);
                    border-radius: var(--radio-dos);
                    padding: var(--espacio-tres);
                    background: #fff;
                    display: flex;
                    flex-direction: column;
                    gap: .35rem;
                }
                .ficha-precio--destacado {
                    border-color: var(--color-uno);
                    background: var(--color-uno-claro, rgba(184,112,79,.08));
                }
                .ficha-precio__nombre {
                    font-weight: 600;
                    color: var(--color-dos);
                    font-size: .95rem;
                    line-height: 1.35;
                }
                .ficha-precio__monto {
                    font-size: 1.25rem;
                    font-weight: 700;
                    color: var(--color-uno);
                    letter-spacing: -.01em;
                }
                .ficha-precio__desc {
                    font-size: .82rem;
                    color: var(--color-seis);
                    line-height: 1.45;
                }
                .ficha-precios__nota {
                    margin: var(--espacio-tres) 0 0;
                    font-size: .78rem;
                    color: var(--color-seis);
                    opacity: .75;
                    font-style: italic;
                }
                </style>
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                    <h2 class="ficha-h2">
                        <i data-lucide="tag" class="icono"></i>
                        Precios
                    </h2>
                    <div class="ficha-precios">
                        <?php foreach ($precios_lista as $p):
                            $montoPrecio = formatearPrecioItem($p);
                        ?>
                        <div class="ficha-precio <?php echo !empty($p['destacado']) ? 'ficha-precio--destacado' : ''; ?>">
                            <div class="ficha-precio__nombre"><?php echo limpiar($p['nombre']); ?></div>
                            <?php if ($montoPrecio !== ''): ?>
                            <div class="ficha-precio__monto"><?php echo limpiar($montoPrecio); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['descripcion'])): ?>
                            <div class="ficha-precio__desc"><?php echo limpiar($p['descripcion']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="ficha-precios__nota">
                        Precios orientativos. Confirma el detalle directamente con el negocio.
                    </p>
                </section>
                <?php endif; ?>

                <!-- Galerías por categoría -->
                <?php foreach ($galeria_grupos_display as $grupo): ?>
                <section style="margin-bottom: var(--espacio-cuatro);">
                    <h3 class="ficha-eyebrow">
                        <?php echo htmlspecialchars($grupo['label']); ?>
                    </h3>
                    <div style="display:flex; gap:var(--espacio-dos); overflow-x:auto; padding-bottom:var(--espacio-dos); min-width:0;">
                        <?php foreach ($grupo['images'] as $img): ?>
                        <?php $img_url = resolverUrlImagen($img['ruta']); ?>
                        <?php $img_alt = htmlspecialchars($img['alt_text'] ?: $crematorio_nombre); ?>
                        <img src="<?php echo htmlspecialchars($img_url); ?>"
                             alt="<?php echo $img_alt; ?>"
                             loading="lazy"
                             onclick="lightboxOpen(<?= (int)$img['lb_idx'] ?>)"
                             style="height:130px; width:auto; flex-shrink:0; object-fit:cover; border-radius:var(--radio-dos); cursor:pointer; border:1px solid var(--color-cinco); transition:opacity var(--transicion);"
                             onmouseover="this.style.opacity='.85'"
                             onmouseout="this.style.opacity='1'"
                             onerror="this.style.display='none'; var w=this.parentElement; if(w&&Array.from(w.querySelectorAll('img')).every(function(i){return i.style.display==='none';}))w.closest('section').style.display='none';">
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>

                <!-- Galería "Fotos de clientes" — imágenes enviadas con reseñas (separada) -->
                <?php if (!empty($clientes_galeria_display)): ?>
                <section style="margin-bottom: var(--espacio-cuatro);">
                    <h3 class="ficha-eyebrow">
                        <i data-lucide="camera" class="icono" style="width:14px; height:14px;"></i>
                        Fotos enviadas por clientes
                        <span style="font-size:var(--fs-uno); color:var(--color-seis-claro); text-transform:none; letter-spacing:normal; opacity:.8; font-weight:normal;">
                            (junto con sus reseñas)
                        </span>
                    </h3>
                    <div style="display:flex; gap:var(--espacio-dos); overflow-x:auto; padding-bottom:var(--espacio-dos); min-width:0;">
                        <?php foreach ($clientes_galeria_display as $img):
                            $img_url = resolverUrlImagen($img['ruta']);
                            $img_alt = htmlspecialchars($img['alt_text'] ?: $crematorio_nombre);
                            $lbIdx   = (int) $img['lb_idx'];
                        ?>
                        <div style="position:relative; flex-shrink:0;">
                            <img src="<?php echo htmlspecialchars($img_url); ?>"
                                 alt="<?php echo $img_alt; ?>"
                                 loading="lazy"
                                 onclick="lightboxOpen(<?= $lbIdx ?>)"
                                 style="height:130px; width:auto; object-fit:cover; border-radius:var(--radio-dos); cursor:pointer; border:1px solid var(--color-cinco); transition:opacity var(--transicion); display:block;"
                                 onmouseover="this.style.opacity='.85'"
                                 onmouseout="this.style.opacity='1'"
                                 onerror="this.style.display='none'">
                            <span title="Foto enviada por un cliente"
                                  style="position:absolute; top:8px; left:8px; background:rgba(21,128,61,.9); color:#fff; font-size:.65rem; padding:.2rem .5rem; border-radius:999px; font-weight:700; pointer-events:none;">
                                📷 Cliente
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Reseñas (zona social: margin-top extra para separar visualmente) -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-top: var(--espacio-cinco); margin-bottom: var(--espacio-cuatro);">
                    <h2 class="ficha-h2">
                        <i data-lucide="star" class="icono"></i>
                        Reseñas
                        <?php
                        $total_resenas_aprobadas = contarResenasAprobadas($crematorio['id']);
                        if ($total_resenas_aprobadas > 0):
                        ?>
                        <span style="font-weight: normal; color: var(--color-seis); opacity: 0.7; font-size: var(--fs-uno);">
                            (<?php echo $total_resenas_aprobadas; ?>)
                        </span>
                        <?php endif; ?>
                    </h2>

                    <?php
                    $desglose = obtenerDesgloseResenas($crematorio['id']);
                    if ($desglose && $desglose['total'] > 0):
                        $d_media  = $desglose['media'];
                        $d_total  = $desglose['total'];
                        $d_counts = $desglose['counts'];
                    ?>
                    <!-- Desglose de valoraciones -->
                    <div style="display: flex; align-items: center; gap: var(--espacio-cinco); padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); margin-bottom: var(--espacio-cuatro); flex-wrap: wrap;">

                        <!-- Número grande + estrellas (lucide, fill consistente con el resto del sistema) -->
                        <div style="text-align: center; min-width: 90px; flex-shrink: 0;">
                            <div style="font-size: 3rem; font-weight: var(--peso-negro); color: var(--color-dos); line-height: 1;">
                                <?php echo number_format($d_media, 1); ?>
                            </div>
                            <div style="display: inline-flex; gap: 2px; color: var(--color-diez); margin-top: var(--espacio-uno);">
                                <?php
                                $estrellasLlenas = round($d_media);
                                for ($s = 1; $s <= 5; $s++):
                                    $llena = $s <= $estrellasLlenas;
                                ?>
                                <i data-lucide="star" style="width:18px;height:18px;<?php echo $llena ? 'fill:var(--color-diez);' : 'opacity:.35;'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div style="font-size: var(--fs-dos); color: var(--color-seis-claro); margin-top: var(--espacio-uno);">
                                <?php echo $d_total; ?> reseña<?php echo $d_total !== 1 ? 's' : ''; ?>
                            </div>
                        </div>

                        <!-- Barras por estrella -->
                        <div style="flex: 1; min-width: 180px; display: flex; flex-direction: column; gap: 6px;">
                            <?php for ($s = 5; $s >= 1; $s--): ?>
                            <?php
                            $cnt  = $d_counts[$s];
                            $pct  = $d_total > 0 ? round($cnt / $d_total * 100) : 0;
                            ?>
                            <div style="display: flex; align-items: center; gap: var(--espacio-dos);">
                                <span style="font-size: var(--fs-dos); color: var(--color-seis); white-space: nowrap; min-width: 14px; text-align: right;"><?php echo $s; ?></span>
                                <i data-lucide="star" style="width:13px;height:13px;color:var(--color-diez);fill:var(--color-diez);flex-shrink:0;"></i>
                                <div style="flex: 1; height: 8px; background: var(--color-cuatro); border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo $pct; ?>%; background: var(--color-diez); border-radius: 4px; transition: width 0.4s ease;"></div>
                                </div>
                                <span style="font-size: var(--fs-dos); color: var(--color-seis-claro); white-space: nowrap; min-width: 30px; text-align: right;"><?php echo $cnt; ?></span>
                            </div>
                            <?php endfor; ?>
                        </div>

                    </div>
                    <?php endif; ?>

                    <?php
                    $resenas_aprobadas = obtenerResenasAprobadas($crematorio['id'], 10);
                    $fuentes_presentes = array_unique(array_column($resenas_aprobadas, 'fuente'));
                    ?>

                    <!-- Filtros de fuente (solo si hay más de una fuente) -->
                    <?php if (count($fuentes_presentes) > 1): ?>
                    <div style="display:flex; flex-wrap:wrap; gap:var(--espacio-dos); margin-bottom:var(--espacio-cuatro);">
                        <button onclick="filtrarResenas('todas')" data-filtro="todas"
                            style="padding:var(--espacio-uno) var(--espacio-tres); border-radius:var(--radio-full); border:1px solid var(--color-uno); background:var(--color-uno); color:white; font-size:var(--fs-uno); cursor:pointer;" class="filtro-resena activo">
                            Todas
                        </button>
                        <?php if (in_array('propio', $fuentes_presentes)): ?>
                        <button onclick="filtrarResenas('propio')" data-filtro="propio"
                            style="padding:var(--espacio-uno) var(--espacio-tres); border-radius:var(--radio-full); border:1px solid var(--color-cinco); background:white; color:var(--color-seis); font-size:var(--fs-uno); cursor:pointer;" class="filtro-resena">
                            Verificadas
                        </button>
                        <?php endif; ?>
                        <?php if (in_array('google', $fuentes_presentes)): ?>
                        <button onclick="filtrarResenas('google')" data-filtro="google"
                            style="padding:var(--espacio-uno) var(--espacio-tres); border-radius:var(--radio-full); border:1px solid var(--color-cinco); background:white; color:var(--color-seis); font-size:var(--fs-uno); cursor:pointer; display:flex; align-items:center; gap:5px;" class="filtro-resena">
                            <svg width="12" height="12" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                            Google
                        </button>
                        <?php endif; ?>
                        <?php if (in_array('trustindex', $fuentes_presentes)): ?>
                        <button onclick="filtrarResenas('trustindex')" data-filtro="trustindex"
                            style="padding:var(--espacio-uno) var(--espacio-tres); border-radius:var(--radio-full); border:1px solid var(--color-cinco); background:white; color:var(--color-seis); font-size:var(--fs-uno); cursor:pointer;" class="filtro-resena">
                            Trustindex
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div id="lista-resenas" style="display: flex; flex-direction: column; gap: var(--espacio-cuatro);">

                        <?php if (empty($resenas_aprobadas)):
                        ?>
                        <!-- Sin reseñas aún -->
                        <article style="padding: var(--espacio-cinco); background: var(--color-cinco); border-radius: var(--radio-dos); text-align: center;">
                            <i data-lucide="message-square" style="width: 48px; height: 48px; color: var(--color-seis-claro); margin-bottom: var(--espacio-tres);"></i>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                Aún no hay reseñas para este crematorio.<br>
                                <strong style="color: var(--color-uno);">¡Sé el primero en compartir tu experiencia!</strong>
                            </p>
                        </article>

                        <?php else: ?>

                        <?php foreach ($resenas_aprobadas as $resena): ?>
                        <?php $fuente = $resena['fuente'] ?? 'propio'; ?>
                        <article data-fuente="<?php echo htmlspecialchars($fuente); ?>" style="padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--espacio-tres); gap: var(--espacio-dos);">
                                <!-- Nombre + estrellas -->
                                <div>
                                    <div style="font-weight: var(--peso-negrita); color: var(--color-dos);">
                                        <?php echo limpiar($resena['nombre']); ?>
                                    </div>
                                    <div class="tarjeta__valoracion" style="margin-top: var(--espacio-uno);">
                                        <?php echo generarEstrellas($resena['calificacion']); ?>
                                    </div>
                                </div>
                                <!-- Fecha + fuente -->
                                <div style="display: flex; flex-direction: column; align-items: flex-end; gap: var(--espacio-uno); flex-shrink: 0;">
                                    <span style="font-size: var(--fs-dos); color: var(--color-seis-claro);">
                                        <?php echo date('d/m/Y', strtotime($resena['created_at'])); ?>
                                    </span>
                                    <?php if ($fuente === 'google'): ?>
                                    <span title="Reseña de Google" style="display:flex;align-items:center;gap:4px;font-size:var(--fs-dos);color:var(--color-seis-claro);">
                                        <svg width="14" height="14" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                                        </svg>
                                        Google
                                    </span>
                                    <?php elseif ($fuente === 'trustindex'): ?>
                                    <span title="Reseña de Trustindex" style="display:flex;align-items:center;gap:4px;font-size:var(--fs-dos);color:var(--color-seis-claro);">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="12" cy="12" r="11" fill="#F4A623"/>
                                            <path d="M12 6l1.5 4.5H18l-3.75 2.75 1.5 4.5L12 15l-3.75 2.75 1.5-4.5L6 10.5h4.5L12 6z" fill="white"/>
                                        </svg>
                                        Trustindex
                                    </span>
                                    <?php else: ?>
                                    <span title="Reseña verificada" style="display:flex;align-items:center;gap:4px;font-size:var(--fs-dos);color:var(--color-seis-claro);">
                                        <i data-lucide="badge-check" style="width:14px;height:14px;color:var(--color-uno);"></i>
                                        Verificada
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p style="color: var(--color-seis); line-height: 1.6; margin: 0;">
                                <?php echo nl2br(limpiar($resena['comentario'])); ?>
                            </p>

                            <?php
                            // Mini-galería: imágenes que el cliente envió con esta reseña
                            $imgsResena = $imagenes_por_resena[$resena['id']] ?? [];
                            if (!empty($imgsResena)):
                            ?>
                            <div style="margin-top: var(--espacio-tres); display: flex; gap: var(--espacio-dos); flex-wrap: wrap;">
                                <?php foreach ($imgsResena as $img):
                                    $img_url = resolverUrlImagen($img['ruta']);
                                    $img_alt = htmlspecialchars($img['alt_text'] ?: $crematorio_nombre);
                                    $lbIdx   = (int) $img['lb_idx'];
                                ?>
                                <img src="<?php echo htmlspecialchars($img_url); ?>"
                                     alt="<?php echo $img_alt; ?>"
                                     loading="lazy"
                                     onclick="lightboxOpen(<?= $lbIdx ?>)"
                                     style="height:84px; width:84px; object-fit:cover; border-radius:var(--radio-uno); cursor:pointer; border:1px solid var(--color-cinco); transition:transform .15s, opacity .15s;"
                                     onmouseover="this.style.transform='scale(1.05)'; this.style.opacity='.9';"
                                     onmouseout="this.style.transform='scale(1)'; this.style.opacity='1';"
                                     onerror="this.style.display='none'">
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </article>
                        <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                    <script>
                    function filtrarResenas(fuente) {
                        document.querySelectorAll('#lista-resenas article').forEach(function(art) {
                            art.style.display = (fuente === 'todas' || art.dataset.fuente === fuente) ? '' : 'none';
                        });
                        document.querySelectorAll('.filtro-resena').forEach(function(btn) {
                            var activo = btn.dataset.filtro === fuente;
                            btn.style.background  = activo ? 'var(--color-uno)' : 'white';
                            btn.style.color       = activo ? 'white' : 'var(--color-seis)';
                            btn.style.borderColor = activo ? 'var(--color-uno)' : 'var(--color-cinco)';
                        });
                    }
                    </script>

                </section>

                <!-- Formulario de reseña -->
                <section class="tarjeta simple" style="padding: var(--espacio-cuatro); margin-bottom: var(--espacio-cuatro);">
                    <h2 class="ficha-h2">
                        <i data-lucide="pen-line" class="icono"></i>
                        Dejar una Reseña
                    </h2>

                    <!-- Mensaje de alerta -->
                    <div id="alerta-resena" class="alerta" style="display: none; margin-bottom: var(--espacio-cuatro);"></div>

                    <form id="form-resena" onsubmit="enviarResena(event)" enctype="multipart/form-data" novalidate>

                        <!-- Honeypot: campo invisible que solo los bots completan -->
                        <div aria-hidden="true" style="position:absolute; left:-9999px; top:-9999px; height:0; width:0; overflow:hidden;">
                            <label for="website-url-extra">No completar este campo</label>
                            <input type="text" id="website-url-extra" name="website_url" tabindex="-1" autocomplete="off" value="">
                        </div>

                        <!-- Time-trap: timestamp del render para detectar envíos demasiado rápidos -->
                        <input type="hidden" name="form_render_ts" value="<?php echo time(); ?>">

                        <!-- Calificación con estrellas -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta">Calificación *</label>
                            <div id="calificacion-estrellas" class="estrellas" style="cursor: pointer;" onmouseleave="restaurarEstrellas()">
                                <span class="estrella llena" onmouseenter="previewEstrellas(1)" onclick="seleccionarEstrellas(1)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(2)" onclick="seleccionarEstrellas(2)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(3)" onclick="seleccionarEstrellas(3)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(4)" onclick="seleccionarEstrellas(4)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                                <span class="estrella llena" onmouseenter="previewEstrellas(5)" onclick="seleccionarEstrellas(5)"><i data-lucide="star" style="width: 24px; height: 24px;"></i></span>
                            </div>
                            <input type="hidden" id="calificacion" name="calificacion" value="5">
                        </div>

                        <!-- Nombre y Email -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--espacio-cuatro);">
                            <div class="formulario-grupo">
                                <label class="formulario-etiqueta" for="nombre-resena">Nombre *</label>
                                <input
                                    type="text"
                                    id="nombre-resena"
                                    name="nombre"
                                    class="campo"
                                    required
                                    placeholder="Tu nombre"
                                >
                            </div>

                            <div class="formulario-grupo">
                                <label class="formulario-etiqueta" for="email-resena">Email *</label>
                                <input
                                    type="email"
                                    id="email-resena"
                                    name="email"
                                    class="campo"
                                    required
                                    placeholder="tu@email.com"
                                >
                            </div>
                        </div>

                        <!-- Comentario con contador interno + mensaje motivacional sutil -->
                        <div class="formulario-grupo">
                            <div class="resena-label-fila">
                                <label class="formulario-etiqueta" for="comentario-resena">Comentario *</label>
                                <span class="resena-mensaje resena-mensaje--silencio" id="resena-mensaje" aria-live="polite"></span>
                            </div>
                            <div class="resena-textarea-wrap">
                                <textarea
                                    id="comentario-resena"
                                    name="comentario"
                                    class="area-texto"
                                    required
                                    placeholder="Cuéntanos sobre tu experiencia..."
                                    rows="5"
                                    minlength="60"
                                ></textarea>
                                <span class="resena-contador" id="resena-contador"><span id="resena-contador-num">0</span> / 60</span>
                            </div>
                        </div>

                        <!-- Imágenes (opcional) -->
                        <div class="formulario-grupo">
                            <label class="formulario-etiqueta" for="imagenes-resena">
                                Fotos <span style="font-weight:normal; color:var(--color-seis-claro);">(opcional · hasta 5 · max 5MB c/u · jpg, png, webp)</span>
                            </label>
                            <input
                                type="file"
                                id="imagenes-resena"
                                name="imagenes_resena[]"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                multiple
                                style="display:none;"
                                onchange="previsualizarImagenesResena(this)"
                            >
                            <div class="upload-zona-resena" id="zona-imagenes-resena" onclick="document.getElementById('imagenes-resena').click()"
                                 style="border:2px dashed var(--color-cinco); border-radius:var(--radio-uno); padding:var(--espacio-cuatro); text-align:center; cursor:pointer; background:var(--color-ocho); transition:border-color .15s;">
                                <div id="placeholder-imagenes-resena" style="color:var(--color-seis-claro); font-size:var(--fs-dos);">
                                    <i data-lucide="image-plus" class="icono" style="width:28px; height:28px; display:inline-block; vertical-align:middle; margin-right:.4rem;"></i>
                                    Hacé clic para agregar fotos
                                </div>
                                <div id="preview-imagenes-resena" style="display:none; gap:var(--espacio-dos); flex-wrap:wrap; justify-content:center;"></div>
                            </div>
                        </div>

                        <!-- Botón -->
                        <button type="submit" class="boton uno">
                            Enviar Reseña
                        </button>

                        <p style="font-size: var(--fs-uno); color: var(--color-seis-claro); margin-top: var(--espacio-tres);">
                            Tu reseña será revisada antes de ser publicada.
                        </p>
                    </form>
                </section>

            </main>

            <!-- SIDEBAR -->
            <aside>

                <?php
                $telPrincipal = $fTelefonosVis[0] ?? null;
                // URL Google Maps (precomputado para botón terciario).
                // Prioridad: place_id (ficha real del negocio en Google) → coords → dirección.
                if ($google_place_id !== '') {
                    $gmaps_url = 'https://www.google.com/maps/place/?q=place_id:' . $google_place_id;
                } elseif ($usar_leaflet) {
                    $gmaps_url = 'https://www.google.com/maps/search/?api=1&query=' . $lat_mapa . ',' . $lng_mapa;
                } elseif ($direccion || $ciudad_nombre) {
                    $gmaps_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre . ', España');
                } else {
                    $gmaps_url = null;
                }
                ?>
                <?php
                // Solo el PRIMER tel/email (UNO por tipo en el sticky público)
                $emailPrincipal = $fEmailsVis[0] ?? null;
                $tieneInfo = $direccion || $ciudad_nombre || $telPrincipal || $emailPrincipal || $web || !empty($fRedesVis);
                $hayCta    = $telPrincipal || $whatsapp || $gmaps_url;

                // Atributos compartidos para data-lead-capture (modal interceptor)
                $logoLeadCapture = $logo_url ? htmlspecialchars($logo_url, ENT_QUOTES) : '';
                $cremIdAttr      = (int)$crematorio['id'];
                $cremNombreAttr  = htmlspecialchars($crematorio_nombre, ENT_QUOTES);
                ?>
                <div class="ficha-sidebar__sticky">
                    <!-- Header: logo izq + nombre + ciudad -->
                    <div class="ficha-sidebar__header">
                        <?php if ($logo_url): ?>
                        <div class="ficha-sidebar__logo">
                            <img src="<?php echo htmlspecialchars($logo_url); ?>"
                                 alt="Logo de <?php echo htmlspecialchars($crematorio_nombre); ?>"
                                 loading="lazy"
                                 onerror="this.parentElement.style.display='none'">
                        </div>
                        <?php endif; ?>
                        <div class="ficha-sidebar__nombre-wrap">
                            <h3 class="ficha-sidebar__nombre"><?php echo limpiar($crematorio_nombre); ?></h3>
                            <?php if ($ciudad_nombre): ?>
                            <p class="ficha-sidebar__ubicacion">
                                <i data-lucide="map-pin" class="icono"></i>
                                <?php echo limpiar($ciudad_nombre); ?><?php echo $provincia_nombre ? ', ' . limpiar($provincia_nombre) : ''; ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info compacta (UNO de cada tipo: dirección, tel, email, web, redes) -->
                    <?php if ($tieneInfo): ?>
                    <div class="ficha-info">
                        <?php if ($direccion || $ciudad_nombre): ?>
                        <?php if ($gmaps_url): ?>
                        <a href="<?php echo htmlspecialchars($gmaps_url); ?>" target="_blank" rel="noopener" class="ficha-info__item ficha-info__item--clickeable">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span class="ficha-info__valor ficha-info__valor--multilinea"><?php echo limpiar($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre); ?></span>
                        </a>
                        <?php else: ?>
                        <div class="ficha-info__item">
                            <i data-lucide="map-pin" class="icono"></i>
                            <span class="ficha-info__valor ficha-info__valor--multilinea"><?php echo limpiar($direccion ?: $ciudad_nombre . ', ' . $provincia_nombre); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($telPrincipal): ?>
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $telPrincipal['numero']); ?>" class="ficha-info__item ficha-info__item--clickeable">
                            <i data-lucide="phone" class="icono"></i>
                            <span class="ficha-info__valor"><?php echo limpiar($telPrincipal['numero']); ?></span>
                        </a>
                        <?php endif; ?>

                        <?php if ($emailPrincipal): ?>
                        <a href="mailto:<?php echo limpiar($emailPrincipal['email']); ?>" class="ficha-info__item ficha-info__item--clickeable">
                            <i data-lucide="mail" class="icono"></i>
                            <span class="ficha-info__valor"><?php echo limpiar($emailPrincipal['email']); ?></span>
                        </a>
                        <?php endif; ?>

                        <?php if ($web): $webUtm = urlConUtm($web, ['cmas_negocio_id' => $cremIdAttr]); ?>
                        <a href="<?php echo htmlspecialchars($webUtm); ?>"
                           target="_blank" rel="noopener"
                           class="ficha-info__item ficha-info__item--clickeable"
                           data-lead-capture="web"
                           data-destino="<?php echo htmlspecialchars($webUtm); ?>"
                           data-crematorio-id="<?php echo $cremIdAttr; ?>"
                           data-crematorio-nombre="<?php echo $cremNombreAttr; ?>"
                           data-crematorio-logo="<?php echo $logoLeadCapture; ?>">
                            <i data-lucide="globe" class="icono"></i>
                            <span class="ficha-info__valor"><?php echo limpiar(preg_replace('#^https?://(www\.)?#', '', $web)); ?></span>
                        </a>
                        <?php endif; ?>

                        <?php if (!empty($fRedesVis)): ?>
                        <div class="ficha-info__redes">
                            <?php foreach ($fRedesVis as $fR):
                                $rIco = $redesIconos[$fR['red']] ?? 'link';
                                $rCol = $redesColores[$fR['red']] ?? 'var(--color-seis)';
                                $rUrl = urlConUtm($fR['url'], ['cmas_negocio_id' => $cremIdAttr]);
                            ?>
                            <a href="<?php echo htmlspecialchars($rUrl); ?>" target="_blank" rel="noopener" class="ficha-info__red" title="<?php echo limpiar($fR['label']); ?>">
                                <i data-lucide="<?php echo $rIco; ?>" class="icono" style="color: <?php echo $rCol; ?>;"></i>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- 3 CTAs apilados al final, copy orientada a acción concreta.
                         Todos llevan data-lead-capture → intercepta el widget interno
                         (modal de lead capture + webhook + tracking). -->
                    <?php if ($hayCta): ?>
                    <div class="ficha-sidebar__ctas">
                        <?php if ($telPrincipal): $telLimpio = preg_replace('/[^0-9+]/', '', $telPrincipal['numero']); ?>
                        <a href="tel:<?php echo $telLimpio; ?>"
                           class="ficha-cta ficha-cta--llamar"
                           data-lead-capture="tel"
                           data-destino="tel:<?php echo $telLimpio; ?>"
                           data-phone-agent="<?php echo $telLimpio; ?>"
                           data-crematorio-id="<?php echo $cremIdAttr; ?>"
                           data-crematorio-nombre="<?php echo $cremNombreAttr; ?>"
                           data-crematorio-logo="<?php echo $logoLeadCapture; ?>">
                            <i data-lucide="phone" class="icono"></i>
                            Llamar por teléfono
                        </a>
                        <?php endif; ?>

                        <?php
                        if ($whatsapp):
                            $waLimpio = preg_replace('/[^0-9]/', '', $whatsapp);
                            // Mensaje inicial del wa.me — SOLO si el usuario evita el
                            // modal. Si llena el form, procesar-lead-b2c.php lo reemplaza
                            // por uno rico. Incluimos contexto de la ficha.
                            $waTextoInicial = 'Hola, vi su ficha de ' . $crematorio_nombre
                                . ' en Crematoriosdemascotas.com y me gustaría obtener información sobre sus servicios.';
                            $waUrlInicial = 'https://wa.me/' . $waLimpio . '?text=' . urlencode($waTextoInicial);
                        ?>
                        <a href="<?php echo htmlspecialchars($waUrlInicial); ?>"
                           class="ficha-cta ficha-cta--whatsapp"
                           target="_blank"
                           rel="noopener"
                           data-lead-capture="wa"
                           data-destino="<?php echo htmlspecialchars($waUrlInicial); ?>"
                           data-phone-agent="<?php echo $waLimpio; ?>"
                           data-crematorio-id="<?php echo $cremIdAttr; ?>"
                           data-crematorio-nombre="<?php echo $cremNombreAttr; ?>"
                           data-crematorio-logo="<?php echo $logoLeadCapture; ?>">
                            <!-- Logo oficial de WhatsApp (SVG inline — lucide no tiene brand-icons) -->
                            <svg class="icono" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413"/>
                            </svg>
                            Enviar mensaje por WhatsApp
                        </a>
                        <?php endif; ?>

                        <?php if ($gmaps_url): ?>
                        <a href="<?php echo htmlspecialchars($gmaps_url); ?>"
                           target="_blank"
                           rel="noopener"
                           class="ficha-cta ficha-cta--maps"
                           data-lead-capture="maps"
                           data-destino="<?php echo htmlspecialchars($gmaps_url); ?>"
                           data-crematorio-id="<?php echo $cremIdAttr; ?>"
                           data-crematorio-nombre="<?php echo $cremNombreAttr; ?>"
                           data-crematorio-logo="<?php echo $logoLeadCapture; ?>">
                            <i data-lucide="navigation" class="icono"></i>
                            Ver en el mapa
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

            </aside>
        </div>
    </div>

    <!-- Script específico de la página -->
    <script>
        // Mostrar mensaje de alerta
        function mostrarAlertaResena(mensaje, tipo) {
            // Usamos toasts globales del sitio (Notyf) en vez del alerta inline.
            // tipo: 'error' | 'success' | 'info'
            if (window.toast) {
                if (tipo === 'success' && window.toast.ok)        { window.toast.ok(mensaje); return; }
                if (tipo === 'error'   && window.toast.error)     { window.toast.error(mensaje); return; }
                if (window.toast.info) { window.toast.info(mensaje); return; }
            }
            // Fallback: el div inline solo si no hay toast disponible
            const alerta = document.getElementById('alerta-resena');
            if (alerta) {
                alerta.textContent = mensaje;
                alerta.className = 'alerta ' + tipo;
                alerta.style.display = 'flex';
                alerta.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Actualizar visualización de estrellas
        function actualizarEstrellas(valor) {
            const estrellas = document.querySelectorAll('#calificacion-estrellas .estrella');
            estrellas.forEach((span, index) => {
                if (index < valor) {
                    span.classList.add('llena');
                    span.classList.remove('vacia');
                } else {
                    span.classList.add('vacia');
                    span.classList.remove('llena');
                }
            });
        }

        // Preview en hover
        function previewEstrellas(valor) {
            actualizarEstrellas(valor);
        }

        // Restaurar al valor seleccionado
        function restaurarEstrellas() {
            const valorActual = parseInt(document.getElementById('calificacion').value);
            actualizarEstrellas(valorActual);
        }

        // Seleccionar estrellas (clic)
        function seleccionarEstrellas(valor) {
            document.getElementById('calificacion').value = valor;
            actualizarEstrellas(valor);
        }

        // ── Contador del comentario (v2 — sutil, 4 estados) ──
        // 0-10 chars  : silencio (no aparece mensaje, no presiona al usuario).
        // 11-59 chars : mensaje rojo motivando a escribir más.
        // 60-119 chars: contador y mensaje verde calmo, valida.
        // >=120 chars : mensaje verde brillante (éxito).
        (function() {
            const ta  = document.getElementById('comentario-resena');
            const num = document.getElementById('resena-contador-num');
            const numBox = document.getElementById('resena-contador');
            const msg = document.getElementById('resena-mensaje');
            if (!ta || !num || !numBox || !msg) return;

            const MIN = 60;
            const OK  = 120;

            function actualizar() {
                const n = ta.value.trim().length;
                num.textContent = n;

                // Contador: verde si supera el mínimo, neutro si no
                numBox.classList.toggle('resena-contador--ok', n >= MIN);

                // Reset clases mensaje
                msg.classList.remove('resena-mensaje--silencio', 'resena-mensaje--rojo',
                                     'resena-mensaje--verde', 'resena-mensaje--exito');

                if (n < 4) {
                    msg.textContent = '';
                    msg.classList.add('resena-mensaje--silencio');
                } else if (n < MIN) {
                    msg.textContent = 'Cuéntanos un poco más, ' + (MIN - n) + ' caracteres para llegar al mínimo.';
                    msg.classList.add('resena-mensaje--rojo');
                } else if (n < OK) {
                    msg.textContent = '¡Vas bien! Sigue contando para ayudar a otros usuarios.';
                    msg.classList.add('resena-mensaje--verde');
                } else {
                    // Ícono check-circle inline (lucide) — hereda el color verde via currentColor
                    msg.innerHTML = '<svg style="width:15px;height:15px;display:inline-block;vertical-align:-3px;margin-right:5px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>¡Excelente reseña, gracias por el detalle!';
                    msg.classList.add('resena-mensaje--exito');
                }
            }

            ta.addEventListener('input', actualizar);
            actualizar();
        })();

        // Enviar reseña
        function enviarResena(event) {
            event.preventDefault();

            const nombre = document.getElementById('nombre-resena').value.trim();
            const email = document.getElementById('email-resena').value.trim();
            const comentario = document.getElementById('comentario-resena').value.trim();
            const calificacion = document.getElementById('calificacion').value;

            if (!nombre || !email || !comentario) {
                mostrarAlertaResena('Por favor completa todos los campos.', 'error');
                return;
            }

            // Comentario debe tener al menos 60 caracteres (mismo umbral que el contador UX)
            if (comentario.length < 60) {
                mostrarAlertaResena('El comentario debe tener al menos 60 caracteres. Te faltan ' + (60 - comentario.length) + '.', 'error');
                return;
            }

            // Validar email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                mostrarAlertaResena('Por favor ingresa un email válido.', 'error');
                return;
            }

            // Validar imágenes adjuntas (opcionales, max 5, max 5MB c/u)
            const inputImgs = document.getElementById('imagenes-resena');
            const files = inputImgs && inputImgs.files ? Array.from(inputImgs.files) : [];
            if (files.length > 5) {
                mostrarAlertaResena('Puedes adjuntar hasta 5 fotos.', 'error');
                return;
            }
            const TIPOS_OK = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            for (const f of files) {
                if (!TIPOS_OK.includes(f.type)) {
                    mostrarAlertaResena('Solo se permiten imágenes JPG, PNG, WebP o GIF.', 'error');
                    return;
                }
                if (f.size > 5 * 1024 * 1024) {
                    mostrarAlertaResena(`La imagen "${f.name}" supera 5MB.`, 'error');
                    return;
                }
            }

            // Enviar vía AJAX
            const boton = document.querySelector('#form-resena button[type="submit"]');
            boton.disabled = true;
            boton.textContent = files.length > 0 ? 'Enviando (subiendo fotos)...' : 'Enviando...';

            const formData = new FormData();
            formData.append('tipo', 'resena');
            formData.append('nombre', nombre);
            formData.append('email', email);
            formData.append('comentario', comentario);
            formData.append('calificacion', calificacion);
            formData.append('crematorio', '<?php echo addslashes($crematorio_nombre); ?>');
            formData.append('crematorio_slug', '<?php echo addslashes($crematorio_slug); ?>');
            formData.append('page_url', window.location.href);

            // Anti-spam: honeypot + time-trap (los tomamos directo del form)
            const formEl = document.getElementById('form-resena');
            const hpot   = formEl.querySelector('input[name="website_url"]');
            const ts     = formEl.querySelector('input[name="form_render_ts"]');
            if (hpot) formData.append('website_url', hpot.value);
            if (ts)   formData.append('form_render_ts', ts.value);

            files.forEach(f => formData.append('imagenes_resena[]', f, f.name));

            fetch('<?php echo BASE_URL; ?>/procesar-formulario.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    dataLayer.push({
                        'event': 'form_submit_success',
                        'form_id': data.form_id,
                        'form_name': data.form_name
                    });
                    const nImgs = data.imagenes_guardadas || 0;
                    const msj = nImgs > 0
                        ? `¡Gracias por tu reseña! Subimos ${nImgs} foto${nImgs === 1 ? '' : 's'}. Será publicada después de ser revisada.`
                        : '¡Gracias por tu reseña! Será publicada después de ser revisada.';
                    mostrarAlertaResena(msj, 'exito');
                    document.getElementById('form-resena').reset();
                    seleccionarEstrellas(5);
                    // Reset preview de imágenes
                    const prev = document.getElementById('preview-imagenes-resena');
                    const ph = document.getElementById('placeholder-imagenes-resena');
                    if (prev) { prev.innerHTML = ''; prev.style.display = 'none'; }
                    if (ph) ph.style.display = '';
                } else {
                    mostrarAlertaResena(data.mensaje || 'Error al enviar. Inténtalo de nuevo.', 'error');
                }
                boton.disabled = false;
                boton.textContent = 'Enviar Reseña';
            })
            .catch(() => {
                mostrarAlertaResena('Error de conexión. Inténtalo de nuevo.', 'error');
                boton.disabled = false;
                boton.textContent = 'Enviar Reseña';
            });
        }

        // Preview de imágenes seleccionadas en el form de reseña
        function previsualizarImagenesResena(input) {
            const preview = document.getElementById('preview-imagenes-resena');
            const placeholder = document.getElementById('placeholder-imagenes-resena');
            if (!preview || !placeholder) return;

            if (!input.files || input.files.length === 0) {
                preview.innerHTML = '';
                preview.style.display = 'none';
                placeholder.style.display = '';
                return;
            }

            if (input.files.length > 5) {
                mostrarAlertaResena('Puedes adjuntar hasta 5 fotos.', 'error');
                input.value = '';
                preview.innerHTML = '';
                preview.style.display = 'none';
                placeholder.style.display = '';
                return;
            }

            preview.innerHTML = '';
            Array.from(input.files).forEach((file, i) => {
                if (file.size > 5 * 1024 * 1024) return;
                const reader = new FileReader();
                reader.onload = e => {
                    const wrap = document.createElement('div');
                    wrap.style.cssText = 'position:relative; width:88px; height:88px; border-radius:6px; overflow:hidden; border:1px solid var(--color-cinco);';
                    wrap.innerHTML = `
                        <img src="${e.target.result}" alt="" style="width:100%; height:100%; object-fit:cover; display:block;">
                        <span style="position:absolute; bottom:2px; right:4px; background:rgba(0,0,0,.6); color:#fff; font-size:.65rem; padding:.05rem .35rem; border-radius:4px;">${i + 1}</span>
                    `;
                    preview.appendChild(wrap);
                };
                reader.readAsDataURL(file);
            });
            preview.style.display = 'flex';
            placeholder.style.display = 'none';
        }
    </script>

    <!-- Media query para responsive -->
    <style>
        @media (max-width: 1024px) {
            .contenedor > div[style*="grid-template-columns: 1fr 350px"] {
                grid-template-columns: 1fr !important;
            }
        }

        @media (max-width: 768px) {
            div[style*="grid-template-columns: repeat(2, 1fr)"] {
                grid-template-columns: 1fr !important;
            }

            div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            section[style*="grid-template-columns:repeat(3,1fr)"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>

<?php if (!empty($lightbox_images)): ?>
<!-- LIGHTBOX — con panel lateral para reseña del cliente cuando aplica -->
<style>
    #lb-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.92); z-index: 9000;
        align-items: center; justify-content: center; flex-direction: column;
    }
    #lb-stage {
        display: flex; align-items: center; justify-content: center;
        gap: 1.5rem; max-width: 95vw; max-height: 82vh;
    }
    #lb-img-wrap {
        position: relative; display: flex; align-items: center; justify-content: center;
        max-width: 70vw; max-height: 82vh;
    }
    #lb-img {
        max-width: 70vw; max-height: 82vh;
        object-fit: contain; border-radius: 6px;
        box-shadow: 0 8px 32px rgba(0,0,0,.5);
    }
    #lb-resena-panel {
        display: none;
        max-width: 360px; min-width: 280px;
        max-height: 82vh; overflow-y: auto;
        background: rgba(255,255,255,.06);
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 10px;
        padding: 1.25rem 1.4rem;
        color: #e5e7eb;
        font-size: .92rem; line-height: 1.55;
    }
    #lb-resena-panel.activa { display: block; }
    #lb-resena-panel .lb-r-autor    { font-weight: 700; color: #fff; margin-bottom: .3rem; font-size: 1rem; }
    #lb-resena-panel .lb-r-estrellas{ color: #facc15; letter-spacing: 1px; font-size: 1rem; margin-bottom: .4rem; }
    #lb-resena-panel .lb-r-fecha    { color: #9ca3af; font-size: .75rem; margin-bottom: .9rem; }
    #lb-resena-panel .lb-r-texto    { color: #e5e7eb; line-height: 1.6; white-space: pre-wrap; }
    #lb-resena-panel .lb-r-badge    { display:inline-flex; align-items:center; gap:.25rem; background:rgba(21,128,61,.85); color:#fff; font-size:.65rem; padding:.15rem .55rem; border-radius:999px; font-weight:700; margin-bottom:.7rem; }

    @media (max-width: 900px) {
        #lb-stage { flex-direction: column; gap: 1rem; max-height: 95vh; }
        #lb-img-wrap, #lb-img { max-width: 92vw; max-height: 60vh; }
        #lb-resena-panel { max-width: 92vw; max-height: 30vh; }
    }
</style>
<div id="lb-overlay" onclick="if(event.target===this)lbClose()">
    <button onclick="lbClose()" style="position:absolute;top:1rem;right:1.5rem;background:none;border:none;color:#fff;font-size:2rem;cursor:pointer;line-height:1;padding:.25rem .5rem;z-index:10;" title="Cerrar (Esc)">✕</button>
    <button id="lb-prev" onclick="lbNav(-1)" style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;font-size:2rem;cursor:pointer;border-radius:50%;width:3rem;height:3rem;display:flex;align-items:center;justify-content:center;z-index:10;" title="Anterior (←)">‹</button>
    <button id="lb-next" onclick="lbNav(1)" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;font-size:2rem;cursor:pointer;border-radius:50%;width:3rem;height:3rem;display:flex;align-items:center;justify-content:center;z-index:10;" title="Siguiente (→)">›</button>

    <div id="lb-stage">
        <div id="lb-img-wrap">
            <img id="lb-img" src="" alt="">
        </div>
        <aside id="lb-resena-panel" aria-label="Reseña del cliente"></aside>
    </div>

    <p id="lb-caption" style="color:#ddd;margin-top:.9rem;font-size:.9rem;max-width:80vw;text-align:center;line-height:1.4;"></p>
    <p id="lb-counter" style="color:#888;margin-top:.2rem;font-size:.75rem;"></p>
</div>
<script>
const _lb = <?= $lightbox_json ?>;
let _lbi = 0;

function _lbEstrellas(n) {
    const filled = Math.max(0, Math.min(5, n|0));
    return '★'.repeat(filled) + '☆'.repeat(5 - filled);
}
function _lbFmtFecha(s) {
    if (!s) return '';
    // Aceptar 'YYYY-MM-DD HH:MM:SS' o ISO
    const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
}
function _lbEsc(s) {
    return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function lbShow() {
    if (!_lb.length) return;
    const d = _lb[_lbi];
    document.getElementById('lb-img').src     = d.url;
    document.getElementById('lb-img').alt     = d.alt;
    document.getElementById('lb-caption').textContent = d.alt;
    document.getElementById('lb-counter').textContent = _lb.length > 1 ? (_lbi + 1) + ' / ' + _lb.length : '';
    document.getElementById('lb-prev').style.visibility = _lbi > 0 ? 'visible' : 'hidden';
    document.getElementById('lb-next').style.visibility = _lbi < _lb.length - 1 ? 'visible' : 'hidden';

    // Panel de reseña si la imagen viene de un cliente con reseña asociada
    const panel = document.getElementById('lb-resena-panel');
    if (d.esCliente && d.resenaNombre) {
        panel.innerHTML = `
            <div class="lb-r-badge">📷 Foto enviada por un cliente</div>
            <div class="lb-r-autor">${_lbEsc(d.resenaNombre)}</div>
            <div class="lb-r-estrellas" title="${d.resenaCalificacion||0} de 5">${_lbEstrellas(d.resenaCalificacion||0)}</div>
            <div class="lb-r-fecha">${_lbEsc(_lbFmtFecha(d.resenaFecha))}</div>
            <div class="lb-r-texto">${_lbEsc(d.resenaComentario||'')}</div>
        `;
        panel.classList.add('activa');
    } else {
        panel.classList.remove('activa');
        panel.innerHTML = '';
    }
}
function lightboxOpen(idx) {
    _lbi = idx;
    lbShow();
    document.getElementById('lb-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function lbClose() {
    document.getElementById('lb-overlay').style.display = 'none';
    document.body.style.overflow = '';
}
function lbNav(d) {
    _lbi = Math.max(0, Math.min(_lb.length - 1, _lbi + d));
    lbShow();
}
document.addEventListener('keydown', function(e) {
    if (document.getElementById('lb-overlay').style.display === 'none') return;
    if (e.key === 'Escape')      lbClose();
    if (e.key === 'ArrowLeft')   lbNav(-1);
    if (e.key === 'ArrowRight')  lbNav(1);
});

/* ─── Galería principal: detectar fin de scroll para ocultar fade/chevron ─── */
(function() {
    var scrollEl = document.querySelector('[data-galeria-scroll]');
    if (!scrollEl) return;
    var trackEl = scrollEl.querySelector('.ficha-galeria__track');
    if (!trackEl) return;

    function check() {
        var atEnd = trackEl.scrollLeft + trackEl.clientWidth >= trackEl.scrollWidth - 4;
        scrollEl.classList.toggle('is-scrolled-end', atEnd);
    }
    trackEl.addEventListener('scroll', check, { passive: true });
    window.addEventListener('resize', check);
    // Esconder hint si no hay overflow real (poca cantidad de fotos)
    if (trackEl.scrollWidth <= trackEl.clientWidth + 4) {
        scrollEl.classList.add('is-scrolled-end');
    }
})();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
