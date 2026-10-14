<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Tu reseña ya es pública" (al autor)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailResenaAprobada(array $usuario, array $negocio): array
 *     $usuario: nombre, email, calificacion
 *     $negocio: nombre, slug
 *     return:   ['asunto','html','texto']
 *
 * Botón «Ver mi reseña» → ficha con UTM (utm_medium=transaccional).
 * Confirma explícitamente que no habrá publicidad a esta dirección.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailResenaAprobada')) {

    function renderEmailResenaAprobada(array $usuario, array $negocio): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($usuario['nombre'] ?? ''));
        $nNeg   = (string)($negocio['nombre'] ?? '');

        $asunto = "Tu reseña de {$nNeg} ya es pública";

        $urlFicha = notifUrlPublica((string)$negocio['slug'])
            . '?utm_source=email&utm_medium=transaccional&utm_campaign=resena_aprobada#tras-galeria';

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Gracias por compartir tu experiencia: ayuda a otros dueños de mascotas.',
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => [
                'Tu reseña de <strong>' . $e($nNeg) . '</strong> ya está publicada. Gracias por compartir tu experiencia: tu opinión ayuda a otros dueños de mascotas a decidir con más tranquilidad en un momento que no es fácil, y ese es justamente el propósito de este portal.',
                'Solo te escribimos para avisarte de lo que has compartido con nosotros: <strong>no recibirás publicidad ni comunicaciones comerciales</strong> a esta dirección.',
                'Gracias de nuevo por usar el portal.',
            ],
            'cta'        => ['texto' => 'Ver mi reseña', 'url' => $urlFicha],
            'pie_motivo' => "Has recibido este mensaje porque has publicado una reseña en {$sitio}. Si no has sido tú, puedes ignorarlo.",
            'url_baja'   => notifUrlBaja((string)$usuario['email']),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
