<?php
/**
 * Backfill one-off: poblar crematorios.google_verificado desde R10.csv.
 *
 * R10.csv NO tiene place_id → match por website (host normalizado) y, si no,
 * por nombre+ciudad normalizados. Solo actualiza con match ÚNICO y valor
 * definido (True/False). Idempotente (re-ejecutable). Lo ambiguo/sin match
 * NO se toca y se reporta.
 *
 * Uso (CLI):  php admin/migrations/backfill_google_verificado.php [ruta_csv] [--apply]
 *   sin --apply  → DRY RUN (solo reporta, no escribe)
 *   con --apply  → escribe los UPDATE
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Solo CLI'); }

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
require_once dirname(__DIR__, 2) . '/includes/funciones.php';

$csvPath = $argv[1] ?? 'C:/Users/User/Documents/Fiverr & Lycapolis/crematoriosdemascotas.com/BBDDs/output/crematorios_R10.csv';
$apply   = in_array('--apply', $argv, true);

if (!is_file($csvPath)) { exit("CSV no encontrado: $csvPath\n"); }

function norm($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return $s;
}
function hostKey($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (!preg_match('~^https?://~i', $url)) $url = 'http://' . $url;
    $h = parse_url($url, PHP_URL_HOST);
    if (!$h) return '';
    return preg_replace('/^www\./', '', mb_strtolower($h, 'UTF-8'));
}
function boolG($v) {
    $v = strtolower(trim((string)$v));
    if ($v === 'true')  return 1;
    if ($v === 'false') return 0;
    return null;
}

// ── Leer R10.csv → mapas web / nombre+ciudad ──
$fh = fopen($csvPath, 'r');
$head = fgetcsv($fh);
$ix = array_flip($head);
foreach (['name','city','website','verified'] as $col) {
    if (!isset($ix[$col])) exit("Falta columna '$col' en el CSV\n");
}
$byWeb = []; $byNC = []; $filas = 0;
while (($r = fgetcsv($fh)) !== false) {
    $filas++;
    $v = boolG($r[$ix['verified']] ?? '');
    if ($v === null) continue; // sin dato útil, no aporta
    $hk = hostKey($r[$ix['website']] ?? '');
    if ($hk !== '') {
        $byWeb[$hk][] = $v;
    }
    $nc = norm($r[$ix['name']] ?? '') . '|' . norm($r[$ix['city']] ?? '');
    if ($nc !== '|') $byNC[$nc][] = $v;
}
fclose($fh);

// Resolver a valor único: si todas las ocurrencias coinciden → ese valor; si discrepan → ambiguo (null)
$resolver = function ($arr) {
    $u = array_values(array_unique($arr));
    return count($u) === 1 ? $u[0] : null;
};

$pdo = obtenerConexion();
$cr = $pdo->query("SELECT id, nombre, ciudad, website, google_verificado FROM crematorios")->fetchAll(PDO::FETCH_ASSOC);

$st = ['total'=>count($cr),'web'=>0,'nc'=>0,'set1'=>0,'set0'=>0,'sin_match'=>0,'ambiguo'=>0,'sin_cambio'=>0];
$upd = $pdo->prepare("UPDATE crematorios SET google_verificado = :v WHERE id = :id");

foreach ($cr as $c) {
    $val = null; $via = null;
    $hk = hostKey($c['website']);
    if ($hk !== '' && isset($byWeb[$hk])) { $val = $resolver($byWeb[$hk]); if ($val !== null) $via = 'web'; }
    if ($val === null) {
        $nc = norm($c['nombre']) . '|' . norm($c['ciudad']);
        if (isset($byNC[$nc])) { $val = $resolver($byNC[$nc]); if ($val !== null) $via = 'nc'; }
    }
    if ($val === null) { $st['sin_match']++; continue; }
    $st[$via]++;
    if ((string)$c['google_verificado'] === (string)$val) { $st['sin_cambio']++; }
    if ($apply) $upd->execute([':v'=>$val, ':id'=>$c['id']]);
    $st[$val === 1 ? 'set1' : 'set0']++;
}

echo ($apply ? "== APLICADO ==\n" : "== DRY RUN (sin escribir; usá --apply) ==\n");
echo "CSV filas: $filas · DB fichas: {$st['total']}\n";
echo "Match por website: {$st['web']} · por nombre+ciudad: {$st['nc']}\n";
echo "google_verificado=1: {$st['set1']} · =0: {$st['set0']}\n";
echo "Sin match (queda NULL): {$st['sin_match']} · ya estaban igual: {$st['sin_cambio']}\n";
