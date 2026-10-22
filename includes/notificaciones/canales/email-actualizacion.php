<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email al NEGOCIO por la actualización de su ficha (campaña de verificación)
 * ═══════════════════════════════════════════════════════════
 *
 * Eventos: ficha.actualizacion_recibida  («hemos recibido tus datos»)
 *          ficha.actualizacion_revisada  («tu ficha está revisada»)
 * Datos:   ['actualizacion_id' => int]
 *
 * Destinatario: contacto_email de la actualización (la persona que rellenó el formulario).
 * Es transaccional: informa del estado de SU envío, nunca publicidad. Lleva enlace de baja.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../plantillas-email/actualizacion-recibida.php';
require_once __DIR__ . '/../../plantillas-email/actualizacion-revisada.php';
require_once __DIR__ . '/../../actualizacion-ficha.php';

if (!function_exists('notifCanal_email_actualizacion')) {

    function notifCanal_email_actualizacion(\PDO $pdo, string $evento, array $datos): array
    {
        $st = $pdo->prepare("
            SELECT a.*, c.nombre AS negocio_nombre, c.slug AS negocio_slug
            FROM actualizaciones_ficha a
            JOIN crematorios c ON c.id = a.crematorio_id
            WHERE a.id = ? LIMIT 1
        ");
        $st->execute([(int)($datos['actualizacion_id'] ?? 0)]);
        $a = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$a)                                                      return ['omitir' => 'actualizacion_no_existe'];
        if (!filter_var($a['contacto_email'], FILTER_VALIDATE_EMAIL)) return ['omitir' => 'email_invalido'];

        $email = mb_strtolower(trim($a['contacto_email']));
        $nombre = mb_substr(trim((string)$a['contacto_nombre']), 0, 60);

        if ($evento === 'ficha.actualizacion_revisada') {
            if ($a['estado'] !== 'revisada') return ['omitir' => 'no_revisada'];
            $cat = actFichaCatalogo();
            $apro = []; $rech = [];
            $q = $pdo->prepare("SELECT campo, estado FROM actualizacion_ficha_campos WHERE actualizacion_id = ?");
            $q->execute([(int)$a['id']]);
            foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $f) {
                $etq = $cat[$f['campo']][0] ?? $f['campo'];
                if ($f['estado'] === 'aprobado') $apro[] = $etq; elseif ($f['estado'] === 'rechazado') $rech[] = $etq;
            }
            $q = $pdo->prepare("SELECT tipo, estado FROM actualizacion_ficha_imagenes WHERE actualizacion_id = ?");
            $q->execute([(int)$a['id']]);
            $imgA = 0; $imgR = 0;
            foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $i) { if ($i['estado'] === 'aprobada') $imgA++; elseif ($i['estado'] === 'rechazada') $imgR++; }
            if ($imgA) $apro[] = $imgA === 1 ? '1 imagen' : "{$imgA} imágenes";
            if ($imgR) $rech[] = $imgR === 1 ? '1 imagen' : "{$imgR} imágenes";

            $tpl = renderEmailActualizacionRevisada([
                'contacto_nombre' => $nombre, 'negocio_nombre' => (string)$a['negocio_nombre'],
                'aprobados' => $apro, 'rechazados' => $rech,
                'mensaje_admin' => $a['mensaje_admin'] ?? null, 'insignia' => (bool)$apro,
            ], (string)$a['negocio_slug'], $email);
        } else {
            $nc = $pdo->prepare("SELECT COUNT(*) FROM actualizacion_ficha_campos WHERE actualizacion_id = ?");
            $nc->execute([(int)$a['id']]);
            $ni = $pdo->prepare("SELECT COUNT(*) FROM actualizacion_ficha_imagenes WHERE actualizacion_id = ?");
            $ni->execute([(int)$a['id']]);
            $tpl = renderEmailActualizacionRecibida([
                'contacto_nombre' => $nombre, 'negocio_nombre' => (string)$a['negocio_nombre'],
                'n_cambios' => (int)$nc->fetchColumn(), 'n_imagenes' => (int)$ni->fetchColumn(),
            ], $email);
        }

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => fn() => enviarMailHtml(
                $email, $tpl['asunto'], $tpl['html'], $tpl['texto'],
                'info@crematoriosdemascotas.com', $nombre, 'html', notifUrlBaja($email)
            ),
        ];
    }
}
