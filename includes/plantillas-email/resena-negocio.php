<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Tu negocio ha recibido una reseña positiva" (al NEGOCIO)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailResenaNegocio(array $resena, array $negocio, string $emailNegocio): array
 *     $resena:  id, nombre (autor, tal como se muestra en la ficha), calificacion, comentario
 *     $negocio: nombre, slug
 *     return:   ['asunto','html','texto']
 *
 * Es solo una NOTIFICACIÓN: se dice expresamente que no hay que hacer nada.
 *   · Botón 1 → ver la reseña en su ficha (con UTM, utm_medium=transaccional).
 *   · Botón 2 → responderla en responder-resena.php (enlace firmado, sin cuenta).
 * La respuesta del negocio NO se publica sola: la revisamos, la publicamos y se le avisa.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailResenaNegocio')) {

    function renderEmailResenaNegocio(array $resena, array $negocio, string $emailNegocio): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nNeg   = (string)($negocio['nombre'] ?? '');
        $autor  = trim((string)($resena['nombre'] ?? ''));
        $autor  = $autor !== '' ? mb_substr($autor, 0, 60) : 'Un cliente';
        $nota   = (int)($resena['calificacion'] ?? 5);
        $estrellas = emailEstrellas($nota);

        $asunto = "Nueva reseña positiva para {$nNeg} ({$estrellas})";

        $urlResena = notifUrlPublica((string)$negocio['slug'])
            . '?utm_source=email&utm_medium=transaccional&utm_campaign=resena_negocio#tras-galeria';

        // Página del portal con el formulario (enlace firmado: el negocio no necesita cuenta)
        $urlResponder = notifUrlRespuesta((int)($resena['id'] ?? 0));

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => "{$autor} ha dejado una reseña positiva de {$nNeg}. No tienes que hacer nada.",
            'saludo'     => "Hola, equipo de {$nNeg}:",
            'parrafos'   => [
                '¡Buenas noticias! <strong>' . $e($autor) . '</strong> ha dejado una reseña positiva sobre <strong>' . $e($nNeg) . '</strong> y ya está publicada en tu ficha del directorio.',
            ],
            'cita'       => ['autor' => $autor, 'estrellas' => $nota, 'texto' => (string)($resena['comentario'] ?? '')],
            'cta'        => ['texto' => 'Ver la reseña en mi ficha', 'url' => $urlResena],
            'parrafos_despues' => [
                '<strong>No tienes que hacer nada:</strong> este email es solo una notificación.',
                'Si quieres responder a la reseña, escribe tu mensaje en la página que se abre con el botón de abajo. Lo revisaremos y, si todo está en orden, lo publicaremos bajo la reseña y te avisaremos en cuanto esté público.',
            ],
            'cta2'       => ['texto' => 'Quiero responder a la reseña', 'url' => $urlResponder],
            'pie_motivo' => "Has recibido este mensaje porque {$nNeg} figura en {$sitio} y ha recibido una nueva reseña.",
            'url_baja'   => notifUrlBaja($emailNegocio),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
