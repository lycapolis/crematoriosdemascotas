<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email al NEGOCIO «hemos recibido tu solicitud de llamada» (campaña de verificación)
 * ═══════════════════════════════════════════════════════════
 *
 * Evento: llamada.solicitada     Datos: ['llamada_id' => int]
 *
 * Destinatario: el primer email válido de la ficha (en el paso de la llamada el negocio no deja email).
 * Transaccional; lleva enlace de baja. Una sola vez por solicitud (idempotencia del despachador).
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../plantillas-email/llamada-recibida.php';
require_once __DIR__ . '/../../campana-prospeccion.php';   // campanaEmailsFicha / campanaFichaSuprimida

if (!function_exists('notifCanal_email_llamada')) {

    function notifCanal_email_llamada(\PDO $pdo, string $evento, array $datos): array
    {
        $st = $pdo->prepare("
            SELECT l.id, l.telefono_confirmado, c.nombre AS negocio_nombre, c.slug AS negocio_slug,
                   c.email, c.email_clientes, c.emails_json
            FROM solicitudes_llamada l
            JOIN crematorios c ON c.id = l.crematorio_id
            WHERE l.id = ? LIMIT 1
        ");
        $st->execute([(int)($datos['llamada_id'] ?? 0)]);
        $l = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$l) return ['omitir' => 'llamada_no_existe'];
        $emails = campanaEmailsFicha($l);
        if (!$emails) return ['omitir' => 'sin_email'];
        if (campanaFichaSuprimida($pdo, $l)) return ['omitir' => 'baja'];
        $email = $emails[0];

        $tpl = renderEmailLlamadaRecibida([
            'negocio_nombre' => (string)$l['negocio_nombre'],
            'negocio_slug'   => (string)$l['negocio_slug'],
            'telefono'       => (string)$l['telefono_confirmado'],
        ], $email);

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => fn() => enviarMailHtml(
                $email, $tpl['asunto'], $tpl['html'], $tpl['texto'],
                'info@crematoriosdemascotas.com', '', 'html', notifUrlBaja($email)
            ),
        ];
    }
}
