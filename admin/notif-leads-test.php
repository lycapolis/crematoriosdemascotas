<?php
/**
 * ═══════════════════════════════════════════════════════════
 * NOTIF LEADS TEST — herramienta de diagnóstico
 * ═══════════════════════════════════════════════════════════
 *
 * Permite forzar el reenvío de la notificación de un lead específico
 * o enviar un lead "dummy" a un email para previsualizar la plantilla.
 *
 * Útil mientras:
 *   - No hay credenciales SMTP configuradas (verás fallback mail() o error)
 *   - Quieres ver cómo se renderiza el HTML antes de tener leads reales
 * ═══════════════════════════════════════════════════════════
 */

require_once '../includes/config.php';
require_once '../includes/conexion_db.php';
require_once '../includes/funciones.php';
require_once 'auth.php';
require_once '../includes/notificaciones.php';

requierePermiso('solicitudes');

$titulo_pagina = 'Test notificaciones de leads';
$pdo = obtenerConexion();

$resultado     = null;
$preview       = null;
$leadIdInput   = (int)($_POST['lead_id'] ?? 0);
$emailDummy    = trim($_POST['email_dummy'] ?? '');
$accion        = $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'reenviar' && $leadIdInput > 0) {
    // Reset del flag para permitir reenvío
    $pdo->prepare("UPDATE leads_b2c SET negocio_notificado = 0, negocio_notificado_at = NULL WHERE id = ?")
        ->execute([$leadIdInput]);
    $resultado = notificarNegocioLead($pdo, $leadIdInput);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'teaser_preview') {
    $cremId  = (int)($_POST['crem_id'] ?? 0);
    $emailTo = trim($_POST['teaser_email'] ?? '');
    if ($cremId > 0 && $emailTo) {
        // Forzamos override del email destino (no usamos el del negocio)
        $res = enviarTeaserLeadsArea($pdo, $cremId, false, $emailTo);
        $resultado = $res;
    } else {
        $resultado = ['ok' => false, 'error' => 'Faltan datos (crematorio + email destino).'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'teaser_dryrun') {
    $cremId = (int)($_POST['crem_id'] ?? 0);
    if ($cremId > 0) {
        $res = enviarTeaserLeadsArea($pdo, $cremId, true);
        $resultado = $res;
        if (!empty($res['preview'])) $preview = $res['preview'];
    } else {
        $resultado = ['ok' => false, 'error' => 'Falta crematorio.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'preview' && $emailDummy) {
    // Genera un lead de muestra (sin guardar en BD) y envía a $emailDummy
    require_once '../includes/mailer.php';
    require_once '../includes/plantillas-email/lead-nuevo-negocio.php';

    $leadDummy = [
        'id' => 0, 'nombre' => 'Maria García', 'email' => 'maria@example.com',
        'phone_code' => '34', 'whatsapp_number' => '600123456',
        'ciudad' => 'Madrid', 'servicio' => 'Perro', 'mascota_tamano' => '5 - 15 kg',
        'mensaje' => "Buenas tardes. Mi perro Toby falleció esta mañana y necesitamos coordinar el servicio para hoy mismo si fuera posible.\n\nGracias.",
        'channel_type' => 'wa', 'created_at' => date('Y-m-d H:i:s'),
    ];
    $negocioDummy = [
        'id' => 0, 'nombre' => 'Crematorio Ejemplo', 'slug' => 'ejemplo', 'tier' => '03'
    ];
    $completos = !empty($_POST['datos_completos']);
    $tpl  = renderEmailLeadNuevo($leadDummy, $negocioDummy, $completos);
    $envio = enviarMailHtml($emailDummy, '[PREVIEW] ' . $tpl['asunto'], $tpl['html'], $tpl['texto']);
    $resultado = $envio;
    $preview   = $tpl;
}

include 'header.php';
?>
<main class="admin-main">
  <div style="max-width:900px;margin:0 auto;padding:var(--espacio-cuatro);">
    <h1 style="margin:0 0 var(--espacio-dos) 0;">Test notificaciones de leads</h1>
    <p style="color:var(--admin-text-suave); margin-bottom:var(--espacio-cinco);">Forzar reenvío de notificación a un lead existente o enviar una preview con datos dummy.</p>

    <?php if ($resultado): ?>
      <?php if ($resultado['ok']): ?>
        <div class="admin-banner admin-banner--exito" style="margin-bottom:var(--espacio-cuatro);">
          ✓ Envío correcto. Transporte: <strong><?= htmlspecialchars($resultado['transporte'] ?? '?') ?></strong>
        </div>
      <?php else: ?>
        <div class="admin-banner admin-banner--error" style="margin-bottom:var(--espacio-cuatro);">
          ✗ <?= htmlspecialchars($resultado['motivo'] ?? $resultado['error'] ?? 'error desconocido') ?>
          <?php if (!empty($resultado['transporte'])): ?> · transporte: <?= htmlspecialchars($resultado['transporte']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <section class="ficha-card" style="margin-bottom:var(--espacio-cinco);">
      <h2 class="ficha-card__title">1) Reenviar notificación a un lead real</h2>
      <form method="post">
        <input type="hidden" name="accion" value="reenviar">
        <div style="display:flex; gap:var(--espacio-tres); align-items:flex-end;">
          <div class="field" style="flex:1; margin-bottom:0;">
            <label class="field__label" for="lead_id">ID del lead</label>
            <input type="number" id="lead_id" name="lead_id" class="field__input" required min="1">
            <span class="field__hint">Resetea negocio_notificado y dispara notificarNegocioLead()</span>
          </div>
          <button type="submit" class="boton uno">Reenviar</button>
        </div>
      </form>
    </section>

    <section class="ficha-card">
      <h2 class="ficha-card__title">2) Preview con datos dummy</h2>
      <form method="post">
        <input type="hidden" name="accion" value="preview">
        <div class="field">
          <label class="field__label" for="email_dummy">Enviar a este email</label>
          <input type="email" id="email_dummy" name="email_dummy" class="field__input" required placeholder="tu@email.com">
        </div>
        <label style="display:flex; align-items:center; gap:.5rem; margin-bottom:var(--espacio-tres);">
          <input type="checkbox" name="datos_completos" value="1" checked>
          <span>Datos completos (plan alto). Sin marcar = enmascarado.</span>
        </label>
        <button type="submit" class="boton uno">Enviar preview</button>
      </form>
    </section>

    <?php
    // Cargar lista de crematorios sin tier de pago para los selects de teaser
    $cremsSinTier = $pdo->query("
        SELECT c.id, c.nombre, c.tier, c.ciudad, p.nombre AS provincia_nombre
        FROM crematorios c
        LEFT JOIN provincias p ON c.provincia_id = p.id
        WHERE c.activo = 1
        ORDER BY c.tier ASC, c.nombre ASC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <section class="ficha-card" style="margin-top:var(--espacio-cinco);">
      <h2 class="ficha-card__title">3) Teaser ofuscado — preview / dry-run</h2>
      <p style="color:var(--admin-text-suave); margin-bottom:var(--espacio-tres); font-size:.9rem;">
        Email mensual a negocios SIN plan de pago con estadísticas de leads en su área. Datos personales enmascarados.
      </p>
      <form method="post">
        <div class="field">
          <label class="field__label" for="crem_id">Negocio</label>
          <select id="crem_id" name="crem_id" class="field__select" required>
            <option value="">— Elegí un negocio —</option>
            <?php foreach ($cremsSinTier as $c): ?>
            <option value="<?= (int)$c['id'] ?>">
              [<?= htmlspecialchars($c['tier']) ?>] <?= htmlspecialchars($c['nombre']) ?> — <?= htmlspecialchars($c['ciudad'] ?? '?') ?>, <?= htmlspecialchars($c['provincia_nombre'] ?? '?') ?>
            </option>
            <?php endforeach; ?>
          </select>
          <span class="field__hint">Solo planes <code>00</code> y <code>01</code> reciben el teaser real. Acá ves todos para testear.</span>
        </div>

        <div style="display:flex; gap:var(--espacio-tres); margin-bottom:var(--espacio-tres);">
          <button type="submit" name="accion" value="teaser_dryrun" class="boton dos">
            <i data-lucide="eye" class="icono"></i> Dry-run (preview HTML)
          </button>
        </div>

        <div class="field">
          <label class="field__label" for="teaser_email">Email destino (override)</label>
          <input type="email" id="teaser_email" name="teaser_email" class="field__input" placeholder="tu@email.com">
          <span class="field__hint">Si completás el email + clic en "Enviar real", se manda a este email (no al del negocio).</span>
        </div>
        <button type="submit" name="accion" value="teaser_preview" class="boton uno">
          <i data-lucide="send" class="icono"></i> Enviar real al email override
        </button>
      </form>
    </section>

    <?php if ($preview): ?>
      <section class="ficha-card" style="margin-top:var(--espacio-cinco);">
        <h2 class="ficha-card__title">Preview HTML renderizado</h2>
        <iframe srcdoc="<?= htmlspecialchars($preview['html']) ?>" style="width:100%;height:700px;border:1px solid var(--admin-border);border-radius:var(--admin-radio-md);"></iframe>
      </section>
    <?php endif; ?>

    <section style="margin-top:var(--espacio-cinco); padding:var(--espacio-cuatro); background:var(--admin-bg-suave); border:1px solid var(--admin-border); border-radius:var(--admin-radio-md);">
      <strong>Estado del envío SMTP:</strong>
      <ul style="margin-top:.5rem;">
        <li>SMTP_HOST: <code><?= defined('SMTP_HOST') && SMTP_HOST ? htmlspecialchars(SMTP_HOST) : '<em>vacío → fallback a mail()</em>' ?></code></li>
        <li>SMTP_USER: <code><?= defined('SMTP_USER') && SMTP_USER ? htmlspecialchars(SMTP_USER) : '<em>vacío</em>' ?></code></li>
        <li>SMTP_FROM: <code><?= defined('SMTP_FROM_EMAIL') ? htmlspecialchars(SMTP_FROM_EMAIL) : '?' ?></code></li>
        <li>PHPMailer instalado: <?= file_exists(__DIR__ . '/../assets/librerias/phpmailer/PHPMailer.php') ? '✓' : '✗' ?></li>
      </ul>
    </section>
  </div>
</main>
<?php include 'footer.php'; ?>
