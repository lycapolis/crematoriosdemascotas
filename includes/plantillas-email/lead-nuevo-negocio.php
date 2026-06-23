<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Nuevo lead recibido"
 * ═══════════════════════════════════════════════════════════
 *
 * Renderiza la versión HTML + texto plano del email que recibe
 * un negocio cuando un usuario completa el modal de lead capture.
 *
 * Diseño:
 *   - Branded con paleta del sitio (terracota / marrón / crema)
 *   - HTML compatible con clientes de email (table-based, inline styles)
 *   - Modo completo vs parcial según tier del negocio
 *
 * Función exportada:
 *   renderEmailLeadNuevo(array $lead, array $negocio, bool $datosCompletos): array
 *     return: ['asunto' => string, 'html' => string, 'texto' => string]
 *
 * @param array $lead
 *   id, nombre, email, whatsapp_number, phone_code, ciudad,
 *   servicio, mascota_tamano, mensaje, channel_type, created_at
 *
 * @param array $negocio
 *   id, nombre, slug, tier, ciudad_nombre
 *
 * @param bool $datosCompletos
 *   true → muestra teléfono+email completos (tier alto)
 *   false → enmascara teléfono+email (tier verificado, urgencia upgrade)
 * ═══════════════════════════════════════════════════════════
 */

if (!function_exists('renderEmailLeadNuevo')) {

    function renderEmailLeadNuevo(array $lead, array $negocio, bool $datosCompletos = true): array
    {
        $sitio   = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';

        // ─── Datos del lead ──────────────────────────────────────
        $nombre   = (string)($lead['nombre']  ?? '');
        $email    = (string)($lead['email']   ?? '');
        $tel      = trim('+' . ($lead['phone_code'] ?? '') . ' ' . ($lead['whatsapp_number'] ?? ''));
        $ciudad   = (string)($lead['ciudad']  ?? '');
        $servicio = (string)($lead['servicio'] ?? '');
        $tamano   = (string)($lead['mascota_tamano'] ?? '');
        $mensaje  = (string)($lead['mensaje'] ?? '');
        $canal    = (string)($lead['channel_type'] ?? 'web');
        $fechaIso = (string)($lead['created_at'] ?? date('Y-m-d H:i:s'));

        // ─── Visibilidad de contacto según tier ──────────────────
        $emailVisible = $datosCompletos ? $email : enmascararEmail($email);
        $telVisible   = $datosCompletos ? $tel   : enmascararTelefono($tel);

        $canalLabels = [
            'tel'  => 'Click en "Llamar"',
            'wa'   => 'Click en WhatsApp',
            'maps' => 'Click en "Ver en mapa"',
            'web'  => 'Click en sitio web',
        ];
        $canalLabel = $canalLabels[$canal] ?? 'Formulario web';

        $asunto = "Nuevo lead — {$nombre}" . ($ciudad ? " ({$ciudad})" : '');

        // ─── HTML ────────────────────────────────────────────────
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

      <!-- TÍTULO -->
      <tr><td style="padding:32px 28px 8px 28px;">
        <p style="margin:0 0 4px 0;font-size:13px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Nuevo lead recibido</p>
        <h1 style="margin:0;font-size:24px;line-height:1.25;color:#2C2C2C;font-weight:700;">
          <?= htmlspecialchars($nombre) ?> te ha contactado
        </h1>
        <p style="margin:8px 0 0 0;font-size:14px;color:#5A3E2F;opacity:.7;">
          Desde <strong><?= htmlspecialchars($negocio['nombre'] ?? '') ?></strong> · <?= $canalLabel ?> · <?= date('d/m/Y H:i', strtotime($fechaIso)) ?>
        </p>
      </td></tr>

      <!-- DATOS DEL LEAD -->
      <tr><td style="padding:24px 28px 8px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F5EDE5;border-radius:8px;padding:18px 20px;">
          <?= filaDato('Mascota', htmlspecialchars($servicio . ($tamano ? ' · ' . $tamano : ''))) ?>
          <?php if ($ciudad): ?>
            <?= filaDato('Ciudad', htmlspecialchars($ciudad)) ?>
          <?php endif; ?>
          <?php if ($email): ?>
            <?= filaDato('Email', $datosCompletos
                ? '<a href="mailto:' . htmlspecialchars($email) . '" style="color:#B8704F;text-decoration:none;font-weight:600;">' . htmlspecialchars($email) . '</a>'
                : '<span style="color:#8B7765;">' . htmlspecialchars($emailVisible) . '</span> <span style="font-size:11px;color:#B8704F;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">· upgrade para ver</span>') ?>
          <?php endif; ?>
          <?php if (trim($tel)): ?>
            <?= filaDato('Teléfono', $datosCompletos
                ? '<a href="tel:' . preg_replace('/[^0-9+]/', '', $tel) . '" style="color:#B8704F;text-decoration:none;font-weight:600;">' . htmlspecialchars($tel) . '</a>'
                : '<span style="color:#8B7765;">' . htmlspecialchars($telVisible) . '</span> <span style="font-size:11px;color:#B8704F;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">· upgrade para ver</span>') ?>
          <?php endif; ?>
        </table>
      </td></tr>

      <?php if ($mensaje): ?>
      <!-- MENSAJE -->
      <tr><td style="padding:8px 28px 16px 28px;">
        <p style="margin:0 0 8px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Mensaje del cliente</p>
        <blockquote style="margin:0;padding:14px 18px;border-left:3px solid #B8704F;background:#FFFBF7;font-size:15px;line-height:1.55;color:#2C2C2C;font-style:italic;">
          <?= nl2br(htmlspecialchars($mensaje)) ?>
        </blockquote>
      </td></tr>
      <?php endif; ?>

      <!-- CTAs -->
      <?php if ($datosCompletos): ?>
      <tr><td style="padding:8px 28px 28px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <?php if ($email): ?>
            <td style="padding-right:10px;">
              <a href="mailto:<?= htmlspecialchars($email) ?>" style="display:inline-block;background:#B8704F;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;font-size:15px;">Responder por email</a>
            </td>
            <?php endif; ?>
            <?php if (trim($tel)): $telLimpio = preg_replace('/[^0-9+]/', '', $tel); ?>
            <td style="padding-right:10px;">
              <a href="tel:<?= $telLimpio ?>" style="display:inline-block;background:#5A3E2F;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;font-size:15px;">Llamar</a>
            </td>
            <td>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $telLimpio) ?>" style="display:inline-block;background:#25D366;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;font-size:15px;">WhatsApp</a>
            </td>
            <?php endif; ?>
          </tr>
        </table>
      </td></tr>
      <?php else: ?>
      <!-- Banner de upgrade -->
      <tr><td style="padding:8px 28px 28px 28px;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:linear-gradient(135deg,#B8704F 0%,#A05F40 100%);border-radius:8px;padding:18px 20px;">
          <tr><td style="color:#fff;">
            <p style="margin:0 0 6px 0;font-size:14px;font-weight:700;">Desbloquea el contacto completo</p>
            <p style="margin:0;font-size:13px;line-height:1.5;opacity:.95;">
              Tu plan actual recibe leads con contacto enmascarado. Mejora a <strong>Destacado</strong> y recibe el teléfono y email completos al instante.
            </p>
          </td></tr>
        </table>
      </td></tr>
      <?php endif; ?>

      <!-- FOOTER -->
      <tr><td style="background:#F5EDE5;padding:18px 28px;border-top:1px solid rgba(184,112,79,.15);">
        <p style="margin:0;font-size:12px;color:#5A3E2F;opacity:.7;line-height:1.5;">
          Recibes este email porque tu negocio está activo en <?= htmlspecialchars($sitio) ?>.<br>
          Si no quieres recibir más notificaciones de leads, contesta a este mensaje con la palabra <strong>BAJA</strong> o desactívalo en tu panel de negocio.
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
        $texto .= "NUEVO LEAD — {$sitio}\n";
        $texto .= "═══════════════════════════════════════\n\n";
        $texto .= "Negocio:  " . ($negocio['nombre'] ?? '') . "\n";
        $texto .= "Origen:   {$canalLabel}\n";
        $texto .= "Fecha:    " . date('d/m/Y H:i', strtotime($fechaIso)) . "\n\n";
        $texto .= "─── Datos del cliente ───\n";
        $texto .= "Nombre:   {$nombre}\n";
        if ($ciudad)         $texto .= "Ciudad:   {$ciudad}\n";
        if ($servicio)       $texto .= "Mascota:  {$servicio}" . ($tamano ? " · {$tamano}" : '') . "\n";
        if ($email)          $texto .= "Email:    {$emailVisible}\n";
        if (trim($tel))      $texto .= "Tel:      {$telVisible}\n";
        if ($mensaje) {
            $texto .= "\nMensaje:\n";
            $texto .= "  " . str_replace("\n", "\n  ", trim($mensaje)) . "\n";
        }
        if (!$datosCompletos) {
            $texto .= "\n[Tu plan actual recibe contacto enmascarado.\n";
            $texto .= " Mejora a Destacado para ver email y teléfono completos.]\n";
        }
        $texto .= "\n─────────────────────────────────────\n";
        $texto .= "Recibes este email porque tu negocio está activo en {$sitio}.\n";
        $texto .= "Para darte de baja, responde con la palabra BAJA.\n";

        return [
            'asunto' => $asunto,
            'html'   => $html,
            'texto'  => $texto,
        ];
    }

    // ─── Helpers internos ────────────────────────────────────────

    function filaDato(string $label, string $valorHtml): string
    {
        return '<tr>
            <td style="padding:6px 0;font-size:12px;color:#8B7765;text-transform:uppercase;letter-spacing:.06em;font-weight:600;width:90px;vertical-align:top;">' . htmlspecialchars($label) . '</td>
            <td style="padding:6px 0;font-size:15px;color:#2C2C2C;vertical-align:top;">' . $valorHtml . '</td>
        </tr>';
    }

    function enmascararEmail(string $email): string
    {
        if (!str_contains($email, '@')) return '';
        [$user, $dom] = explode('@', $email, 2);
        $userMasked = strlen($user) <= 2
            ? str_repeat('•', strlen($user))
            : substr($user, 0, 1) . str_repeat('•', max(strlen($user) - 2, 1)) . substr($user, -1);
        return $userMasked . '@' . $dom;
    }

    function enmascararTelefono(string $tel): string
    {
        $digits = preg_replace('/\D/', '', $tel);
        if (strlen($digits) < 6) return str_repeat('•', strlen($digits));
        return substr($digits, 0, 3) . ' ••• ••' . substr($digits, -2);
    }
}
