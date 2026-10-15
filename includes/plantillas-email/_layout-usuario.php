<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LAYOUT COMÚN — emails transaccionales al USUARIO
 * ═══════════════════════════════════════════════════════════
 *
 * Mismo look que lead-nuevo-negocio.php (terracota / marrón / crema),
 * pero sobrio: una columna, sin imágenes, un único botón opcional.
 *
 *   renderEmailLayoutUsuario(array $p): array{html:string, texto:string}
 *
 *   $p = [
 *     'asunto'     => string,
 *     'preheader'  => string,          // texto de vista previa en la bandeja
 *     'saludo'     => string,          // "Hola, María:"
 *     'parrafos'   => string[],        // texto plano; se escapa aquí
 *     'cita'       => ['autor'=>..., 'estrellas'=>int, 'texto'=>...] | null,  // reseña citada (opcional)
 *     'cta'        => ['texto'=>..., 'url'=>...] | null,
 *     'parrafos_despues' => string[],   // texto tras el botón principal (opcional)
 *     'cta2'       => ['texto'=>..., 'url'=>...] | null,   // botón secundario (opcional)
 *     'pie_motivo' => string,          // por qué recibe el email
 *     'url_baja'   => string,
 *     'texto_baja' => string,          // opcional; por defecto «Dejar de recibir estas notificaciones»
 *   ]
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_cabecera.php';

if (!function_exists('renderEmailLayoutUsuario')) {

    function renderEmailLayoutUsuario(array $p): array
    {
        $sitio      = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e          = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $urlPriv    = function_exists('notifUrlPublica') ? notifUrlPublica('privacidad.php') : '';
        $parrafos   = $p['parrafos'] ?? [];
        $cta        = $p['cta'] ?? null;
        $cita       = $p['cita'] ?? null;
        $despues    = $p['parrafos_despues'] ?? [];
        $cta2       = $p['cta2'] ?? null;
        $textoBaja  = (string)($p['texto_baja'] ?? 'Dejar de recibir estas notificaciones');

        ob_start(); ?><!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($p['asunto']) ?></title>
</head>
<body style="margin:0;padding:0;background:#FFFBF7;font-family:'Helvetica Neue',Arial,sans-serif;color:#2C2C2C;-webkit-font-smoothing:antialiased;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;font-size:1px;line-height:1px;color:#FFFBF7;"><?= $e($p['preheader'] ?? '') ?></div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#FFFBF7;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(90,62,47,.08);">

      <tr><td style="background:#5A3E2F;padding:12px 18px;text-align:left;">
        <?= emailCabeceraLogo() ?>
      </td></tr>

      <tr><td style="padding:32px 28px 8px 28px;font-size:16px;line-height:1.6;color:#2C2C2C;">
        <p style="margin:0 0 16px 0;"><?= $e($p['saludo'] ?? '') ?></p>
        <?php foreach ($parrafos as $par): ?>
        <p style="margin:0 0 16px 0;"><?= $par /* ya viene con el HTML permitido preparado por la plantilla */ ?></p>
        <?php endforeach; ?>
      </td></tr>

      <?php if ($cita): ?>
      <tr><td style="padding:0 28px 16px 28px;">
        <p style="margin:0 0 8px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;"><?= $e($cita['autor']) ?><?php if ((int)$cita['estrellas'] > 0): ?> · <span style="color:#B8704F;letter-spacing:.1em;"><?= $e(emailEstrellas((int)$cita['estrellas'])) ?></span><?php endif; ?></p>
        <blockquote style="margin:0;padding:14px 18px;border-left:3px solid #B8704F;background:#FFFBF7;font-size:15px;line-height:1.55;color:#2C2C2C;font-style:italic;"><?= nl2br($e($cita['texto'])) ?></blockquote>
      </td></tr>
      <?php endif; ?>

      <?php if ($cta): ?>
      <tr><td style="padding:4px 28px 24px 28px;">
        <a href="<?= $e($cta['url']) ?>" style="display:inline-block;background:#B8704F;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;font-size:15px;"><?= $e($cta['texto']) ?></a>
      </td></tr>
      <?php endif; ?>

      <?php if ($despues): ?>
      <tr><td style="padding:0 28px 8px 28px;font-size:16px;line-height:1.6;color:#2C2C2C;">
        <?php foreach ($despues as $par): ?>
        <p style="margin:0 0 16px 0;"><?= $par ?></p>
        <?php endforeach; ?>
      </td></tr>
      <?php endif; ?>

      <?php if ($cta2): ?>
      <tr><td style="padding:0 28px 24px 28px;">
        <a href="<?= $e($cta2['url']) ?>" style="display:inline-block;background:#fff;color:#B8704F;text-decoration:none;padding:11px 22px;border-radius:6px;font-weight:600;font-size:15px;border:1px solid #B8704F;"><?= $e($cta2['texto']) ?></a>
      </td></tr>
      <?php endif; ?>

      <tr><td style="padding:0 28px 28px 28px;font-size:16px;line-height:1.6;color:#2C2C2C;">
        <p style="margin:0;">Gracias,<br><strong>El equipo de <?= $e($sitio) ?></strong></p>
      </td></tr>

      <tr><td style="background:#F5EDE5;padding:18px 28px;border-top:1px solid rgba(184,112,79,.15);">
        <p style="margin:0;font-size:12px;color:#5A3E2F;opacity:.8;line-height:1.6;">
          <?= $e($p['pie_motivo']) ?><br>
          <a href="<?= $e($p['url_baja']) ?>" style="color:#5A3E2F;"><?= $e($textoBaja) ?></a>
          <?php if ($urlPriv): ?> · <a href="<?= $e($urlPriv) ?>" style="color:#5A3E2F;">Política de privacidad</a><?php endif; ?>
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html><?php
        $html = ob_get_clean();

        // ─── Texto plano ─────────────────────────────────────
        $texto  = ($p['saludo'] ?? '') . "\n\n";
        foreach ($parrafos as $par) {
            $texto .= trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $par)), ENT_QUOTES, 'UTF-8')) . "\n\n";
        }
        if ($cita) {
            $texto .= $cita['autor'] . ((int)$cita['estrellas'] > 0 ? ' · ' . emailEstrellas((int)$cita['estrellas']) : '') . "\n  "
                    . str_replace("\n", "\n  ", trim($cita['texto'])) . "\n\n";
        }
        if ($cta) {
            $texto .= $cta['texto'] . ': ' . $cta['url'] . "\n\n";
        }
        foreach ($despues as $par) {
            $texto .= trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $par)), ENT_QUOTES, 'UTF-8')) . "\n\n";
        }
        if ($cta2) {
            $texto .= $cta2['texto'] . ': ' . $cta2['url'] . "\n\n";
        }
        $texto .= "Gracias,\nEl equipo de {$sitio}\n\n";
        $texto .= "────────────────────────────\n";
        $texto .= $p['pie_motivo'] . "\n";
        $texto .= $textoBaja . ': ' . $p['url_baja'] . "\n";
        if ($urlPriv) $texto .= 'Política de privacidad: ' . $urlPriv . "\n";

        return ['html' => $html, 'texto' => $texto];
    }

    /** ★★★★☆ en texto (sin imágenes) */
    function emailEstrellas(int $n): string
    {
        $n = max(0, min(5, $n));
        return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    }
}
