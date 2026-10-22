<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CATÁLOGO DE EVENTOS DE NOTIFICACIÓN
 * ═══════════════════════════════════════════════════════════
 *
 * Un solo sitio donde se declara qué pasa cuando ocurre un evento.
 * El código que lo dispara solo llama a notificarEvento($pdo, 'clave', [datos]).
 *
 * Campos de cada evento:
 *   titulo             → nombre legible (panel admin/webhooks.php)
 *   canales            → lista de canales que lo reciben (ver despachador.php y canales/)
 *                        webhook → destinos de admin/webhooks.php · email_equipo → aviso interno
 *   clave              → callable(array $datos): string  — idempotencia: misma clave
 *                        + mismo canal + mismo destinatario = se envía UNA sola vez
 *   reactiva_supresion → true si recibir este evento cuenta como "el usuario volvió a
 *                        escribirnos" y reactiva sus avisos tras una baja
 *   webhook            → cómo viaja por el canal webhook:
 *                          modo 'heredado' → el JSON que Make ya recibe (en $datos['payload']),
 *                                            intacto, con variables extra añadidas al final
 *                          modo 'sobre'    → sobre normalizado {evento, version, id, fecha, origen, data}
 *                          lista true      → el cuerpo se envía envuelto en [ {…} ] (lead B2C, como antes)
 *
 * Los clics anónimos («Ir directo», cerrar el modal) NO son eventos: no van por webhook.
 * ═══════════════════════════════════════════════════════════
 */

if (!function_exists('notifCatalogoEventos')) {

    function notifCatalogoEventos(): array
    {
        return [
            // ── Conversiones del sitio (JSON heredado de Make + aviso al equipo) ──
            'resena.recibida' => [
                'titulo'             => 'Reseña recibida',
                'canales'            => ['webhook', 'email_equipo', 'email_usuario'],
                'clave'              => fn(array $d) => 'resena.recibida:' . (int)$d['resena_id'],
                'reactiva_supresion' => true,
                'webhook'            => ['modo' => 'heredado'],
            ],
            'contacto.recibido' => [
                'titulo'             => 'Formulario de contacto',
                'canales'            => ['webhook', 'email_equipo'],
                'clave'              => fn(array $d) => 'contacto.recibido:' . (string)$d['uid'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'heredado'],
            ],
            'lead_comercial.recibido' => [
                'titulo'             => 'Promociona tu crematorio (lead comercial)',
                'canales'            => ['webhook', 'email_equipo'],
                'clave'              => fn(array $d) => 'lead_comercial.recibido:' . (string)$d['uid'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'heredado'],
            ],
            'solicitud.recibida' => [
                'titulo'             => 'Registro de negocio (solicitud recibida)',
                'canales'            => ['webhook', 'email_equipo', 'email_solicitante'],
                'clave'              => fn(array $d) => 'solicitud.recibida:' . (int)$d['solicitud_id'],
                'reactiva_supresion' => true,
                'webhook'            => ['modo' => 'heredado'],
            ],
            'lead_b2c.recibido' => [
                'titulo'             => 'Lead de la ficha o de la burbuja',
                'canales'            => ['webhook', 'email_equipo'],
                'clave'              => fn(array $d) => 'lead_b2c.recibido:' . (int)$d['lead_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'heredado', 'lista' => true],
            ],

            // Confirmación al visitante que deja sus datos (ficha o burbuja). Un email por ficha y día:
            // la clave lleva la ficha (0 = burbuja) y el día; el destinatario completa la unicidad.
            'lead_b2c.confirmacion' => [
                'titulo'             => 'Confirmación al visitante que deja sus datos',
                'canales'            => ['email_usuario'],
                'clave'              => fn(array $d) => 'lead_b2c.confirmacion:' . (int)($d['crematorio_id'] ?? 0) . ':' . (string)($d['dia'] ?? date('Ymd')),
                'reactiva_supresion' => false,   // quien se dio de baja no recibe nada aunque vuelva a escribir
                'webhook'            => ['modo' => 'sobre'],
            ],

            // ── Ciclo de vida (sobre normalizado) ──
            'resena.aprobada' => [
                'titulo'             => 'Reseña aprobada',
                'canales'            => ['webhook', 'email_usuario', 'email_negocio'],   // autor + negocio (si es positiva)
                // Una sola vez por reseña aunque se pause y se vuelva a aprobar
                'clave'              => fn(array $d) => 'resena.aprobada:' . (int)$d['resena_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            'solicitud.aprobada' => [
                'titulo'             => 'Solicitud de alta aprobada',
                'canales'            => ['webhook', 'email_solicitante'],
                'clave'              => fn(array $d) => 'solicitud.aprobada:' . (int)$d['solicitud_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            'solicitud.rechazada' => [
                'titulo'             => 'Solicitud de alta rechazada',
                'canales'            => ['webhook', 'email_solicitante'],
                // Si se re-evalúa y se rechaza con OTRO motivo se vuelve a avisar; con el mismo, no
                'clave'              => fn(array $d) => 'solicitud.rechazada:' . (int)$d['solicitud_id'] . ':' . substr(md5((string)($d['motivo'] ?? '')), 0, 8),
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            'lead.entregado' => [
                'titulo'             => 'Lead entregado al negocio',
                'canales'            => ['webhook'],
                'clave'              => fn(array $d) => 'lead.entregado:' . (int)$d['lead_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            // ── Respuesta del negocio a una reseña (siempre moderada) ──
            'respuesta.recibida' => [
                'titulo'             => 'Respuesta del negocio recibida (pendiente de moderar)',
                'canales'            => ['email_equipo'],     // aviso interno; no va a webhook ni al negocio
                'clave'              => fn(array $d) => 'respuesta.recibida:' . (int)$d['respuesta_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            'respuesta.aprobada' => [
                'titulo'             => 'Respuesta del negocio aprobada',
                'canales'            => ['email_negocio'],
                'clave'              => fn(array $d) => 'respuesta.aprobada:' . (int)$d['respuesta_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            // ── Campaña de prospección: un negocio pide que le llamemos (aviso interno, sin webhook) ──
            'llamada.solicitada' => [
                'titulo'             => 'Un negocio pide que le llamemos (campaña de verificación)',
                'canales'            => ['email_equipo'],
                'clave'              => fn(array $d) => 'llamada.solicitada:' . (int)$d['llamada_id'],
                'reactiva_supresion' => false,
                'webhook'            => ['modo' => 'sobre'],
            ],
            // 'resena.rechazada' → deliberadamente SIN aviso al usuario (decisión del 2026-10-12)
        ];
    }

    /**
     * Variables que el canal webhook AÑADE al final del JSON heredado (nunca pisa una que ya exista).
     * El panel admin/webhooks.php permite activar/desactivar cada una por evento.
     */
    function notifWebhookExtrasCatalogo(): array
    {
        return [
            'evento'         => 'Clave del evento (ej. resena.recibida)',
            'evento_version' => 'Versión del formato del evento (1)',
            'evento_id'      => 'Identificador estable del evento (sirve para evitar duplicados en el receptor)',
            'evento_fecha'   => 'Fecha y hora del envío (ISO 8601)',
            'evento_origen'  => 'Dominio del portal que lo envía',
            'sitio_url'      => 'URL base del portal',
        ];
    }
}
