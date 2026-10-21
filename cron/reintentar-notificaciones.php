<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CRON — Reintento de notificaciones y webhooks fallidos
 * ═══════════════════════════════════════════════════════════
 *
 * Retoma las filas de notificaciones_log en estado «fallida» con menos de 3 intentos
 * (de los últimos 7 días). Las filas viejas en «pendiente» NO se reintentan (se tratan
 * como duplicado). Lo ya enviado nunca se reenvía (idempotencia del despachador).
 *
 * ─── Uso ───────────────────────────────────────────────────
 *   php cron/reintentar-notificaciones.php            # reintento real
 *   php cron/reintentar-notificaciones.php --dry-run  # solo lista qué reintentaría
 *
 * ─── Cron Hostinger (hPanel → Avanzado → Cron Jobs) ────────
 *   Sugerido: cada 15 minutos.
 *   Comando:
 *     /usr/bin/php /home/USUARIO/domains/crematoriosdemascotas.com/public_html/cron/reintentar-notificaciones.php
 *   Frecuencia: en hPanel elige «Cada 15 minutos» (o, en modo manual, minuto = barra-15, resto asteriscos).
 * ═══════════════════════════════════════════════════════════
 */

// Hardening: prohibir invocación HTTP — solo CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por CLI.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion_db.php';
require_once __DIR__ . '/../includes/notificaciones/despachador.php';

$dryRun = in_array('--dry-run', $argv, true);

$pdo = obtenerConexion();
if (!$pdo) {
    echo "ERROR: sin conexión BD.\n";
    exit(1);
}

$r = notifReintentarFallidas($pdo, $dryRun);

echo "REINTENTO DE NOTIFICACIONES — " . date('Y-m-d H:i:s') . ($dryRun ? " (DRY-RUN)" : "") . "\n";
echo "Filas evaluadas: {$r['evaluadas']} · reintentadas: {$r['reintentadas']} · enviadas ahora: {$r['enviadas']} · no reintentables: {$r['sin_datos']}\n";
foreach ($r['detalle'] as $linea) echo "  - {$linea}\n";
exit(0);
