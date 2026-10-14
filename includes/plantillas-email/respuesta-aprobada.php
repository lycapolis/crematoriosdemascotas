<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Tu respuesta a la reseña ya es pública" (al NEGOCIO)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailRespuestaAprobada(array $resena, string $respuesta, array $negocio, string $emailNegocio): array
 *     $resena:    nombre (autor), calificacion, comentario
 *     $respuesta: texto publicado
 *     $negocio:   nombre, slug
 *     return:     ['asunto','html','texto']
 *
 * Botón → la reseña con su respuesta en la ficha (UTM utm_medium=transaccional).
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailRespuestaAprobada')) {

    function renderEmailRespuestaAprobada(array $resena, string $respuesta, array $negocio, string $emailNegocio): array
    {
        $sitio = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e     = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nNeg  = (string)($negocio['nombre'] ?? '');
        $autor = trim((string)($resena['nombre'] ?? ''));
        $autor = $autor !== '' ? mb_substr($autor, 0, 60) : 'un cliente';

        $asunto = "Tu respuesta a la reseña de {$autor} ya es pública";

        $urlFicha = notifUrlPublica((string)$negocio['slug'])
            . '?utm_source=email&utm_medium=transaccional&utm_campaign=respuesta_aprobada#tras-galeria';

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => "Hemos publicado tu respuesta a la reseña de {$autor}.",
            'saludo'     => "Hola, equipo de {$nNeg}:",
            'parrafos'   => [
                'Hemos revisado y publicado tu respuesta a la reseña de <strong>' . $e($autor) . '</strong>. Ya aparece bajo la reseña, en la ficha de <strong>' . $e($nNeg) . '</strong>:',
            ],
            'cita'       => ['autor' => $nNeg, 'estrellas' => 0, 'texto' => $respuesta],
            'cta'        => ['texto' => 'Ver la respuesta en mi ficha', 'url' => $urlFicha],
            'parrafos_despues' => [
                'No tienes que hacer nada más. Gracias por responder a tus clientes.',
            ],
            'pie_motivo' => "Has recibido este mensaje porque {$nNeg} figura en {$sitio} y has respondido a una reseña.",
            'url_baja'   => notifUrlBaja($emailNegocio),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
