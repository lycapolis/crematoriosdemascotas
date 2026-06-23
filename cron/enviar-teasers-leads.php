<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CRON — Teaser ofuscado de leads (batch)
 * ═══════════════════════════════════════════════════════════
 *
 * Recorre todos los crematorios con tier objetivo (00/01) y dispara
 * el envío del teaser ofuscado a quienes correspondan.
 *
 * Cada negocio recibe MÁXIMO 1 teaser cada TEASER_FRECUENCIA_DIAS (30 días).
 * El throttle es por crematorio individual — el cron puede correr
 * a diario sin riesgo de spam.
 *
 * ─── Uso ───────────────────────────────────────────────────
 *   php cron/enviar-teasers-leads.php           # envío real
 *   php cron/enviar-teasers-leads.php --dry-run # simulación, no marca ni envía
 *   php cron/enviar-teasers-leads.php --negocio=5 # solo un negocio
 *
 * ─── Cron Hostinger (hPanel) ───────────────────────────────
 *   Sugerido: 1x día, 09:00 hora España.
 *   Comando:
 *     /usr/bin/php /home/USUARIO/domains/crematoriosdemascotas.com/public_html/cron/enviar-teasers-leads.php
 *   Schedule: 0 9 * * *
 *
 *   El cron es defensivo: si las credenciales SMTP no están configuradas,
 *   loguea warning y sale sin spamear con mail() nativo (peligroso en producción).
 * ═══════════════════════════════════════════════════════════
 */

// Hardening: prohibir invocación HTTP — solo CLI
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por CLI.\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/conexion_db.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../includes/notificaciones.php';

// ─── Args ──────────────────────────────────────────────────
$dryRun     = in_array('--dry-run', $argv, true);
$negocioId  = 0;
foreach ($argv as $a) {
    if (preg_match('/^--negocio=(\d+)$/', $a, $m)) {
        $negocioId = (int)$m[1];
    }
}

echo "═══════════════════════════════════════\n";
echo "TEASER LEADS — " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════\n";
echo "Modo: " . ($dryRun ? 'DRY-RUN (no envía, no marca)' : 'REAL') . "\n";
if ($negocioId) echo "Filtro: solo crematorio #{$negocioId}\n";
echo "\n";

$pdo = obtenerConexion();
if (!$pdo) {
    echo "ERROR: sin conexión BD.\n";
    exit(1);
}

// Safety check: si no hay SMTP configurado y NO es dry-run, alertar
$smtpListo = defined('SMTP_HOST') && SMTP_HOST !== '' && defined('SMTP_USER') && SMTP_USER !== '';
if (!$smtpListo && !$dryRun) {
    echo "WARN: SMTP no configurado. Los emails irán por mail() nativo (riesgo de spam).\n";
    echo "      Configurar SMTP_HOST/USER/PASS en .env antes de correr en producción.\n\n";
}

// ─── Listar negocios candidatos ────────────────────────────
$tiersObjetivo = json_decode(defined('TEASER_TIERS_OBJETIVO') ? TEASER_TIERS_OBJETIVO : '[]', true) ?: [];
if (empty($tiersObjetivo)) {
    echo "ERROR: TEASER_TIERS_OBJETIVO vacío en config.php.\n";
    exit(1);
}

$placeholders = implode(',', array_fill(0, count($tiersObjetivo), '?'));
$sql = "SELECT id, nombre, tier
        FROM crematorios
        WHERE tier IN ($placeholders)
          AND recibe_notif_leads = 1
          AND activo = 1";
$params = $tiersObjetivo;
if ($negocioId) {
    $sql .= " AND id = ?";
    $params[] = $negocioId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Candidatos: " . count($candidatos) . "\n\n";

// ─── Recorrer y enviar ─────────────────────────────────────
$resumen = [
    'enviados'         => 0,
    'sin_leads_area'   => 0,
    'throttle'         => 0,
    'sin_email'        => 0,
    'opt_out'          => 0,
    'envio_fallido'    => 0,
    'otros'            => 0,
];

foreach ($candidatos as $crem) {
    $res = enviarTeaserLeadsArea($pdo, (int)$crem['id'], $dryRun);
    $linea = sprintf(
        "  #%-4d %-50s tier=%s → %s",
        $crem['id'],
        mb_substr($crem['nombre'], 0, 50),
        $crem['tier'],
        $res['ok'] ? '✓ ' . ($res['motivo'] ?? 'enviado') : '✗ ' . ($res['motivo'] ?? '?')
    );
    if (!empty($res['stats'])) {
        $linea .= " (leads área: " . (int)($res['stats']['total_leads'] ?? 0) . ")";
    }
    echo $linea . "\n";

    if ($res['ok'] && !$dryRun) {
        $resumen['enviados']++;
    } elseif ($res['ok'] && $dryRun) {
        $resumen['enviados']++; // contamos los que hubiesen salido
    } else {
        $motivo = $res['motivo'] ?? 'otros';
        if (isset($resumen[$motivo])) $resumen[$motivo]++;
        else $resumen['otros']++;
    }
}

// ─── Resumen ───────────────────────────────────────────────
echo "\n═══════════════════════════════════════\n";
echo "RESUMEN\n";
echo "═══════════════════════════════════════\n";
echo "  Enviados (o simulados): {$resumen['enviados']}\n";
echo "  Sin leads en área:      {$resumen['sin_leads_area']}\n";
echo "  Throttle (ya enviado):  {$resumen['throttle']}\n";
echo "  Sin email destino:      {$resumen['sin_email']}\n";
echo "  Opt-out:                {$resumen['opt_out']}\n";
echo "  Envío fallido:          {$resumen['envio_fallido']}\n";
echo "  Otros:                  {$resumen['otros']}\n";

exit(0);
