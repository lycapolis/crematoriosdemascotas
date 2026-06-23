<?php
declare(strict_types=1);
/**
 * ═══════════════════════════════════════════════════════════
 * BACKFILL — google_place_id desde Google Places API (New)
 * ═══════════════════════════════════════════════════════════
 *
 * Recorre fichas activas con google_place_id vacío y consulta
 * Places API "Text Search" para resolver el place_id real.
 *
 * Idempotente: re-correr no pisa lo ya escrito.
 * Por defecto DRY-RUN — solo escribe con --apply.
 *
 * Uso (CLI):
 *   php admin/migrations/backfill_google_place_id.php
 *   php admin/migrations/backfill_google_place_id.php --apply
 *   php admin/migrations/backfill_google_place_id.php --verbose
 *   php admin/migrations/backfill_google_place_id.php --id=42
 *
 * Costo aprox: $0.032 por ficha (Text Search) — gratis bajo free
 * credit mensual de Google ($200).
 * ═══════════════════════════════════════════════════════════
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo corre por CLI.');
}

$root = dirname(__DIR__, 2);
require_once $root . '/includes/config.php';
require_once $root . '/includes/conexion_db.php';
require_once $root . '/includes/funciones.php';

// ─── Args ───────────────────────────────────────────────────
$apply   = in_array('--apply', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$onlyId  = null;
foreach ($argv as $a) {
    if (preg_match('/^--id=(\d+)$/', $a, $m)) { $onlyId = (int)$m[1]; }
}

if (!defined('GOOGLE_MAPS_API_KEY') || GOOGLE_MAPS_API_KEY === '') {
    fwrite(STDERR, "ERROR: GOOGLE_MAPS_API_KEY no configurada en includes/config.php\n");
    exit(1);
}

$pdo = obtenerConexion();
if (!$pdo) { fwrite(STDERR, "ERROR: conexión BD\n"); exit(1); }

// ─── Fetch fichas ───────────────────────────────────────────
$sql = "SELECT c.id, c.nombre, c.ciudad, c.direccion_completa, p.nombre AS provincia
        FROM crematorios c
        LEFT JOIN provincias p ON p.id = c.provincia_id
        WHERE c.estado = 'activa'
          AND (c.google_place_id IS NULL OR c.google_place_id = '')";
if ($onlyId) { $sql .= " AND c.id = :id"; }
$sql .= " ORDER BY c.id";

$stmt = $pdo->prepare($sql);
if ($onlyId) { $stmt->bindValue(':id', $onlyId, PDO::PARAM_INT); }
$stmt->execute();
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Modo:    " . ($apply ? 'APPLY (escribe a BD)' : 'DRY-RUN (no escribe)') . "\n";
echo "Fichas:  " . count($fichas) . " a procesar\n";
echo str_repeat('─', 70) . "\n";

// ─── Helpers ────────────────────────────────────────────────
function placesTextSearch(string $query, string $apiKey): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://places.googleapis.com/v1/places:searchText',
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.types'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'textQuery'      => $query,
            'languageCode'   => 'es',
            'regionCode'     => 'es',
            'maxResultCount' => 3
        ])
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) {
        return ['__error' => "HTTP $code", '__body' => $resp];
    }
    return json_decode($resp, true) ?: ['__error' => 'JSON inválido'];
}

function normaliza(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    if (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($tmp !== false) { $s = $tmp; }
    }
    $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

// ─── Procesar ───────────────────────────────────────────────
$ok = 0; $skip = 0; $err = 0;

foreach ($fichas as $f) {
    $id        = (int)$f['id'];
    $nombre    = trim((string)$f['nombre']);
    $ciudad    = trim((string)$f['ciudad']);
    $direccion = trim((string)$f['direccion_completa']);
    $provincia = trim((string)$f['provincia']);

    // Query: nombre + dirección completa si la hay; fallback a ciudad/provincia
    $query = $nombre;
    if ($direccion !== '')      { $query .= ' ' . $direccion; }
    elseif ($ciudad !== '')     { $query .= ' ' . $ciudad . ($provincia ? ', ' . $provincia : ''); }
    elseif ($provincia !== '')  { $query .= ' ' . $provincia; }

    echo "[$id] $nombre\n";
    if ($verbose) { echo "    Q: $query\n"; }

    $res = placesTextSearch($query, GOOGLE_MAPS_API_KEY);

    if (isset($res['__error'])) {
        echo "    ❌ API error: {$res['__error']}\n";
        if ($verbose && isset($res['__body'])) {
            echo "    " . substr((string)$res['__body'], 0, 250) . "\n";
        }
        $err++;
        continue;
    }

    if (empty($res['places']) || !is_array($res['places'])) {
        echo "    ⚠️  Sin resultados — manual\n";
        $skip++;
        continue;
    }

    $first            = $res['places'][0];
    $placeId          = $first['id'] ?? null;
    $displayName      = (string)($first['displayName']['text'] ?? '');
    $formattedAddress = (string)($first['formattedAddress'] ?? '');

    if (!$placeId) {
        echo "    ⚠️  Primer resultado sin id — skip\n";
        $skip++;
        continue;
    }

    // Heurística de confianza:
    //   - Alta:  provincia matchea + nombre similar ≥40%
    //   - Media: solo uno de los dos (provincia o nombre ≥60%)
    //   - Baja:  ninguno → skip (revisar a mano)
    $matchProv = ($provincia !== '' && stripos($formattedAddress, $provincia) !== false);
    $simNombre = 0.0;
    similar_text(normaliza($nombre), normaliza($displayName), $simNombre);

    if ($matchProv && $simNombre >= 40) { $confianza = 'alta'; }
    elseif ($matchProv || $simNombre >= 60) { $confianza = 'media'; }
    else { $confianza = 'baja'; }

    echo "    📍 $displayName\n";
    echo "       $formattedAddress\n";
    echo "       place_id=$placeId · confianza=$confianza (prov=" .
         ($matchProv ? 'sí' : 'no') . ", sim=" . round($simNombre) . "%)\n";

    if ($confianza === 'baja') {
        echo "    ⚠️  Confianza baja — skip (revisar a mano)\n";
        $skip++;
        continue;
    }

    if ($apply) {
        $upd = $pdo->prepare("UPDATE crematorios SET google_place_id = :pid, updated_at = NOW() WHERE id = :id");
        $upd->execute([':pid' => $placeId, ':id' => $id]);
        echo "    ✅ Escrito\n";
    } else {
        echo "    ✅ Match OK (dry-run)\n";
    }
    $ok++;
}

echo str_repeat('─', 70) . "\n";
echo "Resumen: OK=$ok · skip=$skip · err=$err · total=" . count($fichas) . "\n";
if (!$apply && $ok > 0) {
    echo "\nDry-run completo. Para escribir: agregar --apply\n";
}
