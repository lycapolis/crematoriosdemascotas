<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CREMATORIOS DE MASCOTAS - SCRIPT DE IMPORTACIÓN PHP
 * ═══════════════════════════════════════════════════════════
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Fecha: 2026-01-25
 *
 * Uso: Ejecutar desde navegador o CLI
 * php importar_csv.php
 * ═══════════════════════════════════════════════════════════
 */

// Configuración de errores para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuración de base de datos
$config = [
    'host' => 'localhost',
    'dbname' => 'crematorios_mascotas',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

// Ruta al CSV
$csvFile = __DIR__ . '/20260125-bbdd.csv';

// ───────────────────────────────────────────────────────────
// FUNCIONES AUXILIARES
// ───────────────────────────────────────────────────────────

function conectarDB($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

function generarSlug($texto) {
    $slug = mb_strtolower($texto, 'UTF-8');
    $slug = preg_replace('/[áàäâ]/u', 'a', $slug);
    $slug = preg_replace('/[éèëê]/u', 'e', $slug);
    $slug = preg_replace('/[íìïî]/u', 'i', $slug);
    $slug = preg_replace('/[óòöô]/u', 'o', $slug);
    $slug = preg_replace('/[úùüû]/u', 'u', $slug);
    $slug = preg_replace('/[ñ]/u', 'n', $slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

function limpiarValor($valor) {
    $valor = trim($valor);
    return $valor === '' ? null : $valor;
}

function limpiarNumero($valor) {
    $valor = trim($valor);
    if ($valor === '' || $valor === null) return null;
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? $valor : null;
}

function limpiarEntero($valor) {
    $valor = trim($valor);
    if ($valor === '' || $valor === null) return 0;
    return (int)$valor;
}

function output($mensaje) {
    if (php_sapi_name() === 'cli') {
        echo $mensaje . "\n";
    } else {
        echo $mensaje . "<br>";
    }
    flush();
}

// ───────────────────────────────────────────────────────────
// INICIO DEL SCRIPT
// ───────────────────────────────────────────────────────────

output("═══════════════════════════════════════════════════════════");
output("IMPORTACIÓN DE CREMATORIOS DE MASCOTAS");
output("═══════════════════════════════════════════════════════════");
output("");

// Verificar que existe el CSV
if (!file_exists($csvFile)) {
    die("Error: No se encontró el archivo CSV en: $csvFile");
}

output("✓ Archivo CSV encontrado: $csvFile");

// Conectar a la base de datos
$pdo = conectarDB($config);
output("✓ Conexión a base de datos establecida");

// ───────────────────────────────────────────────────────────
// PASO 1: Leer CSV y extraer datos únicos
// ───────────────────────────────────────────────────────────
output("");
output("PASO 1: Leyendo CSV y extrayendo datos únicos...");

$handle = fopen($csvFile, 'r');
if (!$handle) {
    die("Error: No se pudo abrir el archivo CSV");
}

// Leer cabecera
$cabecera = fgetcsv($handle);
$cabeceraLimpia = array_map('trim', $cabecera);

// Leer todos los registros
$registros = [];
$comunidadesUnicas = [];
$provinciasUnicas = [];

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < count($cabeceraLimpia)) {
        continue; // Saltar filas incompletas
    }

    $registro = array_combine($cabeceraLimpia, $row);
    $registros[] = $registro;

    $comunidad = trim($registro['comunidad_autonoma']);
    $provincia = trim($registro['state']);

    if ($comunidad && !isset($comunidadesUnicas[$comunidad])) {
        $comunidadesUnicas[$comunidad] = generarSlug($comunidad);
    }

    if ($provincia && $comunidad) {
        $key = "$comunidad|$provincia";
        if (!isset($provinciasUnicas[$key])) {
            $provinciasUnicas[$key] = [
                'comunidad' => $comunidad,
                'provincia' => $provincia,
                'slug' => generarSlug($provincia)
            ];
        }
    }
}
fclose($handle);

output("  - Total registros: " . count($registros));
output("  - Comunidades únicas: " . count($comunidadesUnicas));
output("  - Provincias únicas: " . count($provinciasUnicas));

// ───────────────────────────────────────────────────────────
// PASO 2: Insertar comunidades autónomas
// ───────────────────────────────────────────────────────────
output("");
output("PASO 2: Insertando comunidades autónomas...");

$stmtComunidad = $pdo->prepare("
    INSERT INTO comunidades_autonomas (nombre, slug)
    VALUES (:nombre, :slug)
    ON DUPLICATE KEY UPDATE nombre = nombre
");

foreach ($comunidadesUnicas as $nombre => $slug) {
    $stmtComunidad->execute(['nombre' => $nombre, 'slug' => $slug]);
}

output("  ✓ " . count($comunidadesUnicas) . " comunidades procesadas");

// Obtener IDs de comunidades
$comunidadesMap = [];
$result = $pdo->query("SELECT id, nombre FROM comunidades_autonomas");
while ($row = $result->fetch()) {
    $comunidadesMap[$row['nombre']] = $row['id'];
}

// ───────────────────────────────────────────────────────────
// PASO 3: Insertar provincias
// ───────────────────────────────────────────────────────────
output("");
output("PASO 3: Insertando provincias...");

$stmtProvincia = $pdo->prepare("
    INSERT INTO provincias (comunidad_id, nombre, slug)
    VALUES (:comunidad_id, :nombre, :slug)
    ON DUPLICATE KEY UPDATE nombre = nombre
");

foreach ($provinciasUnicas as $data) {
    $comunidadId = $comunidadesMap[$data['comunidad']] ?? null;
    if ($comunidadId) {
        $stmtProvincia->execute([
            'comunidad_id' => $comunidadId,
            'nombre' => $data['provincia'],
            'slug' => $data['slug']
        ]);
    }
}

output("  ✓ " . count($provinciasUnicas) . " provincias procesadas");

// Obtener IDs de provincias
$provinciasMap = [];
$result = $pdo->query("SELECT id, nombre FROM provincias");
while ($row = $result->fetch()) {
    $provinciasMap[$row['nombre']] = $row['id'];
}

// ───────────────────────────────────────────────────────────
// PASO 4: Insertar crematorios
// ───────────────────────────────────────────────────────────
output("");
output("PASO 4: Insertando crematorios...");

$stmtCrematorio = $pdo->prepare("
    INSERT INTO crematorios (
        provincia_id, nombre, slug, subtypes, telefono, email, website,
        direccion_completa, calle, ciudad, distrito, codigo_postal,
        latitud, longitud, rating, reviews_total, reviews_link,
        reviews_1, reviews_2, reviews_3, reviews_4, reviews_5,
        foto_principal, street_view, logo, business_status,
        verificado, destacado, activo, horarios,
        booking_link, menu_link, location_link, location_reviews_link,
        about, rango_precios, precios, descripcion_google, descripcion,
        prestaciones, servicios, facilidades, accesibilidad,
        place_id, cid, reviews_id
    ) VALUES (
        :provincia_id, :nombre, :slug, :subtypes, :telefono, :email, :website,
        :direccion_completa, :calle, :ciudad, :distrito, :codigo_postal,
        :latitud, :longitud, :rating, :reviews_total, :reviews_link,
        :reviews_1, :reviews_2, :reviews_3, :reviews_4, :reviews_5,
        :foto_principal, :street_view, :logo, :business_status,
        :verificado, :destacado, :activo, :horarios,
        :booking_link, :menu_link, :location_link, :location_reviews_link,
        :about, :rango_precios, :precios, :descripcion_google, :descripcion,
        :prestaciones, :servicios, :facilidades, :accesibilidad,
        :place_id, :cid, :reviews_id
    )
    ON DUPLICATE KEY UPDATE
        nombre = VALUES(nombre),
        updated_at = CURRENT_TIMESTAMP
");

$insertados = 0;
$errores = 0;

foreach ($registros as $i => $r) {
    $provincia = trim($r['state']);
    $provinciaId = $provinciasMap[$provincia] ?? null;

    if (!$provinciaId) {
        output("  ! Error: Provincia no encontrada: $provincia");
        $errores++;
        continue;
    }

    try {
        $stmtCrematorio->execute([
            'provincia_id' => $provinciaId,
            'nombre' => limpiarValor($r['name']),
            'slug' => limpiarValor($r['slug']),
            'subtypes' => limpiarValor($r['subtypes']),
            'telefono' => limpiarValor($r['phone']),
            'email' => limpiarValor($r['email'] ?? null),
            'website' => limpiarValor($r['website']),
            'direccion_completa' => limpiarValor($r['address']),
            'calle' => limpiarValor($r['street']),
            'ciudad' => limpiarValor($r['city']),
            'distrito' => limpiarValor($r['county']),
            'codigo_postal' => limpiarValor($r['postal_code']),
            'latitud' => limpiarNumero($r['latitude']),
            'longitud' => limpiarNumero($r['longitude']),
            'rating' => limpiarNumero($r['rating']),
            'reviews_total' => limpiarEntero($r['reviews']),
            'reviews_link' => limpiarValor($r['reviews_link']),
            'reviews_1' => limpiarEntero($r['reviews_per_score_1']),
            'reviews_2' => limpiarEntero($r['reviews_per_score_2']),
            'reviews_3' => limpiarEntero($r['reviews_per_score_3']),
            'reviews_4' => limpiarEntero($r['reviews_per_score_4']),
            'reviews_5' => limpiarEntero($r['reviews_per_score_5']),
            'foto_principal' => limpiarValor($r['photo']),
            'street_view' => limpiarValor($r['street_view']),
            'logo' => limpiarValor($r['logo']),
            'business_status' => limpiarValor($r['business_status']) ?? 'OPERATIONAL',
            'verificado' => limpiarEntero($r['verified']),
            'destacado' => limpiarEntero($r['destacado'] ?? 0),
            'activo' => limpiarEntero($r['activo'] ?? 1),
            'horarios' => limpiarValor($r['working_hours']),
            'booking_link' => limpiarValor($r['booking_appointment_link']),
            'menu_link' => limpiarValor($r['menu_link']),
            'location_link' => limpiarValor($r['location_link']),
            'location_reviews_link' => limpiarValor($r['location_reviews_link']),
            'about' => limpiarValor($r['about']),
            'rango_precios' => limpiarValor($r['range'] ?? null),
            'precios' => limpiarValor($r['prices']),
            'descripcion_google' => limpiarValor($r['description']),
            'descripcion' => limpiarValor($r['descripcion'] ?? null),
            'prestaciones' => limpiarValor($r['prestaciones'] ?? null),
            'servicios' => limpiarValor($r['servicios'] ?? null),
            'facilidades' => limpiarValor($r['facilidades'] ?? null),
            'accesibilidad' => limpiarValor($r['accesibilidad'] ?? null),
            'place_id' => limpiarValor($r['place_id']),
            'cid' => limpiarValor($r['cid']),
            'reviews_id' => limpiarValor($r['reviews_id'])
        ]);
        $insertados++;
    } catch (PDOException $e) {
        output("  ! Error en registro " . ($i + 1) . ": " . $e->getMessage());
        $errores++;
    }
}

output("  ✓ $insertados crematorios insertados");
if ($errores > 0) {
    output("  ! $errores errores encontrados");
}

// ───────────────────────────────────────────────────────────
// RESUMEN FINAL
// ───────────────────────────────────────────────────────────
output("");
output("═══════════════════════════════════════════════════════════");
output("RESUMEN DE IMPORTACIÓN");
output("═══════════════════════════════════════════════════════════");

$stats = $pdo->query("
    SELECT 'Comunidades Autónomas' as tabla, COUNT(*) as total FROM comunidades_autonomas
    UNION ALL
    SELECT 'Provincias', COUNT(*) FROM provincias
    UNION ALL
    SELECT 'Crematorios', COUNT(*) FROM crematorios
")->fetchAll();

foreach ($stats as $row) {
    output("  {$row['tabla']}: {$row['total']}");
}

output("");
output("CREMATORIOS POR COMUNIDAD:");

$porComunidad = $pdo->query("
    SELECT ca.nombre, COUNT(c.id) as total
    FROM comunidades_autonomas ca
    LEFT JOIN provincias p ON p.comunidad_id = ca.id
    LEFT JOIN crematorios c ON c.provincia_id = p.id
    GROUP BY ca.id, ca.nombre
    ORDER BY total DESC
")->fetchAll();

foreach ($porComunidad as $row) {
    output("  - {$row['nombre']}: {$row['total']}");
}

output("");
output("═══════════════════════════════════════════════════════════");
output("IMPORTACIÓN COMPLETADA");
output("═══════════════════════════════════════════════════════════");
