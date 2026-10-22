<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PLANTILLA EMAIL — «Tu ficha está revisada» (actualización de ficha, al negocio)
 * ═══════════════════════════════════════════════════════════
 *
 *   renderEmailActualizacionRevisada(array $act, string $slug, string $email): array
 *     $act: contacto_nombre, negocio_nombre,
 *           aprobados  (string[] etiquetas de lo publicado),
 *           rechazados (string[] etiquetas de lo que no se pudo aprobar),
 *           mensaje_admin (?string, motivo escrito por el equipo),
 *           insignia (bool: se ha otorgado «Datos actualizados por el negocio»)
 *     $slug: slug de la ficha
 *     return: ['asunto','html','texto']
 *
 * Tres casos: todo aprobado · parte aprobada y parte no · nada aprobado.
 * Botón «Ver mi ficha» (con UTM) solo si algo se publicó. Lleva enlace de baja.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/_layout-usuario.php';

if (!function_exists('renderEmailActualizacionRevisada')) {

    function renderEmailActualizacionRevisada(array $act, string $slug, string $email): array
    {
        $sitio  = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $e      = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $nombre = trim((string)($act['contacto_nombre'] ?? ''));
        $nNeg   = (string)($act['negocio_nombre'] ?? '');
        $apro   = array_values((array)($act['aprobados'] ?? []));
        $rech   = array_values((array)($act['rechazados'] ?? []));
        $msg    = trim((string)($act['mensaje_admin'] ?? ''));
        $lista  = fn(array $xs) => implode('<br>', array_map(fn($x) => '• ' . $e($x), $xs));

        $parrafos = [];
        if ($apro) {
            $asunto = "La ficha de {$nNeg} está revisada";
            $pre    = 'Hemos publicado los datos que nos enviaste.';
            $parrafos[] = 'Hemos revisado los datos que nos enviaste para <strong>' . $e($nNeg) . '</strong> y ya están publicados en tu ficha:<br>' . $lista($apro);
            if ($rech) {
                $parrafos[] = 'Esto no hemos podido aprobarlo por ahora:<br>' . $lista($rech);
            }
            if (!empty($act['insignia'])) {
                $parrafos[] = 'Además, tu ficha ya muestra la insignia <strong>«Datos actualizados por el negocio»</strong>, que indica a las familias que la información la mantiene quien mejor la conoce.';
            }
        } else {
            $asunto = "Sobre los datos enviados de {$nNeg}";
            $pre    = 'Hemos revisado tu envío y no hemos podido aprobar ningún cambio.';
            $parrafos[] = 'Hemos revisado los datos que nos enviaste para <strong>' . $e($nNeg) . '</strong>, pero por ahora no hemos podido aprobar ningún cambio:<br>' . $lista($rech);
            $parrafos[] = 'Tu ficha sigue mostrando los datos de siempre.';
        }
        if ($msg !== '') {
            $parrafos[] = 'Nota de nuestro equipo:<br><em>' . nl2br($e($msg)) . '</em>';
        }

        $despues = [];
        if ($rech) {
            $despues[] = 'Si quieres aclarar o corregir algo, respóndenos a este email y lo revisamos contigo.';
        }

        $capa = renderEmailLayoutUsuario([
            'asunto'     => $asunto,
            'preheader'  => $pre,
            'saludo'     => $nombre !== '' ? "Hola, {$nombre}:" : 'Hola:',
            'parrafos'   => $parrafos,
            'cta'        => $apro ? ['texto' => 'Ver mi ficha', 'url' => notifUrlPublica($slug) . '?utm_source=email&utm_medium=transaccional&utm_campaign=actualizacion_revisada'] : null,
            'parrafos_despues' => $despues,
            'cta2'       => null,
            'pie_motivo' => "Has recibido este mensaje porque enviaste datos para actualizar una ficha en {$sitio}.",
            'url_baja'   => notifUrlBaja($email),
        ]);

        return ['asunto' => $asunto, 'html' => $capa['html'], 'texto' => $capa['texto']];
    }
}
