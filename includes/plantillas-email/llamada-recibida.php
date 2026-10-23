<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — «Hemos recibido tu solicitud de llamada» (campaña de verificación, al negocio)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailLlamadaRecibida(array $ll, string $email): array
 *     $ll: negocio_nombre, negocio_slug, telefono (string, el número al que llamaremos)
 *     return: ['asunto','html','texto']
 *
 * Transaccional: confirma la solicitud hecha en verificar-ficha.php. Va al email de la ficha
 * (el negocio no deja un email en ese paso). Lleva enlace de baja.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailLlamadaRecibida')) {

    function renderEmailLlamadaRecibida(array $ll, string $email): array
    {
        $sitio = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e     = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nNeg  = (string)($ll['negocio_nombre'] ?? '');
        $tel   = (string)($ll['telefono'] ?? '');
        $slug  = (string)($ll['negocio_slug'] ?? '');

        $asunto = "Te llamaremos pronto: ficha de {$nNeg}";

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Hemos recibido tu solicitud. Es una llamada breve y gratuita.',
            'saludo'     => 'Hola:',
            'parrafos'   => [
                'Hemos recibido tu solicitud para verificar la ficha de <strong>' . $e($nNeg) . '</strong> en ' . $e($sitio) . '. Te llamaremos al <strong>' . $e($tel) . '</strong> lo antes posible, de lunes a viernes en horario comercial.',
                'Es una llamada <strong>breve y gratuita</strong>: una persona de nuestro equipo comprobará contigo que los datos de la ficha son correctos. Al terminar, tu ficha recibirá <strong>gratis la insignia «Verificado por humano»</strong>.',
                'Si ese número no es el correcto o quieres cambiar algo antes de la llamada, responde a este email y lo ajustamos.',
            ],
            'cta'        => $slug !== '' ? ['texto' => 'Ver mi ficha', 'url' => notifUrlPublica($slug)] : null,
            'pie_motivo' => "Has recibido este mensaje porque se ha pedido una llamada de verificación para una ficha de {$sitio}, que edita Lycapolis LLC. Si no has sido tú, puedes ignorarlo.",
            'url_baja'   => notifUrlBaja($email),
            'enlace_terminos' => true,
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
