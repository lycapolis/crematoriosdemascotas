<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CRON — Campaña de prospección: envío de los emails 1-3 a los negocios
 * ═══════════════════════════════════════════════════════════
 *
 * Cada ejecución, si la campaña NO está en pausa, es lunes-viernes entre las 9:00 y las 14:00
 * (hora de España) y no se ha llegado al tope diario (10), envía los emails que toquen hasta
 * completar el tope. Fichas candidatas: estado 'activa', no verificadas, con email válido, sin baja
 * y que no hayan respondido. Pasos: 1 invitación → 2 recordatorio (7 días) → 3 último aviso (14 días).
 * Se pausa desde admin/campana-prospeccion.php (no hace falta tocar el cron de Hostinger).
 *
 * ─── Uso ───────────────────────────────────────────────────
 *   php cron/enviar-campana-prospeccion.php                    # envío real (respeta pausa, horario y tope)
 *   php cron/enviar-campana-prospeccion.php --dry-run          # muestra a quién se escribiría hoy; NO envía nada
 *   php cron/enviar-campana-prospeccion.php --demo [--to=a@b.c]  # PRUEBA: envía a las fichas demo (llegan al email de la ficha demo,
 *                                                              # o a --to si se indica), ignora pausa, horario y esperas
 *   --limite=N  (solo con --demo o --dry-run) limita cuántos se procesan
 *
 * ─── Cron Hostinger (hPanel → Avanzado → Cron Jobs) ────────
 *   Sugerido: cada 30 minutos, lunes a viernes, de 9 a 13 (el script ya vigila hora de España y tope).
 *   Comando:
 *     /usr/bin/php /home/USUARIO/domains/crematoriosdemascotas.com/public_html/cron/enviar-campana-prospeccion.php
 *   Frecuencia (modo manual): minuto 0,30 · hora 8-13 · día * · mes * · día de la semana 1-5
 *   (la hora de hPanel puede estar en UTC: por eso empieza en 8; fuera de 9-14 h España el script no envía nada).
 * ═══════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por CLI.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion_db.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/campana-prospeccion.php';

$dryRun = in_array('--dry-run', $argv, true);
$demo   = in_array('--demo', $argv, true);
$to = ''; $limiteArg = 0;
foreach ($argv as $a) {
    if (preg_match('/^--to=(.+)$/', $a, $m)) $to = trim($m[1]);
    if (preg_match('/^--limite=(\d+)$/', $a, $m)) $limiteArg = (int)$m[1];
}

$pdo = obtenerConexion();
if (!$pdo) { echo "ERROR: sin conexión BD.\n"; exit(1); }

$ahora = campanaAhoraEspana();
echo "CAMPAÑA DE PROSPECCIÓN — " . $ahora->format('Y-m-d H:i') . " (hora de España)"
   . ($dryRun ? ' · DRY-RUN' : '') . ($demo ? ' · PRUEBA CON FICHAS DEMO' : '') . "\n";

if ($to !== '' && !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "ERROR: --to no es un email válido.\n";
    exit(1);
}

$tope      = campanaTope($pdo);
$enviados  = campanaEnviadosHoy($pdo, $demo);
$quedan    = max(0, $tope - $enviados);
if ($limiteArg > 0 && ($demo || $dryRun)) $quedan = min($quedan, $limiteArg);

echo "Tope diario: {$tope} · enviados hoy" . ($demo ? ' (pruebas)' : '') . ": {$enviados} · quedan: {$quedan}\n";

if (!$dryRun && !$demo) {
    if (campanaPausada($pdo))       { echo "Campaña EN PAUSA (admin/campana-prospeccion.php). No se envía nada.\n"; exit(0); }
    if (!campanaEnVentana($ahora))  { echo "Fuera de horario (lunes-viernes, 9:00-14:00 España). No se envía nada.\n"; exit(0); }
}
if ($dryRun) {
    echo "Estado: " . (campanaPausada($pdo) ? 'EN PAUSA' : 'activa') . " · horario " . (campanaEnVentana($ahora) ? 'DENTRO' : 'FUERA') . " de la ventana de envío\n";
}
if ($quedan === 0) { echo "Tope diario alcanzado. No se envía nada.\n"; exit(0); }

$candidatas = campanaCandidatas($pdo, $demo);
echo "Fichas que cumplen las condiciones: " . count($candidatas) . "\n\n";

$etq = [1 => 'invitación', 2 => 'recordatorio', 3 => 'último aviso'];
$hechas = 0; $fallos = 0;
foreach ($candidatas as $f) {
    if ($hechas >= $quedan) break;
    $linea = "  #{$f['id']} {$f['nombre']} → email {$f['paso']} ({$etq[$f['paso']]}) a " . ($demo ? ($to ?: $f['email']) : $f['email']);

    if ($dryRun) { echo $linea . "\n"; $hechas++; continue; }

    $res = campanaEnviar($pdo, (int)$f['id'], (int)$f['paso'], $demo ? ($to ?: $f['email']) : null);
    $r = $res['email_campana'] ?? ['estado' => '?'];
    echo $linea . ' · ' . $r['estado'] . (!empty($r['motivo']) ? " ({$r['motivo']})" : '') . (!empty($r['error']) ? ' — ' . $r['error'] : '') . "\n";
    if ($r['estado'] === 'enviada') { $hechas++; $fallos = 0; }
    elseif ($r['estado'] === 'fallida' && ++$fallos >= 3) { echo "Tres fallos seguidos: se corta la tanda (¿SMTP caído?).\n"; break; }
}

echo "\n" . ($dryRun ? "Se escribiría hoy a {$hechas} ficha(s). No se ha enviado nada.\n" : "Enviados ahora: {$hechas}\n");
exit(0);
