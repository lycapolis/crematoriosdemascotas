<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CRON — Recalcular insignias automáticas y puntaje de ranking
 * ═══════════════════════════════════════════════════════════
 *
 * Necesario porque algunas insignias caducan solas (p. ej. «Popular ahora» dura 30 días
 * desde la última reseña positiva) y la popularidad depende de la ventana móvil de 90 días.
 *
 * ─── Uso ───────────────────────────────────────────────────
 *   php cron/recalcular-insignias.php
 *
 * ─── Cron Hostinger (hPanel) ───────────────────────────────
 *   Sugerido: 1x día, 04:00.
 *   Comando:
 *     /usr/bin/php /home/USUARIO/domains/crematoriosdemascotas.com/public_html/cron/recalcular-insignias.php
 *   Schedule: 0 4 * * *
 * ═══════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por CLI.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion_db.php';
require_once __DIR__ . '/../includes/insignias.php';

$pdo = obtenerConexion();
if (!$pdo) {
    fwrite(STDERR, "Sin conexión a la base de datos.\n");
    exit(1);
}

$r = recalcularInsignias($pdo);
echo date('Y-m-d H:i:s') . " — {$r['fichas']} fichas recalculadas · {$r['asignadas']} insignias nuevas · {$r['quitadas']} retiradas\n";
