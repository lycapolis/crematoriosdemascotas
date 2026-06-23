<?php
/**
 * Script de importación ONE-SHOT — importa img1_url/img2_url/img3_url del CSV
 * a crematorio_imagenes. Eliminar tras ejecutar.
 */

require_once 'auth.php';
require_once dirname(__DIR__) . '/includes/funciones.php';
requerirAutenticacion();

$csvPath = 'C:/Users/User/Documents/Fiverr & Lycapolis/crematoriosdemascotas.com/BBDDs/output/crematorios_R10.csv';

if (!file_exists($csvPath)) {
    die('CSV no encontrado: ' . htmlspecialchars($csvPath));
}

$pdo = obtenerConexion();

// Leer CSV
$handle = fopen($csvPath, 'r');
$headers = fgetcsv($handle);
$headers = array_map('trim', $headers);

$insertados = 0;
$omitidos   = 0;
$sinMatch   = 0;
$log        = [];

// Cache de nombres → ids del DB
$stmtAll = $pdo->query("SELECT id, nombre FROM crematorios");
$dbCrematorios = [];
foreach ($stmtAll->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $dbCrematorios[mb_strtolower(trim($row['nombre']))] = $row['id'];
}

$stmtInsert = $pdo->prepare(
    "INSERT IGNORE INTO crematorio_imagenes (crematorio_id, ruta, tipo, categoria, estado_llm, orden, origen)
     VALUES (:cid, :ruta, 'galeria', NULL, 'pendiente', 0, 'seed')"
);

// Verificar existencia por ruta
$stmtCheck = $pdo->prepare(
    "SELECT id FROM crematorio_imagenes WHERE crematorio_id = :cid AND ruta = :ruta LIMIT 1"
);

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) < 2 || empty(trim($row[0]))) continue;

    $data = array_combine($headers, array_pad($row, count($headers), ''));
    $nombre = mb_strtolower(trim($data['name'] ?? ''));
    $urls = array_filter([
        trim($data['img1_url'] ?? ''),
        trim($data['img2_url'] ?? ''),
        trim($data['img3_url'] ?? ''),
    ]);

    if (empty($urls)) continue;

    $cid = $dbCrematorios[$nombre] ?? null;
    if (!$cid) {
        $sinMatch++;
        $log[] = "⚠️  Sin match en DB: " . htmlspecialchars($data['name']);
        continue;
    }

    foreach ($urls as $url) {
        if (!str_starts_with($url, 'http')) continue;

        $stmtCheck->execute([':cid' => $cid, ':ruta' => $url]);
        if ($stmtCheck->fetch()) {
            $omitidos++;
            continue;
        }

        $stmtInsert->execute([':cid' => $cid, ':ruta' => $url]);
        $insertados++;
        $log[] = "✅ [{$cid}] " . htmlspecialchars(substr($url, 0, 80));
    }
}
fclose($handle);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Importar imágenes CSV</title>
<style>body{font-family:monospace;padding:2rem;background:#f8f9fa;}
h2{color:#1e293b;}
.ok{color:#16a34a;} .warn{color:#d97706;} .info{color:#64748b;}
pre{background:#fff;border:1px solid #e2e8f0;padding:1rem;border-radius:.5rem;max-height:60vh;overflow-y:auto;font-size:.8rem;}
</style></head><body>
<h2>Importación completada</h2>
<p class="ok">✅ Insertadas: <strong><?php echo $insertados; ?></strong></p>
<p class="info">⏭️  Ya existían (omitidas): <strong><?php echo $omitidos; ?></strong></p>
<p class="warn">⚠️  Sin match en DB: <strong><?php echo $sinMatch; ?></strong></p>
<pre><?php echo implode("\n", $log); ?></pre>
<p style="margin-top:1rem;color:#dc2626;font-weight:bold;">
    ⚠️ Elimina este archivo del servidor tras verificar los resultados.
</p>
<p><a href="fichas-negocios.php">← Volver al panel</a></p>
</body></html>
