<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email al SOLICITANTE de un alta de negocio
 * ═══════════════════════════════════════════════════════════
 *
 * Eventos: solicitud.recibida, solicitud.aprobada, solicitud.rechazada
 * Datos:   ['solicitud_id' => int, 'motivo' => ?string (solo en rechazada)]
 *
 * Destinatario: contacto_email de la solicitud (la persona que rellenó el formulario).
 * Es transaccional: informa del estado de SU solicitud, nunca publicidad.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../plantillas-email/solicitud-recibida.php';
require_once __DIR__ . '/../../plantillas-email/solicitud-aprobada.php';
require_once __DIR__ . '/../../plantillas-email/solicitud-rechazada.php';

if (!function_exists('notifCanal_email_solicitante')) {

    function notifCanal_email_solicitante(\PDO $pdo, string $evento, array $datos): array
    {
        $st = $pdo->prepare("
            SELECT s.id, s.estado, s.contacto_nombre, s.contacto_email, s.nombre_negocio,
                   s.motivo_rechazo, c.slug AS crem_slug
            FROM solicitudes_registro s
            LEFT JOIN crematorios c ON c.id = s.crematorio_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $st->execute([(int)($datos['solicitud_id'] ?? 0)]);
        $s = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$s)                                                       return ['omitir' => 'solicitud_no_existe'];
        if (!filter_var($s['contacto_email'], FILTER_VALIDATE_EMAIL))  return ['omitir' => 'email_invalido'];

        $email = mb_strtolower(trim($s['contacto_email']));
        $sol   = [
            'id'              => (int)$s['id'],
            'contacto_nombre' => mb_substr(trim((string)$s['contacto_nombre']), 0, 60),
            'nombre_negocio'  => (string)$s['nombre_negocio'],
        ];

        switch ($evento) {
            case 'solicitud.aprobada':
                if ($s['estado'] !== 'aprobada' || empty($s['crem_slug'])) return ['omitir' => 'no_aprobada'];
                $tpl = renderEmailSolicitudAprobada($sol, (string)$s['crem_slug'], $email);
                break;
            case 'solicitud.rechazada':
                if ($s['estado'] !== 'rechazada') return ['omitir' => 'no_rechazada'];
                $tpl = renderEmailSolicitudRechazada($sol, $s['motivo_rechazo'] ?? null, $email);
                break;
            default: // solicitud.recibida
                $tpl = renderEmailSolicitudRecibida($sol, $email);
        }

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => fn() => enviarMailHtml(
                $email, $tpl['asunto'], $tpl['html'], $tpl['texto'],
                'info@crematoriosdemascotas.com', $s['contacto_nombre'], 'html', notifUrlBaja($email)
            ),
        ];
    }
}
