<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CIUDADES — siembra a partir de las fichas (idempotente)
 * ═══════════════════════════════════════════════════════════
 * Crea en `ciudades` las ciudades que existen hoy en el portal (fichas
 * activas), les pone coordenadas y enlaza cada ficha con su ciudad
 * (`crematorios.ciudad_id`). NO toca `crematorios.ciudad`.
 *
 * Coordenadas: Google Geocoding («Ciudad, Provincia»). Si Google falla se usa el
 * promedio de las coordenadas de sus fichas. Si las fichas quedan a más de 30 km
 * del centro de la ciudad, se avisa en el informe.
 *
 * Ejecutar DESPUÉS de ciudades_modelo_1.sql:
 *   CLI:  php admin/migrations/seed_ciudades.php [--sin-google]
 *   (--sin-google: no llama a Google; usa solo el promedio de las fichas)
 *
 * Si la ciudad ya existe, no la duplica ni pisa lo que se editó a mano.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por línea de comandos.'); }

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
require_once dirname(__DIR__, 2) . '/includes/funciones.php';

$sinGoogle = in_array('--sin-google', $argv ?? [], true);
$pdo = obtenerConexion();
if (!$pdo) exit("Sin conexión a la base de datos.\n");

$tieneModelo = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ciudades' AND COLUMN_NAME = 'lat'")->fetchColumn();
if (!$tieneModelo) exit("Falta correr ciudades_modelo_1.sql antes de este script.\n");

// Ciudades que existen y se muestran hoy, pero que conviene revisar a mano.
$dudosas = [
    'zangroiz'         => 'Parece un barrio (Sondika), no un municipio.',
    'pozo-los-palos'   => 'Parece una pedanía (Cartagena), no un municipio.',
    'orozco'           => 'El municipio se escribe «Orozko»; revisar nombre oficial.',
    'elx'              => 'Forma cooficial de «Elche»; decidir cuál usar en SEO.',
    'maliano'          => 'Localidad de Camargo; decidir si se agrupa con el municipio.',
    'esquiroz'         => 'Localidad de Galar; decidir si se agrupa con el municipio.',
];
// Escrituras alternativas conocidas (se guardan como alias para los 301).
$alias = [
    'elx'     => ['elche'],
    'orozco'  => ['orozko'],
];

function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $r = 6371;
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($a)));
}

$provincias = $pdo->query("SELECT id, nombre FROM provincias")->fetchAll(PDO::FETCH_KEY_PAIR);

$fichas = $pdo->query("SELECT id, ciudad, provincia_id, estado, latitud, longitud
                       FROM crematorios
                       WHERE estado IN ('activa','demo') AND ciudad IS NOT NULL AND TRIM(ciudad) <> ''")
              ->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por (provincia, slug). Solo las fichas activas crean ciudades.
$grupos = [];
foreach ($fichas as $f) {
    $nombre = trim(preg_replace('/\s+/u', ' ', $f['ciudad']));
    $slug   = slugificar($nombre);
    if ($slug === '') continue;
    $k = $f['provincia_id'] . '|' . $slug;
    $grupos[$k]['provincia_id'] = (int) $f['provincia_id'];
    $grupos[$k]['slug']         = $slug;
    $grupos[$k]['nombres'][$nombre] = ($grupos[$k]['nombres'][$nombre] ?? 0) + ($f['estado'] === 'activa' ? 1 : 0);
    $grupos[$k]['fichas'][]     = $f;
    if ($f['estado'] === 'activa') $grupos[$k]['activa'] = true;
}

$insCiudad = $pdo->prepare("INSERT INTO ciudades (provincia_id, nombre, slug, slug_alias_json, lat, lng, estado_revision, nota)
                            VALUES (:p, :n, :s, :a, :lat, :lng, :e, :nota)");
$selCiudad = $pdo->prepare("SELECT id FROM ciudades WHERE provincia_id = :p AND slug = :s");
$updFicha  = $pdo->prepare("UPDATE crematorios SET ciudad_id = :cid, ciudad_original = COALESCE(ciudad_original, ciudad)
                            WHERE id = :id AND ciudad_id IS NULL");

$creadas = 0; $existentes = 0; $conAviso = []; $fichasEnlazadas = 0;

foreach ($grupos as $g) {
    $selCiudad->execute([':p' => $g['provincia_id'], ':s' => $g['slug']]);
    $cid = $selCiudad->fetchColumn();

    if (!$cid) {
        if (empty($g['activa'])) continue; // ciudad solo de fichas demo: no se crea

        arsort($g['nombres']);
        $nombre = array_key_first($g['nombres']);
        $provNombre = $provincias[$g['provincia_id']] ?? '';

        // Promedio de coordenadas de las fichas de la ciudad (red de seguridad)
        $lats = []; $lngs = [];
        foreach ($g['fichas'] as $f) {
            if ($f['estado'] === 'activa' && (float) $f['latitud'] != 0) { $lats[] = (float) $f['latitud']; $lngs[] = (float) $f['longitud']; }
        }
        $prom = $lats ? [array_sum($lats) / count($lats), array_sum($lngs) / count($lngs)] : null;

        $lat = $lng = null; $origen = 'sin coordenadas';
        if (!$sinGoogle) {
            $geo = geocodificarDireccion($nombre . ', ' . $provNombre, '', 'ES');
            if (!empty($geo['ok'])) {
                // Se usa siempre el punto de la ciudad según Google; si queda lejos de sus
                // fichas, se avisa (suele ser una ficha con coordenadas mal puestas).
                $lat = $geo['lat']; $lng = $geo['lng']; $origen = 'Google';
                if ($prom !== null && distanciaKm($geo['lat'], $geo['lng'], $prom[0], $prom[1]) > 30) {
                    $conAviso[] = "$nombre ($provNombre): sus fichas quedan a más de 30 km del centro de la ciudad; revisar las coordenadas de la ficha.";
                }
            } else {
                $conAviso[] = "$nombre ($provNombre): Google no la encontró (" . ($geo['error'] ?? '?') . "); se usó el promedio de las fichas.";
            }
            usleep(150000);
        }
        if ($lat === null && $prom !== null) { [$lat, $lng] = $prom; $origen = 'promedio de fichas'; }

        $esDudosa = isset($dudosas[$g['slug']]);
        $insCiudad->execute([
            ':p'    => $g['provincia_id'], ':n' => $nombre, ':s' => $g['slug'],
            ':a'    => isset($alias[$g['slug']]) ? json_encode($alias[$g['slug']], JSON_UNESCAPED_UNICODE) : null,
            ':lat'  => $lat, ':lng' => $lng,
            ':e'    => $esDudosa ? 'dudosa' : 'aprobada',
            ':nota' => $esDudosa ? $dudosas[$g['slug']] : null,
        ]);
        $cid = (int) $pdo->lastInsertId();
        $creadas++;
        echo sprintf("  + %-28s %-14s %s\n", $nombre, $provNombre, $origen);
    } else {
        $existentes++;
    }

    foreach ($g['fichas'] as $f) {
        $updFicha->execute([':cid' => $cid, ':id' => $f['id']]);
        $fichasEnlazadas += $updFicha->rowCount();
    }
}

echo "\n── Informe ──\n";
echo "Ciudades creadas: $creadas · ya existían: $existentes · fichas enlazadas ahora: $fichasEnlazadas\n";
$sin = $pdo->query("SELECT id, nombre, ciudad FROM crematorios WHERE estado = 'activa' AND ciudad_id IS NULL")->fetchAll(PDO::FETCH_ASSOC);
echo "Fichas activas sin ciudad_id: " . count($sin) . "\n";
foreach ($sin as $s) echo "   · #{$s['id']} {$s['nombre']} (ciudad: " . ($s['ciudad'] ?: 'vacía') . ")\n";
$dud = $pdo->query("SELECT nombre, nota FROM ciudades WHERE estado_revision = 'dudosa' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
echo "Ciudades dudosas para revisar a mano: " . count($dud) . "\n";
foreach ($dud as $d) echo "   · {$d['nombre']}: {$d['nota']}\n";
$sc = $pdo->query("SELECT nombre FROM ciudades WHERE lat IS NULL")->fetchAll(PDO::FETCH_COLUMN);
echo "Ciudades sin coordenadas: " . count($sc) . ($sc ? ' (' . implode(', ', $sc) . ')' : '') . "\n";
if ($conAviso) { echo "Avisos de geocodificación:\n"; foreach ($conAviso as $a) echo "   · $a\n"; }
