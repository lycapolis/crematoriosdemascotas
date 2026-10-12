<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Hemos recibido tu reseña" (al autor)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailResenaRecibida(array $usuario, array $negocio): array
 *     $usuario: nombre, email, calificacion
 *     $negocio: nombre, slug
 *     return:   ['asunto','html','texto']
 *
 * No incluye el texto del comentario (evita usar el formulario para
 * meter mensajes arbitrarios en la bandeja de un tercero) ni botón.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailResenaRecibida')) {

    function renderEmailResenaRecibida(array $usuario, array $negocio): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($usuario['nombre'] ?? ''));
        $nNeg   = (string)($negocio['nombre'] ?? '');

        $asunto = "Hemos recibido tu reseña de {$nNeg}";

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Tu reseña está en revisión. Si cumple nuestras normas, la publicaremos.',
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => [
                'Gracias por compartir tu experiencia con <strong>' . $e($nNeg) . '</strong> (' . $e(emailEstrellas((int)($usuario['calificacion'] ?? 0))) . '). Hemos recibido tu reseña y está en revisión.',
                'Revisamos todas las reseñas antes de publicarlas. Si cumple nuestras normas, la publicaremos y te lo comunicaremos.',
                'Gracias por tu tiempo.',
            ],
            'cta'        => null,
            'pie_motivo' => "Has recibido este mensaje porque has enviado una reseña en {$sitio}. Si no has sido tú, puedes ignorarlo.",
            'url_baja'   => notifUrlBaja((string)$usuario['email']),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
