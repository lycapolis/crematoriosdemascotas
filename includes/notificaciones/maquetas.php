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
            'solicitud_recibida'            => 'Solicitud de alta: «Hemos recibido tu solicitud»',
            'solicitud_aprobada'            => 'Solicitud de alta: «Tu negocio ya está en el directorio»',
            'solicitud_rechazada'           => 'Solicitud de alta: rechazo con motivo',
            'solicitud_rechazada_sin_motivo' => 'Solicitud de alta: rechazo sin motivo',
        ];
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
                $tpl = renderEmailResenaNegocio(
                    ['id' => 0, 'nombre' => 'María G.', 'calificacion' => 5,
                     'comentario' => "Nos atendieron con mucho cariño y respeto en un momento muy difícil. Todo fue claro y puntual. Se lo recomiendo a cualquiera que pase por esto."],
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
