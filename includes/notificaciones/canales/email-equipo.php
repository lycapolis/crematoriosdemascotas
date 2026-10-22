<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email al EQUIPO por una conversión del sitio
 * ═══════════════════════════════════════════════════════════
 *
 * Eventos: resena.recibida, contacto.recibido, lead_comercial.recibido,
 *          solicitud.recibida (registro de negocio), lead_b2c.recibido (ficha y burbuja),
 *          respuesta.recibida (un negocio responde a una reseña; pendiente de moderar).
 *
 * Destinatario: EMAIL_CONTACTO (info@). Es un aviso interno: sin enlace de baja ni
 * comprobación de supresiones. El Reply-To es el email de quien convierte, para poder
 * responderle directamente desde la bandeja.
 *
 * Los textos que llegan en el payload pasaron por filtrar() (htmlspecialchars) al guardarse;
 * aquí se decodifican para mostrarlos como texto normal y la plantilla los vuelve a escapar.
 *
 * Los constructores notifEquipo* son funciones puras: la maqueta (admin/notif-leads-test.php)
 * las usa con datos de ejemplo.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../plantillas-email/equipo-conversion.php';

if (!function_exists('notifCanal_email_equipo')) {

    function notifCanal_email_equipo(\PDO $pdo, string $evento, array $datos): array
    {
        if ($evento === 'respuesta.recibida') {
            $st = $pdo->prepare("
                SELECT p.id, p.texto, r.id AS resena_id, r.nombre AS autor, r.calificacion, r.comentario,
                       c.nombre AS negocio_nombre
                FROM resenas_respuestas p
                JOIN resenas r ON r.id = p.resena_id
                JOIN crematorios c ON c.id = r.crematorio_id
                WHERE p.id = ? LIMIT 1
            ");
            $st->execute([(int)($datos['respuesta_id'] ?? 0)]);
            $rp = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$rp) return ['omitir' => 'respuesta_no_existe'];
            $p = [
                'tipo'    => 'Respuesta de un negocio (pendiente de moderar)',
                'resumen' => $rp['negocio_nombre'] . ' → reseña de ' . $rp['autor'] . ' · ' . (int)$rp['calificacion'] . '★',
                'filas'   => [
                    'Negocio'      => (string)$rp['negocio_nombre'],
                    'Reseña de'    => (string)$rp['autor'],
                    'Calificación' => notifEquipoEstrellas($rp['calificacion']),
                ],
                'bloques' => ['Reseña' => (string)$rp['comentario'], 'Respuesta del negocio' => (string)$rp['texto']],
                'cta'     => ['texto' => 'Moderar la respuesta', 'url' => notifUrlPublica('admin/respuestas-resenas.php')],
                'cta2'    => null,
                'origen'  => '',
                'reply_to' => null,
            ];
        } elseif ($evento === 'llamada.solicitada') {
            $st = $pdo->prepare("
                SELECT l.*, c.nombre AS negocio_nombre, c.slug AS negocio_slug
                FROM solicitudes_llamada l
                JOIN crematorios c ON c.id = l.crematorio_id
                WHERE l.id = ? LIMIT 1
            ");
            $st->execute([(int)($datos['llamada_id'] ?? 0)]);
            $ll = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$ll) return ['omitir' => 'llamada_no_existe'];
            $p = notifEquipoDesdeLlamada($ll);
        } elseif ($evento === 'lead_b2c.recibido') {
            $st = $pdo->prepare("SELECT * FROM leads_b2c WHERE id = ? LIMIT 1");
            $st->execute([(int)($datos['lead_id'] ?? 0)]);
            $lead = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$lead) return ['omitir' => 'lead_no_existe'];
            $p = notifEquipoDesdeLead($lead);
        } else {
            $payload = $datos['payload'] ?? null;
            if (!is_array($payload)) return ['omitir' => 'sin_payload'];
            $p = notifEquipoDesdePayload($evento, $payload, $datos);
        }
        if (!$p) return ['omitir' => 'evento_sin_plantilla'];

        $destino = defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'info@crematoriosdemascotas.com';
        $tpl     = renderEmailEquipoConversion($p);

        return [
            'destinatario' => $destino,
            'enviar'       => fn() => enviarMailHtml($destino, $tpl['asunto'], $tpl['html'], $tpl['texto'], $p['reply_to'] ?? null, 'Equipo'),
        ];
    }

    /** Texto «normal» a partir de un valor que pasó por htmlspecialchars. */
    function notifEquipoTxt($v): string
    {
        return trim(html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8'));
    }

    function notifEquipoEstrellas($n): string
    {
        $n = max(0, min(5, (int)$n));
        return str_repeat('★', $n) . str_repeat('☆', 5 - $n) . " ({$n}/5)";
    }

    /** reseña / contacto / promociona / registro: se construyen desde el payload de siempre. */
    function notifEquipoDesdePayload(string $evento, array $payload, array $datos = []): ?array
    {
        $d      = $payload['data'] ?? [];
        $t      = 'notifEquipoTxt';
        $origen = $t($payload['page_url'] ?? '');

        switch ($evento) {
            case 'resena.recibida':
                return [
                    'tipo'    => 'Nueva reseña (pendiente de moderar)',
                    'resumen' => $t($d['nombre'] ?? '') . ' → ' . $t($d['crematorio'] ?? '') . ' · ' . (int)($d['calificacion'] ?? 0) . '★',
                    'filas'   => [
                        'Negocio'      => $t($d['crematorio'] ?? ''),
                        'Calificación' => notifEquipoEstrellas($d['calificacion'] ?? 0),
                        'Autor'        => $t($d['nombre'] ?? ''),
                        'Email'        => $t($d['email'] ?? ''),
                        'Imágenes'     => (int)($d['imagenes_adjuntas'] ?? 0) > 0 ? (int)$d['imagenes_adjuntas'] . ' adjunta(s)' : '',
                    ],
                    'bloques' => ['Comentario' => $t($d['comentario'] ?? '')],
                    'cta'     => ['texto' => 'Moderar en el admin', 'url' => notifUrlPublica('admin/resenas.php')],
                    'cta2'    => null,
                    'origen'  => $origen,
                    'reply_to' => $t($d['email'] ?? ''),
                ];

            case 'contacto.recibido':
                $mail = $t($d['email'] ?? '');
                return [
                    'tipo'    => 'Mensaje de contacto',
                    'resumen' => $t($d['nombre'] ?? '') . ($t($d['asunto'] ?? '') !== '' ? ' — ' . $t($d['asunto']) : ''),
                    'filas'   => [
                        'Nombre'   => $t($d['nombre'] ?? ''),
                        'Email'    => $mail,
                        'Teléfono' => $t($d['telefono'] ?? ''),
                        'Asunto'   => $t($d['asunto'] ?? ''),
                    ],
                    'bloques' => ['Mensaje' => $t($d['mensaje'] ?? '')],
                    'cta'     => $mail ? ['texto' => 'Responder', 'url' => 'mailto:' . $mail . '?subject=' . rawurlencode('Re: ' . $t($d['asunto'] ?? 'Tu mensaje'))] : null,
                    'cta2'    => null,
                    'origen'  => $origen,
                    'reply_to' => $mail,
                ];

            case 'lead_comercial.recibido':
                $mail = $t($d['email'] ?? '');
                return [
                    'tipo'    => 'Lead comercial: «Promociona tu crematorio»',
                    'resumen' => $t($d['nombre'] ?? '') . ' · ' . $t($d['nombre_negocio'] ?? ''),
                    'filas'   => [
                        'Nombre'   => $t($d['nombre'] ?? ''),
                        'Negocio'  => $t($d['nombre_negocio'] ?? ''),
                        'Email'    => $mail,
                        'Teléfono' => $t($d['telefono'] ?? ''),
                        'Ciudad'   => $t($d['ciudad'] ?? ''),
                        'Formulario' => ($d['origen'] ?? '') === 'landing' ? 'Página dedicada' : 'Ventana emergente',
                    ],
                    'bloques' => ['Mensaje' => $t($d['mensaje'] ?? '')],
                    'cta'     => ['texto' => 'Ver leads comerciales', 'url' => notifUrlPublica('admin/leads-comerciales.php')],
                    'cta2'    => $mail ? ['texto' => 'Responder', 'url' => 'mailto:' . $mail] : null,
                    'origen'  => $origen,
                    'reply_to' => $mail,
                ];

            case 'solicitud.recibida':
                $c = $d['contacto'] ?? []; $n = $d['negocio'] ?? []; $u = $d['ubicacion'] ?? []; $k = $d['contenido'] ?? []; $w = $d['urls'] ?? [];
                $mail = $t($c['email'] ?? '');
                $sid  = (int)($payload['solicitud_id'] ?? ($datos['solicitud_id'] ?? 0));
                return [
                    'tipo'    => 'Solicitud de alta de negocio',
                    'resumen' => $t($n['nombre'] ?? '') . ' · ' . $t($u['ciudad'] ?? '') . ' (solicitud nº ' . $sid . ')',
                    'filas'   => [
                        'Negocio'            => $t($n['nombre'] ?? ''),
                        'Contacto'           => $t($c['nombre'] ?? ''),
                        'Email contacto'     => $mail,
                        'Teléfono contacto'  => $t($c['telefono'] ?? ''),
                        'Email clientes'     => $t($n['email_clientes'] ?? ''),
                        'Teléfono clientes'  => $t($n['telefono_clientes'] ?? ''),
                        'Ubicación'          => implode(', ', array_filter([$t($u['direccion'] ?? ''), $t($u['codigo_postal'] ?? ''), $t($u['ciudad'] ?? ''), $t($u['provincia'] ?? ''), $t($u['comunidad'] ?? ''), $t($u['pais'] ?? '')])),
                        'Sitio web'          => $t($w['sitio_web'] ?? ''),
                        'Google Maps'        => $t($w['google_maps_url'] ?? ''),
                        'WhatsApp'           => $t($w['whatsapp'] ?? ''),
                        'Facebook'           => $t($w['facebook'] ?? ''),
                        'Instagram'          => $t($w['instagram'] ?? ''),
                    ],
                    'bloques' => [
                        'Descripción'           => $t($k['descripcion'] ?? ''),
                        'Servicios'             => $t($k['servicios'] ?? ''),
                        'Horarios'              => $t($k['horarios'] ?? ''),
                        'Precios'               => $t($k['precios'] ?? ''),
                        'Comentarios adicionales' => $t($k['comentarios_admin'] ?? ''),
                    ],
                    'cta'     => ['texto' => 'Revisar solicitud', 'url' => notifUrlPublica('admin/solicitud-ver.php?id=' . $sid)],
                    'cta2'    => $mail ? ['texto' => 'Responder', 'url' => 'mailto:' . $mail] : null,
                    'origen'  => $origen,
                    'reply_to' => $mail,
                ];
        }
        return null;
    }

    /** Solicitud de llamada de la campaña de verificación: se construye desde la fila de solicitudes_llamada. */
    function notifEquipoDesdeLlamada(array $l): array
    {
        $negocio = notifEquipoTxt($l['negocio_nombre'] ?? '');
        $cambio  = !empty($l['telefono_cambiado']);
        return [
            'tipo'    => 'Un negocio pide que le llamemos',
            'resumen' => $negocio . ' · ' . ($l['telefono_confirmado'] ?? ''),
            'filas'   => [
                'Negocio'            => $negocio,
                'Teléfono al que llamar' => (string)($l['telefono_confirmado'] ?? ''),
                'Cambio de teléfono' => $cambio
                    ? 'CAMBIADO por el negocio (ya es el público de la ficha). Antes: ' . (($l['telefono_original'] ?? '') !== '' ? $l['telefono_original'] : 'ninguno')
                    : 'Confirmado, sin cambios',
                'Cuándo'             => 'Lo antes posible, en horario comercial, de lunes a viernes',
            ],
            'bloques' => [],
            'cta'     => ['texto' => 'Ver llamadas por hacer', 'url' => notifUrlPublica('admin/llamadas-pendientes.php')],
            'cta2'    => !empty($l['negocio_slug']) ? ['texto' => 'Ver la ficha', 'url' => notifUrlPublica($l['negocio_slug'])] : null,
            'origen'  => '',
            'reply_to' => null,
        ];
    }

    /** Lead de la ficha o de la burbuja: se construye desde la fila de leads_b2c. */
    function notifEquipoDesdeLead(array $l): array
    {
        $canales = ['tel' => 'Llamar (teléfono)', 'wa' => 'WhatsApp', 'maps' => 'Cómo llegar (mapa)', 'web' => 'Sitio web'];
        $canal   = $canales[$l['channel_type'] ?? ''] ?? 'Sin canal concreto';
        $negocio = notifEquipoTxt($l['crematorio_nombre'] ?? '');
        $mail    = notifEquipoTxt($l['email'] ?? '');
        $wa      = trim(($l['phone_code'] ? '+' . $l['phone_code'] . ' ' : '') . ($l['whatsapp_number'] ?? ''));
        $utm     = implode(' / ', array_filter([$l['utm_source'] ?? '', $l['utm_medium'] ?? '', $l['utm_campaign'] ?? '']));

        return [
            'tipo'    => $negocio !== '' ? 'Lead desde la ficha de un negocio' : 'Lead desde la burbuja (sin ficha)',
            'resumen' => ($l['nombre'] ?? '') . ' · ' . ($negocio !== '' ? $negocio : 'consulta general') . ' · ' . $canal,
            'filas'   => [
                'Negocio'     => $negocio !== '' ? $negocio : 'Ninguno (consulta general)',
                'Botón pulsado' => $canal,
                'Nombre'      => (string)($l['nombre'] ?? ''),
                'Email'       => $mail,
                'WhatsApp'    => $wa,
                'Ciudad'      => (string)($l['ciudad_lead'] ?? ''),
                'Mascota'     => (string)($l['servicio'] ?? ''),
                'Tamaño'      => (string)($l['mascota_tamano'] ?? ''),
                'Página'      => (string)($l['pagina_origen'] ?? ''),
                'Campaña (UTM)' => $utm,
            ],
            'bloques' => ['Mensaje' => (string)($l['mensaje'] ?? '')],
            'cta'     => ['texto' => 'Ver en leads B2C', 'url' => notifUrlPublica('admin/leads-b2c.php')],
            'cta2'    => $mail ? ['texto' => 'Responder', 'url' => 'mailto:' . $mail] : null,
            'origen'  => '',
            'reply_to' => $mail,
        ];
    }
}
