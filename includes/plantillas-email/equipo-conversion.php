<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — aviso INTERNO al equipo por una conversión
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailEquipoConversion(array $p): array{asunto,html,texto}
 *
 *   $p = [
 *     'tipo'      => string,                       // «Nueva reseña», «Mensaje de contacto»…
 *     'resumen'   => string,                       // una línea bajo el título (quién / qué negocio)
 *     'filas'     => array<string,string>,         // etiqueta => valor (tabla)
 *     'bloques'   => array<string,string>,         // etiqueta => texto largo (mensaje, descripción…)
 *     'cta'       => ['texto'=>..., 'url'=>...]|null,    // normalmente «Ver en el admin»
 *     'cta2'      => ['texto'=>..., 'url'=>...]|null,    // normalmente «Responder» (mailto)
 *     'origen'    => string,                       // URL de la página desde la que se envió (opcional)
 *   ]
 *
 * Mismo look que el resto de emails (terracota / marrón / crema, 600 px, logo en cabecera),
 * pero denso y sin pie de baja: es un aviso interno, no una comunicación al usuario.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_cabecera.php';

if (!function_exists('renderEmailEquipoConversion')) {

    function renderEmailEquipoConversion(array $p): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $tipo   = (string)($p['tipo'] ?? 'Nueva conversión');
        $filas  = array_filter($p['filas'] ?? [], fn($v) => trim((string)$v) !== '');
        $bloques = array_filter($p['bloques'] ?? [], fn($v) => trim((string)$v) !== '');
        $cta    = $p['cta'] ?? null;
        $cta2   = $p['cta2'] ?? null;
        $asunto = $tipo . (!empty($p['resumen']) ? ' · ' . $p['resumen'] : '');

        ob_start(); ?><!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($asunto) ?></title>
</head>
<body style="margin:0;padding:0;background:#FFFBF7;font-family:'Helvetica Neue',Arial,sans-serif;color:#2C2C2C;-webkit-font-smoothing:antialiased;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#FFFBF7;"><?= $e($p['resumen'] ?? '') ?></div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#FFFBF7;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(90,62,47,.08);">

      <tr><td style="background:#5A3E2F;padding:12px 18px;text-align:left;">
        <?= emailCabeceraLogo() ?>
      </td></tr>

      <tr><td style="padding:28px 28px 8px 28px;">
        <p style="margin:0 0 6px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.08em;font-weight:600;">Aviso interno · conversión</p>
        <h1 style="margin:0 0 6px 0;font-size:22px;line-height:1.3;color:#5A3E2F;"><?= $e($tipo) ?></h1>
        <?php if (!empty($p['resumen'])): ?>
        <p style="margin:0;font-size:16px;line-height:1.5;color:#2C2C2C;"><?= $e($p['resumen']) ?></p>
        <?php endif; ?>
      </td></tr>

      <?php if ($filas): ?>
      <tr><td style="padding:16px 28px 8px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-top:1px solid #F0E4D8;">
          <?php foreach ($filas as $etq => $val): ?>
          <tr>
            <td valign="top" style="padding:9px 12px 9px 0;border-bottom:1px solid #F0E4D8;font-size:13px;color:#8B7765;width:34%;"><?= $e($etq) ?></td>
            <td valign="top" style="padding:9px 0;border-bottom:1px solid #F0E4D8;font-size:15px;color:#2C2C2C;word-break:break-word;"><?= $e($val) ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </td></tr>
      <?php endif; ?>

      <?php foreach ($bloques as $etq => $val): ?>
      <tr><td style="padding:12px 28px 4px 28px;">
        <p style="margin:0 0 6px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;"><?= $e($etq) ?></p>
        <div style="padding:12px 16px;border-left:3px solid #B8704F;background:#FFFBF7;font-size:15px;line-height:1.55;color:#2C2C2C;"><?= nl2br($e($val)) ?></div>
      </td></tr>
      <?php endforeach; ?>

      <?php if ($cta || $cta2): ?>
      <tr><td style="padding:20px 28px 8px 28px;">
        <?php if ($cta): ?>
        <a href="<?= $e($cta['url']) ?>" style="display:inline-block;background:#B8704F;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;font-size:15px;margin:0 8px 8px 0;"><?= $e($cta['texto']) ?></a>
        <?php endif; ?>
        <?php if ($cta2): ?>
        <a href="<?= $e($cta2['url']) ?>" style="display:inline-block;background:#fff;color:#B8704F;text-decoration:none;padding:11px 22px;border-radius:6px;font-weight:600;font-size:15px;border:1px solid #B8704F;margin:0 8px 8px 0;"><?= $e($cta2['texto']) ?></a>
        <?php endif; ?>
      </td></tr>
      <?php endif; ?>

      <tr><td style="background:#F5EDE5;padding:16px 28px;border-top:1px solid rgba(184,112,79,.15);margin-top:16px;">
        <p style="margin:0;font-size:12px;color:#5A3E2F;opacity:.85;line-height:1.6;">
          Aviso interno de <?= $e($sitio) ?>. No lo reenvíes fuera del equipo: contiene datos personales.
          <?php if (!empty($p['origen'])): ?><br>Enviado desde: <?= $e($p['origen']) ?><?php endif; ?>
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html><?php
        $html = ob_get_clean();

        // ─── Texto plano ─────────────────────────────────────
        $texto = mb_strtoupper($tipo) . "\n";
        if (!empty($p['resumen'])) $texto .= $p['resumen'] . "\n";
        $texto .= str_repeat('─', 40) . "\n";
        foreach ($filas as $etq => $val) {
            $texto .= $etq . ': ' . $val . "\n";
        }
        foreach ($bloques as $etq => $val) {
            $texto .= "\n" . $etq . ":\n" . trim($val) . "\n";
        }
        $texto .= "\n";
        if ($cta)  $texto .= $cta['texto'] . ': ' . $cta['url'] . "\n";
        if ($cta2) $texto .= $cta2['texto'] . ': ' . $cta2['url'] . "\n";
        $texto .= str_repeat('─', 40) . "\nAviso interno de {$sitio}. No lo reenvíes fuera del equipo.\n";
        if (!empty($p['origen'])) $texto .= 'Enviado desde: ' . $p['origen'] . "\n";

        return ['asunto' => $asunto, 'html' => $html, 'texto' => $texto];
    }
}
