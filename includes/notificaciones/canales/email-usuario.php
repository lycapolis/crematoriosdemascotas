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

if (!function_exists('notifCanal_email_usuario')) {

    function notifCanal_email_usuario(\PDO $pdo, string $evento, array $datos): array
    {
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
}
