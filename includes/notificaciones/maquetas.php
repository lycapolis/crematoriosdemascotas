<?php
/**
 * ═══════════════════════════════════════════════════════════
 * MAQUETAS DE NOTIFICACIONES — generador de previews
 * ═══════════════════════════════════════════════════════════
 *
 * Usado por admin/notif-leads-test.php (vía notif-preview-ajax.php).
 * Renderiza cada notificación con datos de EJEMPLO (y, opcionalmente, el nombre de un
 * negocio real) SIN enviar nada, SIN consultar leads reales y SIN tocar ningún estado.
 *
 *   notifMaquetaTipos(): array                       → catálogo de maquetas
 *   notifMaqueta(\PDO, string $tipo, array $p, string $emailDestino): array
 *       $p: plan ('00'..'03'), crem_id (int, opcional)
 *       return: ['ok'=>bool, 'tpl'=>['asunto','html','texto'], 'avisos'=>string[]]
 *
 * Al sumar una notificación nueva (equipo, WhatsApp…), se añade aquí un tipo más.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../notificaciones.php';          // lead + teaser (y mailer)
require_once __DIR__ . '/despachador.php';                // reseñas + URLs

if (!function_exists('notifMaqueta')) {

    function notifMaquetaTipos(): array
    {
        return [
            'lead_negocio'     => 'Lead nuevo al negocio',
            'teaser'           => 'Teaser regional (métricas de la provincia)',
            'resena_recibida'  => 'Reseña: «Hemos recibido tu reseña»',
            'resena_aprobada'  => 'Reseña: «Tu reseña ya es pública»',
            'resena_negocio'   => 'Reseña positiva: aviso al negocio',
            'respuesta_aprobada' => 'Respuesta del negocio publicada: aviso al negocio',
            'solicitud_recibida'            => 'Solicitud de alta: «Hemos recibido tu solicitud»',
            'solicitud_aprobada'            => 'Solicitud de alta: «Tu negocio ya está en el directorio»',
            'solicitud_rechazada'           => 'Solicitud de alta: rechazo con motivo',
            'solicitud_rechazada_sin_motivo' => 'Solicitud de alta: rechazo sin motivo',
            // Avisos internos al equipo
            'equipo_resena'        => 'Equipo: nueva reseña',
            'equipo_respuesta'     => 'Equipo: respuesta de un negocio a una reseña',
            'equipo_contacto'      => 'Equipo: mensaje de contacto',
            'equipo_promociona'    => 'Equipo: lead comercial «Promociona tu crematorio»',
            'equipo_registro'      => 'Equipo: solicitud de alta de negocio',
            'equipo_lead_ficha'    => 'Equipo: lead desde la ficha de un negocio',
            'equipo_lead_burbuja'  => 'Equipo: lead desde la burbuja (sin ficha)',
        ] + notifMaquetaTiposWebhook();
    }

    /** Un tipo de maqueta por evento del catálogo con canal webhook: «webhook:<evento>». */
    function notifMaquetaTiposWebhook(): array
    {
        $t = [];
        foreach (notifCatalogoEventos() as $clave => $def) {
            if (in_array('webhook', $def['canales'], true)) $t['webhook:' . $clave] = 'Webhook: ' . $def['titulo'];
        }
        return $t;
    }

    /** Payloads de EJEMPLO con la misma forma que los de producción (procesar-formulario / procesar-registro). */
    function notifMaquetaPayload(string $evento, array $neg): array
    {
        $ahora = date('c');
        $url   = notifUrlPublica(($neg['slug'] ?? 'ejemplo') . '?utm_source=google&utm_medium=organic');
        switch ($evento) {
            case 'resena.recibida':
                return ['form_id' => 'form_resena', 'form_name' => 'Reseña de Crematorio', 'formulario' => 'Formulario de Reseña',
                    'telefono' => '', 'timestamp' => $ahora, 'imagenes_guardadas' => 2,
                    'data' => ['nombre' => 'María G.', 'email' => 'maria@example.com',
                        'comentario' => "Nos atendieron con mucho cariño en un momento muy difícil.\nTodo fue claro y puntual.",
                        'calificacion' => 5, 'crematorio' => $neg['nombre'], 'crematorio_id' => (int)$neg['id'],
                        'resena_id' => 123, 'imagenes_adjuntas' => 2, 'estado' => 'pendiente'],
                    'page_url' => $url];
            case 'contacto.recibido':
                return ['form_id' => 'form_contacto', 'form_name' => 'Contacto General', 'formulario' => 'Formulario de Contacto General',
                    'telefono' => '600123456', 'timestamp' => $ahora,
                    'data' => ['nombre' => 'Carlos Pérez', 'email' => 'carlos@example.com', 'telefono' => '600123456',
                        'asunto' => 'Duda sobre un servicio', 'mensaje' => "Hola, quería preguntar si trabajáis en domingo.\nGracias."],
                    'page_url' => notifUrlPublica('contacto.php')];
            case 'lead_comercial.recibido':
                return ['form_id' => 'form_lead_comercial', 'form_name' => 'Lead comercial (Promocionar crematorio)',
                    'formulario' => 'Formulario de Promoción de Crematorio', 'telefono' => '655987654', 'timestamp' => $ahora,
                    'data' => ['nombre' => 'Lucía Martín', 'nombre_negocio' => 'Crematorio Ejemplo', 'email' => 'lucia@example.com',
                        'telefono' => '655987654', 'ciudad' => 'Valencia', 'mensaje' => 'Nos interesa aparecer como destacados en la provincia.', 'origen' => 'landing'],
                    'page_url' => notifUrlPublica('promociona-tu-crematorio.php')];
            case 'solicitud.recibida':
                return ['form_id' => 'form_registro_negocio', 'form_name' => 'Registro de Crematorio', 'timestamp' => $ahora, 'solicitud_id' => 123,
                    'data' => [
                        'contacto'  => ['nombre' => 'Carlos', 'email' => 'carlos@example.com', 'telefono' => '600123456'],
                        'negocio'   => ['nombre' => 'Crematorio Ejemplo', 'email_clientes' => 'info@ejemplo.com', 'telefono_clientes' => '910000000'],
                        'ubicacion' => ['pais' => 'España', 'comunidad' => 'Comunidad de Madrid', 'provincia' => 'Madrid', 'ciudad' => 'Madrid', 'direccion' => 'Calle Mayor 1', 'codigo_postal' => '28001'],
                        'contenido' => ['descripcion' => 'Crematorio familiar con más de 20 años de experiencia.', 'servicios' => "Cremación individual\nRecogida a domicilio", 'horarios' => 'L-V 9:00-18:00', 'precios' => '', 'comentarios_admin' => ''],
                        'urls'      => ['sitio_web' => 'https://ejemplo.com', 'google_maps_url' => '', 'whatsapp' => '600123456', 'facebook' => '', 'instagram' => ''],
                        'email'     => 'carlos@example.com'],
                    'page_url' => notifUrlPublica('registrar-negocio.php')];
            case 'lead_b2c.recibido':
                return notifMaquetaLeadPayload($neg, true);
        }
        return [];
    }

    /** Fila de leads_b2c de ejemplo (con o sin ficha). */
    function notifMaquetaLeadFila(array $neg, bool $conFicha): array
    {
        return ['id' => 456, 'channel_type' => 'wa', 'crematorio_id' => $conFicha ? (int)$neg['id'] : null,
            'crematorio_nombre' => $conFicha ? $neg['nombre'] : null, 'pagina_origen' => notifUrlPublica($conFicha ? ($neg['slug'] ?? 'ejemplo') : ''),
            'servicio' => 'Perro', 'mascota_tamano' => '5 - 15 kg', 'nombre' => 'María García', 'email' => 'maria@example.com',
            'phone_code' => '34', 'whatsapp_number' => '600123456', 'ciudad_lead' => $neg['ciudad'] ?: 'Madrid',
            'mensaje' => "Mi perro Toby falleció esta mañana y necesitamos coordinar el servicio para hoy mismo.\nGracias.",
            'utm_source' => 'google', 'utm_medium' => 'organic', 'utm_campaign' => ''];
    }

    /** JSON de Make del lead B2C (misma forma que procesar-lead-b2c.php). */
    function notifMaquetaLeadPayload(array $neg, bool $conFicha): array
    {
        $l = notifMaquetaLeadFila($neg, $conFicha);
        return ['id_formulario' => 'cmas-lead-capture-interno', 'formulario' => 'Formulario de Contacto - Ficha de Crematorio',
            'telefono' => '+' . $l['phone_code'] . $l['whatsapp_number'], 'idAgent' => $conFicha ? 'crematorio-' . $l['crematorio_id'] : 'generico',
            'channelType' => 'whatsapp', 'phoneAgent' => '', 'idStat' => (string)$l['id'], 'referrer' => '', 'referrerArrival' => $l['pagina_origen'],
            'sentDateUTC' => gmdate('c'), 'utmParams' => 'utm_source=google&utm_medium=organic', 'utmParams2' => '', 'priceCurrency' => 'EUR',
            'idService' => $conFicha ? (string)$l['crematorio_id'] : 'NA', 'Servicio' => '', 'Mascota' => $l['servicio'],
            'Tamaño de la Mascota' => $l['mascota_tamano'], 'Nombre' => $l['nombre'], 'Email' => $l['email'], 'countryCode' => 'ES',
            'phoneCode' => $l['phone_code'], 'WhatsApp Number' => $l['whatsapp_number'], 'Ciudad' => $l['ciudad_lead'], 'Mensaje' => $l['mensaje'],
            'ip' => '203.0.113.7', 'crematorio_id' => $l['crematorio_id'], 'crematorio_nombre' => $l['crematorio_nombre'] ?? '',
            'pagina_origen' => $l['pagina_origen'], 'destino_url' => 'https://wa.me/34600000000', 'lead_source' => 'cmas_interno_wa'];
    }

    /** `data` de ejemplo de los eventos con sobre normalizado (misma forma que notifWebhookData()). */
    function notifMaquetaDataSobre(string $evento, array $neg): array
    {
        $negocio = ['id' => (int)$neg['id'], 'nombre' => $neg['nombre'], 'slug' => $neg['slug'], 'plan' => $neg['tier']];
        switch ($evento) {
            case 'resena.aprobada':
                return ['resena_id' => 123, 'autor' => 'María G.', 'calificacion' => 5, 'comentario' => 'Nos atendieron con mucho cariño.', 'fuente' => 'propio',
                    'negocio' => $negocio + ['url' => notifUrlPublica($neg['slug'])]];
            case 'solicitud.aprobada':
                return ['solicitud_id' => 123, 'nombre_negocio' => $neg['nombre'],
                    'contacto' => ['nombre' => 'Carlos', 'email' => 'carlos@example.com', 'telefono' => '600123456'],
                    'ficha' => ['id' => (int)$neg['id'], 'slug' => $neg['slug'], 'url' => notifUrlPublica($neg['slug'])]];
            case 'solicitud.rechazada':
                return ['solicitud_id' => 123, 'nombre_negocio' => $neg['nombre'],
                    'contacto' => ['nombre' => 'Carlos', 'email' => 'carlos@example.com', 'telefono' => '600123456'],
                    'motivo' => 'No hemos podido comprobar que el negocio exista en la dirección indicada.'];
            case 'lead.entregado':
                return ['lead_id' => 456, 'canal_lead' => 'wa', 'entregado_en' => date('Y-m-d H:i:s'),
                    'variante' => in_array($neg['tier'], ['00', '01'], true) ? 'portal' : 'completa', 'negocio' => $negocio];
        }
        return [];
    }

    /** Vista previa del webhook de un evento: el JSON que recibirían los destinos. */
    function notifMaquetaWebhook(\PDO $pdo, string $evento, array $neg): ?array
    {
        $def = notifCatalogoEventos()[$evento] ?? null;
        if (!$def) return null;
        $cfg   = notifWebhookConfigEvento($pdo, $evento);
        $clave = ($def['clave'])(['resena_id' => 123, 'solicitud_id' => 123, 'lead_id' => 456, 'uid' => 'ejemplo0123456789', 'motivo' => '']);
        if (($def['webhook']['modo'] ?? 'sobre') === 'heredado') {
            $cuerpo = notifWebhookCuerpo($pdo, $evento, $def, $clave, ['payload' => notifMaquetaPayload($evento, $neg)], $cfg['extras']);
        } else {
            $cuerpo = ['evento' => $evento, 'version' => 1, 'id' => $clave, 'fecha' => date('c'),
                'origen' => parse_url(notifUrlPublica(), PHP_URL_HOST) ?: 'portal', 'data' => notifMaquetaDataSobre($evento, $neg)];
        }
        return ['json' => json_encode($cuerpo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'activo' => $cfg['activo'], 'modo' => $def['webhook']['modo'] ?? 'sobre', 'titulo' => $def['titulo']];
    }

    /** Negocio real (solo nombre/slug/ciudad/plan) o uno ficticio si no se elige ninguno. */
    function notifMaquetaNegocio(\PDO $pdo, int $cremId): array
    {
        if ($cremId > 0) {
            $st = $pdo->prepare("SELECT id, nombre, slug, tier, ciudad FROM crematorios WHERE id = ? LIMIT 1");
            $st->execute([$cremId]);
            $r = $st->fetch(\PDO::FETCH_ASSOC);
            if ($r) return $r + ['ficticio' => false];
        }
        return ['id' => 0, 'nombre' => 'Crematorio Ejemplo', 'slug' => 'ejemplo', 'tier' => '00', 'ciudad' => 'Madrid', 'ficticio' => true];
    }

    function notifMaqueta(\PDO $pdo, string $tipo, array $p, string $emailDestino = ''): array
    {
        $plan = in_array((string)($p['plan'] ?? ''), ['00', '01', '02', '03'], true) ? (string)$p['plan'] : '00';
        $neg  = notifMaquetaNegocio($pdo, (int)($p['crem_id'] ?? 0));
        $avisos = [];
        if ($neg['ficticio']) {
            $avisos[] = 'Negocio de ejemplo (ficticio). Elige uno real o pulsa «Al azar» para ver su nombre en el email.';
        }

        // Aviso interno: un negocio ha respondido a una reseña (no viene de payload ni de lead)
        if ($tipo === 'equipo_respuesta') {
            $p2 = [
                'tipo'    => 'Respuesta de un negocio (pendiente de moderar)',
                'resumen' => $neg['nombre'] . ' → reseña de María G. · 5★',
                'filas'   => ['Negocio' => $neg['nombre'], 'Reseña de' => 'María G.', 'Calificación' => notifEquipoEstrellas(5)],
                'bloques' => ['Reseña' => "Nos atendieron con mucho cariño en un momento muy difícil.\nTodo fue claro y puntual.",
                              'Respuesta del negocio' => 'Muchas gracias, María. Fue un honor acompañaros en ese momento.'],
                'cta'     => ['texto' => 'Moderar la respuesta', 'url' => notifUrlPublica('admin/respuestas-resenas.php')],
                'cta2'    => null, 'origen' => '', 'reply_to' => null,
            ];
            $avisos[] = 'Aviso interno: llega a ' . (defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'info@') . ' cuando un negocio envía una respuesta. No lleva enlace de baja.';
            return ['ok' => true, 'tpl' => renderEmailEquipoConversion($p2), 'avisos' => $avisos];
        }

        // Avisos internos al equipo
        if (strpos($tipo, 'equipo_') === 0) {
            $mapa = ['equipo_resena' => 'resena.recibida', 'equipo_contacto' => 'contacto.recibido',
                     'equipo_promociona' => 'lead_comercial.recibido', 'equipo_registro' => 'solicitud.recibida'];
            $p2  = isset($mapa[$tipo])
                ? notifEquipoDesdePayload($mapa[$tipo], notifMaquetaPayload($mapa[$tipo], $neg))
                : notifEquipoDesdeLead(notifMaquetaLeadFila($neg, $tipo === 'equipo_lead_ficha'));
            $tpl = renderEmailEquipoConversion($p2);
            $avisos[] = 'Aviso interno: llega a ' . (defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'info@') . ' con Reply-To = email de quien convierte. No lleva enlace de baja.';
            return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];
        }

        // Webhooks: el «email» es el JSON que recibirían los destinos
        if (strpos($tipo, 'webhook:') === 0) {
            $w = notifMaquetaWebhook($pdo, substr($tipo, 8), $neg);
            if (!$w) return ['ok' => false, 'tpl' => ['asunto' => '', 'html' => '', 'texto' => ''], 'avisos' => ['Evento desconocido.']];
            $avisos[] = $w['modo'] === 'heredado'
                ? 'JSON heredado: es el que Make ya recibía, intacto, con las variables extra activas añadidas al final.'
                : 'Sobre normalizado {evento, version, id, fecha, origen, data}.';
            if (!$w['activo']) $avisos[] = 'Este evento está DESACTIVADO en admin/webhooks.php: no se envía a ningún destino.';
            return ['ok' => true, 'tpl' => ['asunto' => 'Webhook · ' . $w['titulo'], 'html' => '', 'texto' => $w['json']], 'avisos' => $avisos, 'json' => true];
        }

        switch ($tipo) {
            case 'lead_negocio':
                $recibe    = json_decode(defined('TIERS_RECIBEN_LEAD') ? TIERS_RECIBEN_LEAD : '[]', true) ?: [];
                $completos = json_decode(defined('TIERS_LEAD_COMPLETO') ? TIERS_LEAD_COMPLETO : '[]', true) ?: [];
                $datosCompletos = in_array($plan, $completos, true);

                $tpl = renderEmailLeadNuevo(
                    [
                        'id' => 0, 'nombre' => 'María García', 'email' => 'maria@example.com',
                        'phone_code' => '34', 'whatsapp_number' => '600123456',
                        'ciudad' => $neg['ciudad'] ?: 'Madrid', 'servicio' => 'Perro', 'mascota_tamano' => '5 - 15 kg',
                        'mensaje' => "Buenas tardes. Mi perro Toby falleció esta mañana y necesitamos coordinar el servicio para hoy mismo si fuera posible.\n\nGracias.",
                        'channel_type' => 'wa', 'created_at' => date('Y-m-d H:i:s'),
                    ],
                    ['id' => $neg['id'], 'nombre' => $neg['nombre'], 'slug' => $neg['slug'], 'tier' => $plan],
                    $datosCompletos
                );
                if (in_array($plan, ['00', '01'], true)) {
                    $avisos[] = 'Variante «portal» (planes Gratis/Básico): el negocio ve el contacto ofuscado y puede pedirnos los datos.';
                } elseif ($datosCompletos) {
                    $avisos[] = 'Variante «completa» (plan Premium): email y teléfono visibles, botones de respuesta directa.';
                } else {
                    $avisos[] = 'Variante «ofuscada con invitación a mejorar» (plan Estándar).';
                }
                if (!in_array($plan, $recibe, true)) {
                    $avisos[] = 'Ojo: este plan NO está en TIERS_RECIBEN_LEAD, no recibiría este email.';
                }
                return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];

            case 'teaser':
                if (!$neg['ficticio']) {
                    $res = previewEmailTeaser($pdo, (int)$neg['id']);
                    if ($res['ok']) {
                        return ['ok' => true, 'tpl' => $res['tpl'], 'avisos' => array_merge($avisos, $res['avisos'])];
                    }
                }
                $avisos[] = 'Teaser con cifras de MUESTRA: las métricas reales son de la provincia del negocio y se calculan al elegir uno.';
                $tpl = renderEmailTeaserLeadsArea(
                    ['id' => 0, 'nombre' => $neg['nombre'], 'ciudad' => 'Madrid', 'provincia_nombre' => 'Madrid'],
                    [
                        'total_leads'  => 12,
                        'periodo_dias' => defined('TEASER_PERIODO_DIAS') ? (int)TEASER_PERIODO_DIAS : 30,
                        'por_ciudad'   => [['ciudad' => 'Madrid', 'count' => 7], ['ciudad' => 'Alcalá de Henares', 'count' => 5]],
                        'por_servicio' => ['Perro' => 8, 'Gato' => 4],
                        'leads_mockup' => [
                            ['ciudad' => 'Madrid',            'servicio' => 'Perro', 'tamano' => '15 - 30 kg'],
                            ['ciudad' => 'Madrid',            'servicio' => 'Gato',  'tamano' => '2 - 5 kg'],
                            ['ciudad' => 'Alcalá de Henares', 'servicio' => 'Perro', 'tamano' => '5 - 15 kg'],
                        ],
                    ]
                );
                return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];

            case 'solicitud_recibida':
            case 'solicitud_aprobada':
            case 'solicitud_rechazada':
            case 'solicitud_rechazada_sin_motivo':
                // El enlace «Dejar de recibir» es real para el email destino (para poder probarlo)
                $emailSol = filter_var($emailDestino, FILTER_VALIDATE_EMAIL) ? $emailDestino : 'carlos@example.com';
                $sol = ['id' => 123, 'contacto_nombre' => 'Carlos', 'nombre_negocio' => $neg['nombre']];
                if ($tipo === 'solicitud_recibida') {
                    $tpl = renderEmailSolicitudRecibida($sol, $emailSol);
                } elseif ($tipo === 'solicitud_aprobada') {
                    $tpl = renderEmailSolicitudAprobada($sol, $neg['slug'], $emailSol);
                } else {
                    $motivo = $tipo === 'solicitud_rechazada'
                        ? 'No hemos podido comprobar que el negocio exista en la dirección indicada. Si nos envías un enlace a su web o a su ficha de Google, lo revisamos.'
                        : null;
                    $tpl = renderEmailSolicitudRechazada($sol, $motivo, $emailSol);
                    if ($motivo === null) $avisos[] = 'El motivo es opcional: si el admin no lo escribe al rechazar, el email no incluye ese párrafo.';
                }
                return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];

            case 'resena_negocio':
                $minimo = defined('RESENA_AVISO_NEGOCIO_MIN_ESTRELLAS') ? (int)RESENA_AVISO_NEGOCIO_MIN_ESTRELLAS : 4;
                $avisos[] = "Se envía al aprobar una reseña del portal de {$minimo} o más estrellas, a todos los planes, una sola vez por reseña.";
                // El botón «Quiero responder a la reseña» lleva a responder-resena.php: para que la prueba
                // funcione de verdad se usa una reseña propia aprobada de este negocio, si la tiene.
                $resenaReal = 0;
                if (!$neg['ficticio']) {
                    $q = $pdo->prepare("SELECT id FROM resenas WHERE crematorio_id = ? AND fuente = 'propio' AND estado = 'aprobada' ORDER BY id DESC LIMIT 1");
                    $q->execute([(int)$neg['id']]);
                    $resenaReal = (int)$q->fetchColumn();
                }
                if ($resenaReal === 0) {
                    $avisos[] = 'Este negocio no tiene reseñas propias aprobadas: el botón «Quiero responder a la reseña» llevará a «Enlace no válido». Elige otro negocio para probar el formulario.';
                }
                $tpl = renderEmailResenaNegocio(
                    ['id' => $resenaReal, 'nombre' => 'María G.', 'calificacion' => 5,
                     'comentario' => "Nos atendieron con mucho cariño y respeto en un momento muy difícil. Todo fue claro y puntual. Se lo recomiendo a cualquiera que pase por esto."],
                    ['nombre' => $neg['nombre'], 'slug' => $neg['slug']],
                    filter_var($emailDestino, FILTER_VALIDATE_EMAIL) ? $emailDestino : 'negocio@example.com'
                );
                return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];

            case 'respuesta_aprobada':
                $avisos[] = 'Se envía al aprobar la respuesta de un negocio en admin/respuestas-resenas.php, una sola vez por respuesta.';
                $tpl = renderEmailRespuestaAprobada(
                    ['nombre' => 'María G.', 'calificacion' => 5, 'comentario' => 'Nos atendieron con mucho cariño.'],
                    'Muchas gracias, María. Fue un honor acompañaros en ese momento.',
                    ['nombre' => $neg['nombre'], 'slug' => $neg['slug']],
                    filter_var($emailDestino, FILTER_VALIDATE_EMAIL) ? $emailDestino : 'negocio@example.com'
                );
                return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];

            case 'resena_recibida':
            case 'resena_aprobada':
                // El enlace «Dejar de recibir» es real para el email destino (para poder probarlo)
                $usuario = [
                    'nombre' => 'María', 'calificacion' => 5,
                    'email'  => filter_var($emailDestino, FILTER_VALIDATE_EMAIL) ? $emailDestino : 'maria@example.com',
                ];
                $negocio = ['nombre' => $neg['nombre'], 'slug' => $neg['slug']];
                $tpl = $tipo === 'resena_aprobada'
                    ? renderEmailResenaAprobada($usuario, $negocio)
                    : renderEmailResenaRecibida($usuario, $negocio);
                return ['ok' => true, 'tpl' => $tpl, 'avisos' => $avisos];
        }

        return ['ok' => false, 'tpl' => ['asunto' => '', 'html' => '', 'texto' => ''], 'avisos' => ['Tipo de maqueta desconocido.']];
    }
}
