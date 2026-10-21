<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email al USUARIO que dejó una reseña
 * ═══════════════════════════════════════════════════════════
 *
 * Eventos soportados: resena.recibida, resena.aprobada
 * Datos: ['resena_id' => int]
 *
 * Reglas:
 *   - Solo reseñas de fuente 'propio' (las de Google/Trustindex no tienen autor con email real).
 *   - 'resena.aprobada' solo se envía si la reseña sigue aprobada en BD.
 *   - Email transaccional: sin publicidad, con enlace de baja de notificaciones.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../plantillas-email/resena-recibida.php';
require_once __DIR__ . '/../../plantillas-email/resena-aprobada.php';
require_once __DIR__ . '/../../plantillas-email/lead-confirmacion.php';

if (!function_exists('notifCanal_email_usuario')) {

    function notifCanal_email_usuario(\PDO $pdo, string $evento, array $datos): array
    {
        // Confirmación al visitante que dejó sus datos (lead de ficha o de burbuja)
        if ($evento === 'lead_b2c.confirmacion') {
            return notifCanalEmailUsuarioLead($pdo, $datos);
        }

        $st = $pdo->prepare("
            SELECT r.id, r.nombre, r.email, r.calificacion, r.estado, r.fuente,
                   c.nombre AS negocio_nombre, c.slug AS negocio_slug
            FROM resenas r
            JOIN crematorios c ON c.id = r.crematorio_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $st->execute([(int)($datos['resena_id'] ?? 0)]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        // Sin destinatario → el despachador no deja rastro (puede reintentarse más adelante)
        if (!$r)                                        return ['omitir' => 'resena_no_existe'];
        if ($r['fuente'] !== 'propio')                  return ['omitir' => 'fuente_no_propia'];
        if (!filter_var($r['email'], FILTER_VALIDATE_EMAIL)) return ['omitir' => 'email_invalido'];
        if ($evento === 'resena.aprobada' && $r['estado'] !== 'aprobada') {
            return ['omitir' => 'ya_no_aprobada'];
        }

        $email   = mb_strtolower(trim($r['email']));
        $negocio = ['nombre' => $r['negocio_nombre'], 'slug' => $r['negocio_slug']];
        $usuario = ['nombre' => mb_substr(trim((string)$r['nombre']), 0, 60), 'email' => $email, 'calificacion' => (int)$r['calificacion']];

        $tpl = $evento === 'resena.aprobada'
            ? renderEmailResenaAprobada($usuario, $negocio)
            : renderEmailResenaRecibida($usuario, $negocio);

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => fn() => enviarMailHtml(
                $email,
                $tpl['asunto'],
                $tpl['html'],
                $tpl['texto'],
                'info@crematoriosdemascotas.com',   // Reply-To: una persona real responde
                $r['nombre'],
                'html',
                notifUrlBaja($email)
            ),
        ];
    }

    /** Evento lead_b2c.confirmacion. Datos: ['lead_id' => int, 'crematorio_id' => int, 'dia' => 'Ymd']. */
    function notifCanalEmailUsuarioLead(\PDO $pdo, array $datos): array
    {
        $st = $pdo->prepare("SELECT id, nombre, email, crematorio_id FROM leads_b2c WHERE id = ? LIMIT 1");
        $st->execute([(int)($datos['lead_id'] ?? 0)]);
        $l = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$l)                                             return ['omitir' => 'lead_no_existe'];
        if (!filter_var($l['email'], FILTER_VALIDATE_EMAIL)) return ['omitir' => 'email_invalido'];

        $negocio = null;
        if (!empty($l['crematorio_id'])) {
            $q = $pdo->prepare("SELECT nombre, slug FROM crematorios WHERE id = ? LIMIT 1");
            $q->execute([(int)$l['crematorio_id']]);
            $negocio = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $email   = mb_strtolower(trim($l['email']));
        $usuario = ['nombre' => mb_substr(trim((string)$l['nombre']), 0, 60), 'email' => $email];
        $tpl     = renderEmailLeadConfirmacion($usuario, $negocio);

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => fn() => enviarMailHtml(
                $email,
                $tpl['asunto'],
                $tpl['html'],
                $tpl['texto'],
                'info@crematoriosdemascotas.com',
                $l['nombre'],
                'html',
                notifUrlBaja($email)
            ),
        ];
    }
}
