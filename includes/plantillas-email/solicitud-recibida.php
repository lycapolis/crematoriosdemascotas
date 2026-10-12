<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Hemos recibido tu solicitud" (al solicitante)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailSolicitudRecibida(array $sol, string $email): array
 *     $sol: id, contacto_nombre, nombre_negocio
 *     return: ['asunto','html','texto']
 *
 * Sin botón: no se le pide nada. Solo confirma la recepción y explica qué pasa ahora.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailSolicitudRecibida')) {

    function renderEmailSolicitudRecibida(array $sol, string $email): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($sol['contacto_nombre'] ?? ''));
        $nNeg   = (string)($sol['nombre_negocio'] ?? '');

        $asunto = "Hemos recibido tu solicitud para {$nNeg}";

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Tu solicitud está en revisión. Te escribiremos en cuanto tengamos novedades.',
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => [
                'Gracias por solicitar el alta de <strong>' . $e($nNeg) . '</strong> en nuestro directorio. Hemos recibido tu solicitud (nº ' . (int)($sol['id'] ?? 0) . ') y está en revisión.',
                'Comprobamos los datos antes de publicar cualquier ficha. Te escribiremos a este email en cuanto tengamos novedades, tanto si la ficha se publica como si necesitamos aclarar algo contigo.',
                'No tienes que hacer nada más por ahora.',
            ],
            'cta'        => null,
            'pie_motivo' => "Has recibido este mensaje porque has enviado una solicitud de alta en {$sitio}. Si no has sido tú, puedes ignorarlo.",
            'url_baja'   => notifUrlBaja($email),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
