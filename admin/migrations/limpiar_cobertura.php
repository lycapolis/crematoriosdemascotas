<?php
/**
 * ═══════════════════════════════════════════════════════════
 * COBERTURA — limpieza única de zona_cobertura / ciudades_cobertura
 * ═══════════════════════════════════════════════════════════
 * Reparte lo que escribieron los negocios en las dos cajas históricas según su tipo y normaliza los nombres:
 *   · provincias y regiones («Gerona», «Vizcaya», «Catalunya») → nombre canónico en zona_cobertura
 *   · ciudades del catálogo («polinya») → nombre canónico en ciudades_cobertura
 *   · textos limpios que no están en el catálogo («Pinto») → se conservan en ciudades_cobertura (se ven sin enlace)
 *   · frases o datos sucios («Madrid y comunidades limítrofes») → se conservan SIN tocar y se listan en el informe
 *     para corregirlos a mano (la web pública no los muestra)
 *
 * Por defecto es una SIMULACIÓN (no escribe nada). Con --apply guarda los cambios, después de copiar los valores
 * originales en la tabla `backup_cobertura_20261027` (para poder volver atrás).
 *
 *   php admin/migrations/limpiar_cobertura.php            → simulación + informe
 *   php admin/migrations/limpiar_cobertura.php --apply    → aplica
 *
 * Requiere haber corrido ciudades_modelo_1.sql, seed_ciudades.php y zonas_pais_1.sql.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por línea de comandos.'); }

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/conexion_db.php';
require_once dirname(__DIR__, 2) . '/includes/funciones.php';

$aplicar = in_array('--apply', $argv ?? [], true);
$pdo = obtenerConexion();
if (!$pdo) exit("Sin conexión a la base de datos.\n");

$fichas = $pdo->query("SELECT id, nombre, provincia_id, zona_cobertura, ciudades_cobertura FROM crematorios
                        WHERE (zona_cobertura IS NOT NULL AND zona_cobertura <> '') OR (ciudades_cobertura IS NOT NULL AND ciudades_cobertura <> '')
                        ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$tot = ['ciudad' => 0, 'provincia' => 0, 'region' => 0, 'pais' => 0, 'texto' => 0, 'descartado' => 0];
$cambios = []; $sucios = []; $textos = [];

foreach ($fichas as $f) {
    $zona = []; $ciu = []; $vistos = [];
    foreach ([['zona_cobertura', 'zona'], ['ciudades_cobertura', 'ciudad']] as [$col, $pref]) {
        foreach (preg_split('/[,;]+/', (string) $f[$col]) as $tok) {
            $c = clasificarZonaToken($tok, (int) $f['provincia_id'], $pref);
            if ($c['tipo'] === 'vacio') continue;
            $tot[$c['tipo']]++;
            $clave = $c['tipo'] . ':' . slugificar($c['nombre']);
            if (isset($vistos[$clave])) continue;      // duplicado: se descarta
            $vistos[$clave] = 1;
            if (in_array($c['tipo'], ['provincia', 'region', 'pais'], true)) { $zona[] = $c['nombre']; continue; }
            $ciu[] = $c['nombre'];
            if ($c['tipo'] === 'descartado') $sucios[] = "#{$f['id']} {$f['nombre']}: «{$c['nombre']}»";
            if ($c['tipo'] === 'texto')      $textos[$c['nombre']] = ($textos[$c['nombre']] ?? 0) + 1;
        }
    }
    $nz = implode(', ', $zona); $nc = implode(', ', $ciu);
    if ($nz !== trim((string) $f['zona_cobertura']) || $nc !== trim((string) $f['ciudades_cobertura'])) {
        $cambios[] = ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'zona' => $nz, 'ciu' => $nc,
                      'zona_antes' => (string) $f['zona_cobertura'], 'ciu_antes' => (string) $f['ciudades_cobertura']];
    }
}

echo ($aplicar ? "== APLICANDO ==\n" : "== SIMULACIÓN (no se escribe nada; usa --apply para aplicar) ==\n");
echo "Elementos revisados: " . array_sum($tot) . " → " . json_encode($tot) . "\n";
echo "Fichas con cambios: " . count($cambios) . " de " . count($fichas) . "\n";

if ($aplicar && $cambios) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS backup_cobertura_20261027 (
                    id INT UNSIGNED NOT NULL PRIMARY KEY, zona_cobertura VARCHAR(600) NULL, ciudades_cobertura VARCHAR(600) NULL)");
    $bak = $pdo->prepare("INSERT IGNORE INTO backup_cobertura_20261027 (id, zona_cobertura, ciudades_cobertura) VALUES (?, ?, ?)");
    $upd = $pdo->prepare("UPDATE crematorios SET zona_cobertura = ?, ciudades_cobertura = ? WHERE id = ?");
    $pdo->beginTransaction();
    foreach ($cambios as $c) {
        $bak->execute([$c['id'], $c['zona_antes'] ?: null, $c['ciu_antes'] ?: null]);
        $upd->execute([$c['zona'] ?: null, $c['ciu'] ?: null, $c['id']]);
    }
    $pdo->commit();
    echo "Guardado. Copia de los valores originales en la tabla backup_cobertura_20261027.\n";
}

echo "\nEjemplos de cambios:\n";
foreach (array_slice($cambios, 0, 12) as $c) {
    echo "  #{$c['id']} {$c['nombre']}\n     zona:    [{$c['zona_antes']}] → [{$c['zona']}]\n     ciudades:[{$c['ciu_antes']}] → [{$c['ciu']}]\n";
}
if ($textos) {
    arsort($textos);
    echo "\nTextos limpios que NO están en el catálogo (se ven sin enlace; si son provincias o ciudades reales, añádelas en Ciudades/Provincias y se enlazarán solas):\n  "
       . implode(' | ', array_map(fn($k, $v) => "$k($v)", array_keys($textos), $textos)) . "\n";
}
if ($sucios) {
    echo "\nFrases o datos sucios (NO se muestran al público; corregir a mano en la ficha): " . count($sucios) . "\n";
    foreach ($sucios as $s) echo "  · $s\n";
}
