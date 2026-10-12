<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Tu negocio ya está en el directorio" (al solicitante)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailSolicitudAprobada(array $sol, string $slug, string $email): array
 *     $sol: id, contacto_nombre, nombre_negocio
 *     $slug: slug de la ficha ya creada
 *     return: ['asunto','html','texto']
 *
 * Botón: «Ver mi ficha» (con UTM). Explica qué avisos recibirá el negocio a partir de ahora.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailSolicitudAprobada')) {

    function renderEmailSolicitudAprobada(array $sol, string $slug, string $email): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($sol['contacto_nombre'] ?? ''));
        $nNeg   = (string)($sol['nombre_negocio'] ?? '');

        $asunto = "{$nNeg} ya está en el directorio";

        $urlFicha = notifUrlPublica($slug) . '?utm_source=email&utm_medium=transaccional&utm_campaign=solicitud_aprobada';

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Hemos revisado tu solicitud y tu ficha ya está publicada.',
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => [
                '¡Buenas noticias! Hemos revisado tu solicitud y <strong>' . $e($nNeg) . '</strong> ya tiene su ficha publicada en ' . $e($sitio) . '.',
            ],
            'cta'        => ['texto' => 'Ver mi ficha', 'url' => $urlFicha],
            'parrafos_despues' => [
                'A partir de ahora, quien visite tu ficha podrá ponerse en contacto con tu negocio. Te avisaremos por email, a la dirección de contacto que nos indicaste, cuando alguien deje sus datos o publique una reseña.',
                'Si quieres corregir o ampliar algún dato de tu ficha, respóndenos a este email y lo revisamos.',
            ],
            'cta2'       => null,
            'pie_motivo' => "Has recibido este mensaje porque has solicitado el alta de tu negocio en {$sitio}.",
            'url_baja'   => notifUrlBaja($email),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
