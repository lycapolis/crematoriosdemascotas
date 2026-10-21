<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Hemos recibido tus datos" (al visitante que deja un lead)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailLeadConfirmacion(array $usuario, ?array $negocio): array
 *     $usuario: nombre, email
 *     $negocio: nombre, slug  (null si el lead viene de la burbuja, sin ficha)
 *     return:   ['asunto','html','texto']
 *
 * Sin botones ni teléfono: el portal es un intermediario. No repite los datos
 * del formulario (evita usarlo para meter contenido en la bandeja de un tercero).
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailLeadConfirmacion')) {

    function renderEmailLeadConfirmacion(array $usuario, ?array $negocio = null): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($usuario['nombre'] ?? ''));
        $nNeg   = trim((string)($negocio['nombre'] ?? ''));

        $asunto = 'Hemos recibido tus datos';

        $principal = $nNeg !== ''
            ? 'Gracias por ponerte en contacto. Hemos recibido tus datos y se los hemos hecho llegar a <strong>' . $e($nNeg) . '</strong>. En cuanto puedan, se pondrán en contacto contigo.'
            : 'Gracias por ponerte en contacto. Hemos recibido tus datos y nuestro equipo se pondrá en contacto contigo lo antes posible para ayudarte.';

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Tus datos ya están en camino. Se pondrán en contacto contigo lo antes posible.',
            'saludo'     => $nombre !== '' ? "¡Hola, {$nombre}!" : '¡Hola!',
            'parrafos'   => [
                $principal,
            ],
            'cta'        => null,
            'pie_motivo' => "Has recibido este mensaje porque has dejado tus datos en {$sitio}. Es un mensaje automático; por favor, no respondas a este correo. Si no has sido tú, puedes ignorarlo.",
            'url_baja'   => notifUrlBaja((string)$usuario['email']),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
