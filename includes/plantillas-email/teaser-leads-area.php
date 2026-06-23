<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — Teaser ofuscado de leads en el área
 * ═══════════════════════════════════════════════════════════
 *
 * Email a negocios SIN tier de pago, mostrando que hay actividad
 * de leads en su zona pero con datos enmascarados. Palanca de upsell.
 *
 * Función exportada:
 *   renderEmailTeaserLeadsArea(array $negocio, array $stats): array
 *     return: ['asunto' => string, 'html' => string, 'texto' => string]
 *
 * @param array $negocio  ['id','nombre','ciudad','provincia_nombre']
 * @param array $stats    [
 *   'total_leads'    => int,                       // total en el período
 *   'periodo_dias'   => int,                       // ventana (típicamente 30)
 *   'por_ciudad'     => [['ciudad'=>'X','count'=>3], ...],
 *   'por_servicio'   => ['Perro'=>5, 'Gato'=>2, ...],
 *   'leads_mockup'   => [['ciudad'=>'X','servicio'=>'Perro','tamano'=>'5-15 kg'], ...],
 *     // 3-5 leads reales con SOLO ciudad+servicio+tamaño (sin nombre/email/tel)
 * ]
 * ═══════════════════════════════════════════════════════════
 */

if (!function_exists('renderEmailTeaserLeadsArea')) {

    function renderEmailTeaserLeadsArea(array $negocio, array $stats): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';

        $negNombre   = (string)($negocio['nombre'] ?? 'tu negocio');
        $negCiudad   = (string)($negocio['ciudad'] ?? '');
        $negProv     = (string)($negocio['provincia_nombre'] ?? '');
        $area        = $negCiudad ?: $negProv ?: 'tu zona';

        $total    = (int)($stats['total_leads'] ?? 0);
        $dias     = (int)($stats['periodo_dias'] ?? 30);
        $porCiudad   = $stats['por_ciudad']   ?? [];
        $porServicio = $stats['por_servicio'] ?? [];
        $leadsMockup = $stats['leads_mockup'] ?? [];

        $asunto = "Hubo {$total} consult" . ($total === 1 ? 'a' : 'as') . " en {$area} este mes";

        ob_start(); ?><!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($asunto) ?></title>
</head>
<body style="margin:0;padding:0;background:#FFFBF7;font-family:'Helvetica Neue',Arial,sans-serif;color:#2C2C2C;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#FFFBF7;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(90,62,47,.08);">

      <!-- HEADER -->
      <tr><td style="background:#5A3E2F;padding:24px 28px;text-align:left;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="font-size:28px;color:#B8704F;line-height:1;padding-right:10px;">🐾</td>
            <td style="font-size:18px;font-weight:700;color:#FFFBF7;letter-spacing:-.01em;"><?= htmlspecialchars($sitio) ?></td>
          </tr>
        </table>
      </td></tr>

      <!-- TÍTULO + número grande -->
      <tr><td style="padding:36px 28px 16px 28px;text-align:center;">
        <p style="margin:0 0 8px 0;font-size:13px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Reporte de actividad · últimos <?= $dias ?> días</p>
        <div style="font-size:64px;font-weight:800;color:#B8704F;line-height:1;margin-bottom:6px;letter-spacing:-.03em;">
          <?= $total ?>
        </div>
        <p style="margin:0;font-size:18px;color:#2C2C2C;line-height:1.4;">
          consult<?= $total === 1 ? 'a' : 'as' ?> en <strong><?= htmlspecialchars($area) ?></strong>
        </p>
        <?php if ($negProv && $negCiudad && $negCiudad !== $negProv): ?>
        <p style="margin:4px 0 0 0;font-size:13px;color:#8B7765;">y alrededores de <?= htmlspecialchars($negProv) ?></p>
        <?php endif; ?>
      </td></tr>

      <!-- Desglose por ciudad -->
      <?php if (!empty($porCiudad)): ?>
      <tr><td style="padding:8px 28px 12px 28px;">
        <p style="margin:0 0 10px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Distribución geográfica</p>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F5EDE5;border-radius:8px;padding:14px 18px;">
          <?php foreach ($porCiudad as $c):
              $cnt = (int)($c['count'] ?? 0);
              if ($cnt < 1) continue;
          ?>
          <tr>
            <td style="padding:5px 0;font-size:15px;color:#2C2C2C;"><?= htmlspecialchars($c['ciudad'] ?? '?') ?></td>
            <td style="padding:5px 0;font-size:15px;color:#B8704F;font-weight:700;text-align:right;width:60px;"><?= $cnt ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      </td></tr>
      <?php endif; ?>

      <!-- Desglose por servicio (chips) -->
      <?php if (!empty($porServicio)): ?>
      <tr><td style="padding:4px 28px 12px 28px;">
        <p style="margin:0 0 10px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Tipo de mascota</p>
        <div style="font-size:14px;">
          <?php foreach ($porServicio as $svc => $cnt): if ((int)$cnt < 1) continue; ?>
          <span style="display:inline-block;background:#F5EDE5;border:1px solid rgba(184,112,79,.2);border-radius:999px;padding:6px 12px;margin:2px 4px 2px 0;font-size:13px;color:#2C2C2C;">
            <?= htmlspecialchars($svc) ?> · <strong style="color:#B8704F;"><?= (int)$cnt ?></strong>
          </span>
          <?php endforeach; ?>
        </div>
      </td></tr>
      <?php endif; ?>

      <!-- Mockup de leads ofuscados -->
      <?php if (!empty($leadsMockup)): ?>
      <tr><td style="padding:16px 28px 8px 28px;">
        <p style="margin:0 0 10px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Así se ven los leads (datos enmascarados)</p>
        <?php foreach ($leadsMockup as $lm): ?>
        <div style="background:#FFFBF7;border:1px solid rgba(184,112,79,.15);border-radius:8px;padding:12px 16px;margin-bottom:8px;position:relative;">
          <div style="font-size:14px;color:#2C2C2C;line-height:1.5;">
            <strong style="color:#5A3E2F;">M••••• G•••••</strong> · <?= htmlspecialchars($lm['ciudad'] ?? '?') ?> · <?= htmlspecialchars($lm['servicio'] ?? '?') ?><?= !empty($lm['tamano']) ? ' · ' . htmlspecialchars($lm['tamano']) : '' ?>
          </div>
          <div style="font-size:13px;color:#8B7765;margin-top:4px;line-height:1.5;">
            📧 m••••@•••••.com &nbsp;·&nbsp; 📱 6•• ••• •23
          </div>
        </div>
        <?php endforeach; ?>
        <p style="margin:8px 0 0 0;font-size:12px;color:#8B7765;font-style:italic;text-align:center;">
          Los datos completos se entregan solo a negocios con plan Verificado o superior.
        </p>
      </td></tr>
      <?php endif; ?>

      <!-- CTA upgrade -->
      <tr><td style="padding:20px 28px 28px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:linear-gradient(135deg,#B8704F 0%,#A05F40 100%);border-radius:10px;padding:24px 22px;">
          <tr><td style="color:#fff;text-align:center;">
            <p style="margin:0 0 8px 0;font-size:13px;text-transform:uppercase;letter-spacing:.08em;opacity:.85;">Recibe estos leads en tu email</p>
            <p style="margin:0 0 16px 0;font-size:22px;font-weight:700;line-height:1.3;">
              Cada consulta es un cliente que necesita tus servicios
            </p>
            <p style="margin:0 0 18px 0;font-size:14px;opacity:.95;line-height:1.5;">
              Mejora <strong><?= htmlspecialchars($negNombre) ?></strong> a <strong>Verificado</strong> y recibe en tiempo real el contacto completo de cada lead en <?= htmlspecialchars($area) ?>.
            </p>
            <a href="mailto:<?= defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'info@crematoriosdemascotas.com' ?>?subject=Quiero%20mejorar%20mi%20plan&body=Hola%2C%20me%20interesa%20mejorar%20el%20plan%20de%20<?= rawurlencode($negNombre) ?>"
               style="display:inline-block;background:#fff;color:#B8704F;text-decoration:none;padding:14px 28px;border-radius:6px;font-weight:700;font-size:15px;letter-spacing:.02em;">
              Quiero mejorar mi plan →
            </a>
          </td></tr>
        </table>
      </td></tr>

      <!-- FOOTER -->
      <tr><td style="background:#F5EDE5;padding:18px 28px;border-top:1px solid rgba(184,112,79,.15);">
        <p style="margin:0;font-size:12px;color:#5A3E2F;opacity:.7;line-height:1.5;">
          Recibes este reporte porque tu negocio aparece en <?= htmlspecialchars($sitio) ?>.<br>
          Si no quieres recibir más reportes de actividad, contesta a este mensaje con la palabra <strong>BAJA</strong>.
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html><?php
        $html = ob_get_clean();

        // ─── Texto plano ─────────────────────────────────────────
        $texto  = "═══════════════════════════════════════\n";
        $texto .= "REPORTE DE ACTIVIDAD — {$sitio}\n";
        $texto .= "═══════════════════════════════════════\n\n";
        $texto .= "Hubo {$total} consulta" . ($total === 1 ? '' : 's') . " en {$area} los últimos {$dias} días.\n\n";

        if (!empty($porCiudad)) {
            $texto .= "─── Distribución por ciudad ───\n";
            foreach ($porCiudad as $c) {
                $texto .= "  · " . ($c['ciudad'] ?? '?') . ": " . (int)($c['count'] ?? 0) . "\n";
            }
            $texto .= "\n";
        }
        if (!empty($porServicio)) {
            $texto .= "─── Por tipo de mascota ───\n";
            foreach ($porServicio as $svc => $cnt) {
                if ((int)$cnt < 1) continue;
                $texto .= "  · {$svc}: " . (int)$cnt . "\n";
            }
            $texto .= "\n";
        }
        if (!empty($leadsMockup)) {
            $texto .= "─── Leads (datos enmascarados) ───\n";
            foreach ($leadsMockup as $lm) {
                $texto .= "  M••••• G••••• · " . ($lm['ciudad'] ?? '?') . " · " . ($lm['servicio'] ?? '?') . "\n";
                $texto .= "  m••••@•••••.com · 6•• ••• •23\n\n";
            }
        }

        $texto .= "─────────────────────────────────────\n";
        $texto .= "Mejora a Verificado y recibe los datos completos.\n";
        $texto .= "Responde a este email con \"PLAN\" o llama al equipo.\n\n";
        $texto .= "Para darte de baja, responde con BAJA.\n";

        return [
            'asunto' => $asunto,
            'html'   => $html,
            'texto'  => $texto,
        ];
    }
}
