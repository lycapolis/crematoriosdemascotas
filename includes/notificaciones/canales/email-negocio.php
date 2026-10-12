<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email al NEGOCIO por una reseña positiva aprobada
 * ═══════════════════════════════════════════════════════════
 *
 * Evento: resena.aprobada (el mismo que avisa al autor)    Datos: ['resena_id' => int]
 *
 * Se envía solo si:
 *   - la reseña es del portal (fuente 'propio') y sigue aprobada,
 *   - es positiva: calificación ≥ RESENA_AVISO_NEGOCIO_MIN_ESTRELLAS (por defecto 4),
 *   - el negocio no pidió dejar de recibir notificaciones (recibe_notif_leads) ni su email
 *     está dado de baja (notificaciones_supresiones),
 *   - el negocio tiene algún email válido (misma cascada que los leads).
 * Aplica a TODOS los planes. Una sola vez por reseña (idempotencia en el despachador).
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../notificaciones.php';                 // resolverEmailDestinoNegocio()
require_once __DIR__ . '/../../plantillas-email/resena-negocio.php';

if (!function_exists('notifCanal_email_negocio')) {

    function notifCanal_email_negocio(\PDO $pdo, string $evento, array $datos): array
    {
        $st = $pdo->prepare("
            SELECT r.id, r.nombre, r.calificacion, r.comentario, r.estado, r.fuente,
                   c.nombre AS negocio_nombre, c.slug AS negocio_slug,
                   c.email AS crem_email_publico, c.email_notif_leads AS crem_email_notif,
                   c.emails_json AS crem_emails_json, c.recibe_notif_leads
            FROM resenas r
            JOIN crematorios c ON c.id = r.crematorio_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $st->execute([(int)($datos['resena_id'] ?? 0)]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        // Sin destinatario → el despachador no deja rastro
        if (!$r)                                             return ['omitir' => 'resena_no_existe'];
        if ($r['fuente'] !== 'propio')                       return ['omitir' => 'fuente_no_propia'];
        if ($r['estado'] !== 'aprobada')                     return ['omitir' => 'ya_no_aprobada'];

        $minimo = defined('RESENA_AVISO_NEGOCIO_MIN_ESTRELLAS') ? (int)RESENA_AVISO_NEGOCIO_MIN_ESTRELLAS : 4;
        if ((int)$r['calificacion'] < $minimo)               return ['omitir' => 'no_positiva'];
        if ((int)($r['recibe_notif_leads'] ?? 1) !== 1)      return ['omitir' => 'negocio_opt_out'];

        $email = resolverEmailDestinoNegocio($r);
        if (!$email)                                         return ['omitir' => 'sin_email'];
        $email = mb_strtolower(trim($email));

        $tpl = renderEmailResenaNegocio(
            ['id' => $r['id'], 'nombre' => $r['nombre'], 'calificacion' => (int)$r['calificacion'], 'comentario' => $r['comentario']],
            ['nombre' => $r['negocio_nombre'], 'slug' => $r['negocio_slug']],
            $email
        );

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => fn() => enviarMailHtml(
                $email,
                $tpl['asunto'],
                $tpl['html'],
                $tpl['texto'],
                'info@crematoriosdemascotas.com',
                $r['negocio_nombre']
            ),
        ];
    }
}
