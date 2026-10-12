<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — "Sobre tu solicitud" (rechazo, al solicitante)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailSolicitudRechazada(array $sol, ?string $motivo, string $email): array
 *     $sol: id, contacto_nombre, nombre_negocio
 *     $motivo: texto que escribió el admin al rechazar (opcional; se muestra tal cual, escapado)
 *     return: ['asunto','html','texto']
 *
 * Tono respetuoso y con salida: puede respondernos para aportar información y la revisamos.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailSolicitudRechazada')) {

    function renderEmailSolicitudRechazada(array $sol, ?string $motivo, string $email): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($sol['contacto_nombre'] ?? ''));
        $nNeg   = (string)($sol['nombre_negocio'] ?? '');
        $motivo = trim((string)$motivo);

        $asunto = "Sobre tu solicitud para {$nNeg}";

        $parrafos = [
            'Hemos revisado tu solicitud para <strong>' . $e($nNeg) . '</strong> y, por ahora, no hemos podido publicar su ficha en el directorio.',
        ];
        if ($motivo !== '') {
            $parrafos[] = '<strong>Motivo:</strong> ' . nl2br($e($motivo));
        }
        $parrafos[] = 'Si crees que se trata de un error o quieres aportarnos más información, respóndenos a este email y la revisaremos de nuevo. Con gusto te ayudamos.';

        $urlEscribir = 'mailto:info@crematoriosdemascotas.com?subject=' . rawurlencode('Solicitud nº ' . (int)($sol['id'] ?? 0) . ' — ' . $nNeg);

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'No hemos podido publicar tu ficha por ahora. Puedes escribirnos y la revisamos de nuevo.',
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => $parrafos,
            'cta'        => ['texto' => 'Escribirnos', 'url' => $urlEscribir],
            'pie_motivo' => "Has recibido este mensaje porque has solicitado el alta de tu negocio en {$sitio}.",
            'url_baja'   => notifUrlBaja($email),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
