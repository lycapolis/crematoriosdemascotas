<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — «Hemos recibido tus datos» (actualización de ficha, al negocio)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailActualizacionRecibida(array $act, string $email): array
 *     $act: contacto_nombre, negocio_nombre, n_cambios (int), n_imagenes (int)
 *     return: ['asunto','html','texto']
 *
 * Transaccional: confirma que recibimos lo que el negocio envió desde verificar-ficha.php.
 * Aclara que no se publica nada sin revisar. Lleva enlace de baja.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailActualizacionRecibida')) {

    function renderEmailActualizacionRecibida(array $act, string $email): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($act['contacto_nombre'] ?? ''));
        $nNeg   = (string)($act['negocio_nombre'] ?? '');
        $nCamb  = (int)($act['n_cambios'] ?? 0);
        $nImg   = (int)($act['n_imagenes'] ?? 0);

        $asunto = "Hemos recibido los datos de {$nNeg}";

        $resumen = [];
        if ($nCamb > 0) $resumen[] = $nCamb . ($nCamb === 1 ? ' dato' : ' datos');
        if ($nImg > 0)  $resumen[] = $nImg . ($nImg === 1 ? ' imagen' : ' imágenes');

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => 'Los revisaremos antes de publicarlos. Hasta entonces tu ficha no cambia.',
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => array_filter([
                'Gracias por actualizar la ficha de <strong>' . $e($nNeg) . '</strong> en ' . $e($sitio) . '. Hemos recibido tu envío' . ($resumen ? ' (' . $e(implode(' y ', $resumen)) . ')' : '') . ' y lo revisaremos lo antes posible.',
                'Comprobamos todo antes de publicarlo, así que <strong>hasta entonces tu ficha seguirá mostrando los datos de siempre</strong>. Te escribiremos a este email cuando esté revisada.',
                'No tienes que hacer nada más.',
            ]),
            'cta'        => null,
            'pie_motivo' => "Has recibido este mensaje porque has enviado datos para actualizar una ficha en {$sitio}. Si no has sido tú, puedes ignorarlo.",
            'url_baja'   => notifUrlBaja($email),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
