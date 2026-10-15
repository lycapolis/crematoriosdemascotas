<?php
/**
 * ═══════════════════════════════════════════════════════════
 * RECIBIR LOS DATOS DE UN CONTACTO — página pública del negocio
 * ═══════════════════════════════════════════════════════════
 *
 * Enlace del botón «Quiero recibir sus datos» del email de lead (planes Gratis/Básico):
 *   /recibir-datos.php?l=<id del lead>&t=<firma HMAC>
 *
 * El negocio no tiene cuenta: el enlace firmado es su credencial.
 * - Firma mala, lead inexistente o sin ficha → «Enlace no válido», sin ningún dato.
 * - Firma buena → registra UNA entrega en lead_entregas (clave única por lead: repetir
 *   el clic no duplica) y muestra los datos completos del lead.
 *
 * Cupo (tiers.cupo_leads, NULL = ilimitado): se cuentan las entregas de la ficha de por vida. Con el
 * cupo agotado un lead nuevo NO se entrega (se invita a mejorar el plan); uno ya entregado se sigue viendo.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/conexion_db.php';
require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/notificaciones/despachador.php';

$leadId  = (int)($_GET['l'] ?? 0);
$token   = (string)($_GET['t'] ?? '');
$firmaOk = notifTokenEntregaValido($leadId, $token);

$lead     = null;
$agotado  = false;   // cupo agotado y este lead aún no se había entregado: no se muestran datos
$cupo     = null;    // null = plan ilimitado (sin contador)
if ($firmaOk) {
    try {
        $pdo = obtenerConexion();
        $st = $pdo->prepare("SELECT l.id, l.crematorio_id, l.nombre, l.email, l.phone_code, l.whatsapp_number,
                                    l.ciudad_lead AS ciudad, l.servicio, l.mascota_tamano, l.mensaje, l.created_at,
                                    c.nombre AS negocio_nombre
                             FROM leads_b2c l
                             JOIN crematorios c ON c.id = l.crematorio_id
                             WHERE l.id = ? LIMIT 1");
        $st->execute([$leadId]);
        $lead = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($lead) {
            $cremId = (int)$lead['crematorio_id'];
            $st = $pdo->prepare("SELECT 1 FROM lead_entregas WHERE lead_id = ?");
            $st->execute([$leadId]);
            $yaEntregado = (bool)$st->fetchColumn();

            if (!$yaEntregado) {
                // Una sola fila por lead (UNIQUE). Si el plan tiene cupo, solo se inserta mientras queden entregas
                // (la condición va dentro del propio INSERT para que dos clics a la vez no se cuelen).
                $cupoPrevio = notifCupoLeads($pdo, $cremId);
                $hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
                if ($cupoPrevio === null) {
                    $pdo->prepare("INSERT IGNORE INTO lead_entregas (lead_id, crematorio_id, ip_hash) VALUES (?, ?, ?)")
                        ->execute([$leadId, $cremId, $hash]);
                } else {
                    $pdo->prepare("INSERT IGNORE INTO lead_entregas (lead_id, crematorio_id, ip_hash)
                                   SELECT ?, ?, ? FROM DUAL
                                   WHERE (SELECT COUNT(*) FROM lead_entregas WHERE crematorio_id = ?) < ?")
                        ->execute([$leadId, $cremId, $hash, $cremId, $cupoPrevio['limite']]);
                }
                $st = $pdo->prepare("SELECT 1 FROM lead_entregas WHERE lead_id = ?");
                $st->execute([$leadId]);
                $agotado = !$st->fetchColumn();
            }
            $cupo = notifCupoLeads($pdo, $cremId);
        }
    } catch (\Throwable $ex) {
        error_log('[recibir-datos] ' . $ex->getMessage());
        $lead = null;
    }
}

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$titulo_pagina = 'Datos de contacto';
$pagina_actual = '';
$meta_robots   = 'noindex, nofollow';
include 'includes/header.php';

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$contador = $cupo ? notifContadorCupo($cupo) : null;

if ($lead && !$agotado) {
    $telTexto  = trim('+' . $lead['phone_code'] . ' ' . $lead['whatsapp_number']);
    $telLimpio = preg_replace('/[^0-9+]/', '', $telTexto);
    $telDigitos = preg_replace('/\D/', '', $telTexto);
    $hayTel    = trim((string)$lead['whatsapp_number']) !== '';
    $mascota   = trim($lead['servicio'] . ($lead['mascota_tamano'] ? ' · ' . $lead['mascota_tamano'] : ''), ' ·');
}
?>

<style>
    .recibir-datos { min-height: 60vh; display: flex; justify-content: center; padding: var(--espacio-seis) var(--espacio-cuatro); }
    .recibir-datos__inner { width: 100%; max-width: 640px; }
    .recibir-datos__titulo { font-family: var(--fuente-titulo); font-size: var(--fs-cinco); color: var(--color-dos); margin: 0 0 var(--espacio-tres); letter-spacing: -.01em; }
    .recibir-datos__texto { color: var(--color-seis); line-height: 1.65; margin: 0 0 var(--espacio-cuatro); }
    .recibir-datos__ficha { margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-cuatro); background: var(--color-cinco); border-radius: var(--radio-dos); }
    .recibir-datos__fila { display: flex; gap: var(--espacio-tres); padding: var(--espacio-uno) 0; }
    .recibir-datos__etiqueta { flex: 0 0 90px; font-size: var(--fs-dos); color: var(--color-seis-claro); text-transform: uppercase; letter-spacing: .06em; font-weight: var(--peso-negrita); padding-top: 2px; }
    .recibir-datos__valor { color: var(--color-dos); word-break: break-word; }
    .recibir-datos__mensaje { margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-cuatro); border-left: 3px solid var(--color-uno); background: var(--color-cinco); border-radius: var(--radio-uno); white-space: pre-line; color: var(--color-seis); line-height: 1.6; }
    .recibir-datos__cupo { display: inline-block; margin: 0 0 var(--espacio-cuatro); padding: var(--espacio-uno) var(--espacio-tres); border-radius: 999px; background: var(--cupo-fondo); color: var(--cupo-color); font-size: var(--fs-dos); font-weight: var(--peso-negrita); }
    .recibir-datos__aviso { font-size: var(--fs-dos); color: var(--color-seis-claro); margin: var(--espacio-cuatro) 0 0; }
    .recibir-datos__acciones { display: flex; gap: var(--espacio-tres); flex-wrap: wrap; }
    @media (max-width: 600px) {
        .recibir-datos__acciones .boton { width: 100%; }
        .recibir-datos__fila { flex-direction: column; gap: 0; }
        .recibir-datos__etiqueta { flex-basis: auto; }
    }
</style>

<main class="recibir-datos">
    <div class="recibir-datos__inner">
    <?php if (!$lead): ?>
        <h1 class="recibir-datos__titulo">Enlace no válido</h1>
        <p class="recibir-datos__texto">Este enlace no es válido o está incompleto. Si necesitas los datos de un contacto, escríbenos a <a href="mailto:info@crematoriosdemascotas.com">info@crematoriosdemascotas.com</a> y lo gestionamos.</p>

    <?php elseif ($agotado): ?>
        <h1 class="recibir-datos__titulo">Has usado todos tus leads gratuitos</h1>
        <?php if ($contador): ?>
        <p class="recibir-datos__cupo" style="--cupo-color: <?= $contador['color'] ?>; --cupo-fondo: <?= $contador['fondo'] ?>;"><?= $e($contador['cifra']) ?> · <?= $e($contador['texto']) ?></p>
        <?php endif; ?>
        <p class="recibir-datos__texto">Un cliente ha dejado sus datos en la ficha de <?= $e($lead['negocio_nombre']) ?>, pero con el plan gratuito puedes recibir los datos de <?= (int)($cupo['limite'] ?? 0) ?> contactos y ya los has usado todos. Mejora tu plan para seguir recibiendo los datos completos de cada cliente.</p>
        <div class="recibir-datos__acciones">
            <a class="boton uno" href="<?= $e(notifUrlMejorarPlan((string)$lead['negocio_nombre'])) ?>">Mejorar mi plan</a>
        </div>

    <?php else: ?>
        <h1 class="recibir-datos__titulo">Datos de contacto de <?= $e($lead['nombre']) ?></h1>
        <?php if ($contador): ?>
        <p class="recibir-datos__cupo" style="--cupo-color: <?= $contador['color'] ?>; --cupo-fondo: <?= $contador['fondo'] ?>;"><?= $e($contador['cifra']) ?> · <?= $e($contador['texto']) ?></p>
        <?php endif; ?>
        <p class="recibir-datos__texto">Este cliente dejó sus datos en la ficha de <?= $e($lead['negocio_nombre']) ?>. Aquí los tienes para que puedas contactar con él.</p>

        <div class="recibir-datos__ficha">
            <div class="recibir-datos__fila"><span class="recibir-datos__etiqueta">Nombre</span><span class="recibir-datos__valor"><?= $e($lead['nombre']) ?></span></div>
            <?php if ($hayTel): ?>
            <div class="recibir-datos__fila"><span class="recibir-datos__etiqueta">Teléfono</span><span class="recibir-datos__valor"><a href="tel:<?= $e($telLimpio) ?>"><?= $e($telTexto) ?></a></span></div>
            <?php endif; ?>
            <?php if ($lead['email']): ?>
            <div class="recibir-datos__fila"><span class="recibir-datos__etiqueta">Email</span><span class="recibir-datos__valor"><a href="mailto:<?= $e($lead['email']) ?>"><?= $e($lead['email']) ?></a></span></div>
            <?php endif; ?>
            <?php if ($mascota): ?>
            <div class="recibir-datos__fila"><span class="recibir-datos__etiqueta">Mascota</span><span class="recibir-datos__valor"><?= $e($mascota) ?></span></div>
            <?php endif; ?>
            <?php if ($lead['ciudad']): ?>
            <div class="recibir-datos__fila"><span class="recibir-datos__etiqueta">Ciudad</span><span class="recibir-datos__valor"><?= $e($lead['ciudad']) ?></span></div>
            <?php endif; ?>
        </div>

        <?php if (trim((string)$lead['mensaje']) !== ''): ?>
        <p class="recibir-datos__mensaje"><?= $e($lead['mensaje']) ?></p>
        <?php endif; ?>

        <div class="recibir-datos__acciones">
            <?php if ($hayTel): ?>
            <a class="boton uno" href="tel:<?= $e($telLimpio) ?>">Llamar</a>
            <a class="boton dos" href="https://wa.me/<?= $e($telDigitos) ?>" target="_blank" rel="noopener">WhatsApp</a>
            <?php endif; ?>
            <?php if ($lead['email']): ?>
            <a class="boton dos" href="mailto:<?= $e($lead['email']) ?>">Responder por email</a>
            <?php endif; ?>
        </div>

        <p class="recibir-datos__aviso">Trata estos datos solo para responder a esta consulta.</p>
    <?php endif; ?>
    </div>
</main>

<?php include 'includes/footer.php';
