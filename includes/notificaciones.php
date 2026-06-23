<?php
/**
 * ═══════════════════════════════════════════════════════════
 * NOTIFICACIONES — orquestación de envíos al negocio
 * ═══════════════════════════════════════════════════════════
 *
 * Capa de decisión: "este lead ¿se notifica al negocio? ¿cómo?".
 * El transporte (SMTP / mail()) está en includes/mailer.php.
 * La plantilla HTML está en includes/plantillas-email/lead-nuevo-negocio.php.
 *
 * Funciones públicas:
 *   notificarNegocioLead(\PDO $pdo, int $leadId): array
 *     - Decide si el lead es notificable (tier, opt-in, throttle)
 *     - Render + envío
 *     - Marca leads_b2c.negocio_notificado = 1 si OK
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/plantillas-email/lead-nuevo-negocio.php';
require_once __DIR__ . '/plantillas-email/teaser-leads-area.php';

if (!function_exists('notificarNegocioLead')) {

    /**
     * Procesa una notificación al negocio por un lead recién creado.
     * No bloqueante: cualquier error se loguea pero no rompe el flujo principal.
     *
     * @return array{ok:bool, motivo?:string, transporte?:string}
     *   motivo posibles: 'sin_crematorio', 'tier_no_elegible', 'opt_out',
     *                    'sin_email', 'throttle', 'envio_fallido', 'ya_notificado'
     */
    function notificarNegocioLead(\PDO $pdo, int $leadId): array
    {
        // 1. Cargar el lead + datos del negocio en un solo query
        $stmt = $pdo->prepare("
            SELECT
                l.id, l.crematorio_id, l.channel_type, l.servicio, l.mascota_tamano,
                l.nombre, l.email, l.country_code, l.phone_code, l.whatsapp_number,
                l.ciudad_lead AS ciudad, l.mensaje, l.created_at,
                l.negocio_notificado,
                c.nombre   AS crem_nombre,
                c.slug     AS crem_slug,
                c.tier     AS crem_tier,
                c.email                AS crem_email_publico,
                c.email_notif_leads    AS crem_email_notif,
                c.recibe_notif_leads   AS crem_opt_in,
                c.emails_json          AS crem_emails_json
            FROM leads_b2c l
            LEFT JOIN crematorios c ON c.id = l.crematorio_id
            WHERE l.id = ?
            LIMIT 1
        ");
        $stmt->execute([$leadId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'motivo' => 'lead_no_existe'];
        }

        if (!$row['crematorio_id']) {
            return ['ok' => false, 'motivo' => 'sin_crematorio']; // lead genérico (burbuja sin ficha)
        }

        if ((int)$row['negocio_notificado'] === 1) {
            return ['ok' => false, 'motivo' => 'ya_notificado'];
        }

        // 2. Gate por tier
        $tiersReciben = json_decode(defined('TIERS_RECIBEN_LEAD') ? TIERS_RECIBEN_LEAD : '[]', true) ?: [];
        $tier         = (string)($row['crem_tier'] ?? '');
        if (!in_array($tier, $tiersReciben, true)) {
            return ['ok' => false, 'motivo' => 'tier_no_elegible'];
        }

        // 3. Opt-in del negocio
        if ((int)($row['crem_opt_in'] ?? 1) !== 1) {
            return ['ok' => false, 'motivo' => 'opt_out'];
        }

        // 4. Resolver email destino (fallback en cascada)
        $emailDestino = resolverEmailDestinoNegocio($row);
        if (!$emailDestino) {
            return ['ok' => false, 'motivo' => 'sin_email'];
        }

        // 5. Throttle anti-flood (consultar última notif al mismo negocio)
        $throttleSeg = defined('LEAD_NOTIF_THROTTLE_SEG') ? (int)LEAD_NOTIF_THROTTLE_SEG : 60;
        if ($throttleSeg > 0) {
            $stmtThrottle = $pdo->prepare("
                SELECT MAX(negocio_notificado_at) AS ultima
                FROM leads_b2c
                WHERE crematorio_id = ?
                  AND negocio_notificado = 1
                  AND id <> ?
            ");
            $stmtThrottle->execute([$row['crematorio_id'], $leadId]);
            $ultima = $stmtThrottle->fetchColumn();
            if ($ultima && (time() - strtotime($ultima)) < $throttleSeg) {
                return ['ok' => false, 'motivo' => 'throttle'];
            }
        }

        // 6. Datos completos vs enmascarados según tier
        $tiersCompletos = json_decode(defined('TIERS_LEAD_COMPLETO') ? TIERS_LEAD_COMPLETO : '[]', true) ?: [];
        $datosCompletos = in_array($tier, $tiersCompletos, true);

        // 7. Render plantilla
        $tpl = renderEmailLeadNuevo(
            [
                'id'              => $row['id'],
                'nombre'          => $row['nombre'],
                'email'           => $row['email'],
                'country_code'    => $row['country_code'],
                'phone_code'      => $row['phone_code'],
                'whatsapp_number' => $row['whatsapp_number'],
                'ciudad'          => $row['ciudad'],
                'servicio'        => $row['servicio'],
                'mascota_tamano'  => $row['mascota_tamano'],
                'mensaje'         => $row['mensaje'],
                'channel_type'    => $row['channel_type'],
                'created_at'      => $row['created_at'],
            ],
            [
                'id'     => $row['crematorio_id'],
                'nombre' => $row['crem_nombre'],
                'slug'   => $row['crem_slug'],
                'tier'   => $tier,
            ],
            $datosCompletos
        );

        // 8. Reply-To al email del lead (para que el negocio responda directo si tier alto)
        $replyTo = $datosCompletos ? (string)$row['email'] : null;

        // 9. Enviar
        $envio = enviarMailHtml(
            $emailDestino,
            $tpl['asunto'],
            $tpl['html'],
            $tpl['texto'],
            $replyTo,
            $row['crem_nombre']
        );

        if (!$envio['ok']) {
            error_log("[notif lead {$leadId}] envío fallido: " . ($envio['error'] ?? '?'));
            return ['ok' => false, 'motivo' => 'envio_fallido', 'transporte' => $envio['transporte'] ?? '?'];
        }

        // 10. Marcar lead como notificado
        $pdo->prepare("UPDATE leads_b2c SET negocio_notificado = 1, negocio_notificado_at = NOW() WHERE id = ?")
            ->execute([$leadId]);

        return ['ok' => true, 'transporte' => $envio['transporte']];
    }

    /**
     * Decide qué email del negocio usar para notificarle un lead.
     * Cascada:
     *   1. email_notif_leads (campo dedicado, prioritario)
     *   2. emails_json → primer email de tipo 'general'
     *   3. email (campo plano público)
     */
    function resolverEmailDestinoNegocio(array $crem): ?string
    {
        $candidatos = [];

        if (!empty($crem['crem_email_notif'])) {
            $candidatos[] = trim($crem['crem_email_notif']);
        }

        if (!empty($crem['crem_emails_json'])) {
            $emails = json_decode($crem['crem_emails_json'], true);
            if (is_array($emails)) {
                foreach ($emails as $e) {
                    if (!empty($e['email']) && (($e['tipo'] ?? 'general') === 'general')) {
                        $candidatos[] = trim($e['email']);
                    }
                }
            }
        }

        if (!empty($crem['crem_email_publico'])) {
            $candidatos[] = trim($crem['crem_email_publico']);
        }

        foreach ($candidatos as $c) {
            if ($c && filter_var($c, FILTER_VALIDATE_EMAIL)) return $c;
        }
        return null;
    }

    // ═══════════════════════════════════════════════════════════
    // TEASER OFUSCADO — Email batch a negocios sin tier de pago
    // ═══════════════════════════════════════════════════════════

    /**
     * Envía el teaser ofuscado a UN negocio.
     * No bloqueante: cualquier error se loguea y se devuelve motivo.
     *
     * @return array{ok:bool, motivo?:string, transporte?:string, stats?:array}
     *   motivos: 'sin_negocio', 'tier_no_objetivo', 'opt_out', 'sin_email',
     *            'throttle_frecuencia', 'sin_leads_area', 'envio_fallido', 'dry_run'
     */
    function enviarTeaserLeadsArea(\PDO $pdo, int $crematorioId, bool $dryRun = false, ?string $emailOverride = null): array
    {
        // 1. Datos del negocio
        $stmt = $pdo->prepare("
            SELECT c.id, c.nombre, c.tier, c.ciudad, c.provincia_id,
                   c.email, c.email_notif_leads, c.recibe_notif_leads,
                   c.emails_json, c.teaser_ultimo_envio,
                   p.nombre AS provincia_nombre
            FROM crematorios c
            LEFT JOIN provincias p ON c.provincia_id = p.id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$crematorioId]);
        $crem = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$crem) return ['ok' => false, 'motivo' => 'sin_negocio'];

        // 2. Gate por tier objetivo
        $tiersObjetivo = json_decode(defined('TEASER_TIERS_OBJETIVO') ? TEASER_TIERS_OBJETIVO : '[]', true) ?: [];
        if (!in_array((string)$crem['tier'], $tiersObjetivo, true)) {
            return ['ok' => false, 'motivo' => 'tier_no_objetivo'];
        }

        // 3. Opt-in
        if ((int)($crem['recibe_notif_leads'] ?? 1) !== 1) {
            return ['ok' => false, 'motivo' => 'opt_out'];
        }

        // 4. Throttle por frecuencia (no enviar dos teasers seguidos)
        $frecDias = defined('TEASER_FRECUENCIA_DIAS') ? (int)TEASER_FRECUENCIA_DIAS : 30;
        if (!$dryRun && !empty($crem['teaser_ultimo_envio']) && $frecDias > 0) {
            $ultEnvioTs = strtotime($crem['teaser_ultimo_envio']);
            if ($ultEnvioTs && (time() - $ultEnvioTs) < ($frecDias * 86400)) {
                return ['ok' => false, 'motivo' => 'throttle_frecuencia'];
            }
        }

        // 5. Resolver email destino
        $emailDestino = $emailOverride ?: resolverEmailDestinoNegocio([
            'crem_email_notif'    => $crem['email_notif_leads'],
            'crem_emails_json'    => $crem['emails_json'],
            'crem_email_publico'  => $crem['email'],
        ]);
        if (!$emailDestino) return ['ok' => false, 'motivo' => 'sin_email'];

        // 6. Stats de leads en el área (ventana últimos N días)
        $stats = calcularStatsLeadsArea($pdo, $crem);

        $minLeads = defined('TEASER_MIN_LEADS_AREA') ? (int)TEASER_MIN_LEADS_AREA : 1;
        if ($stats['total_leads'] < $minLeads) {
            return ['ok' => false, 'motivo' => 'sin_leads_area', 'stats' => $stats];
        }

        // 7. Render plantilla
        $tpl = renderEmailTeaserLeadsArea(
            [
                'id'               => $crem['id'],
                'nombre'           => $crem['nombre'],
                'ciudad'           => $crem['ciudad'],
                'provincia_nombre' => $crem['provincia_nombre'],
            ],
            $stats
        );

        if ($dryRun) {
            return ['ok' => true, 'motivo' => 'dry_run', 'stats' => $stats, 'preview' => $tpl];
        }

        // 8. Envío
        $envio = enviarMailHtml($emailDestino, $tpl['asunto'], $tpl['html'], $tpl['texto'], null, $crem['nombre']);
        if (!$envio['ok']) {
            error_log("[teaser crem {$crematorioId}] envío fallido: " . ($envio['error'] ?? '?'));
            return ['ok' => false, 'motivo' => 'envio_fallido', 'transporte' => $envio['transporte'] ?? '?'];
        }

        // 9. Marcar último envío
        $pdo->prepare("UPDATE crematorios SET teaser_ultimo_envio = NOW() WHERE id = ?")
            ->execute([$crematorioId]);

        return ['ok' => true, 'transporte' => $envio['transporte'], 'stats' => $stats];
    }

    /**
     * Calcula stats de leads en el área del negocio (ventana últimos N días).
     * Área = misma provincia. Si el negocio no tiene provincia_id, retorna stats vacíos.
     */
    function calcularStatsLeadsArea(\PDO $pdo, array $crem): array
    {
        $periodoDias = defined('TEASER_PERIODO_DIAS') ? (int)TEASER_PERIODO_DIAS : 30;
        $provinciaId = (int)($crem['provincia_id'] ?? 0);

        $stats = [
            'total_leads'  => 0,
            'periodo_dias' => $periodoDias,
            'por_ciudad'   => [],
            'por_servicio' => [],
            'leads_mockup' => [],
        ];

        if ($provinciaId < 1) return $stats;

        // Leads cuyo crematorio_id pertenece a la misma provincia que el receptor
        $sql = "SELECT l.servicio, l.mascota_tamano, l.ciudad_lead,
                       c.ciudad AS crem_ciudad
                FROM leads_b2c l
                LEFT JOIN crematorios c ON c.id = l.crematorio_id
                WHERE l.created_at >= NOW() - INTERVAL :dias DAY
                  AND (c.provincia_id = :pid OR l.ciudad_lead IS NOT NULL)";
        // Nota: incluímos también leads "genéricos" (sin crematorio_id) cuya ciudad_lead
        // coincide con la provincia — los filtramos en PHP por nombre de ciudad.

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':dias' => $periodoDias, ':pid' => $provinciaId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Filtrar leads relevantes y agregar
        $porCiudad   = [];
        $porServicio = [];
        $mockup      = [];

        foreach ($rows as $r) {
            $ciudad = $r['crem_ciudad'] ?? $r['ciudad_lead'] ?? null;
            if (!$ciudad) continue;

            // Para "leads genéricos sin crem_id" exigimos que la ciudad_lead coincida con
            // alguna ciudad de la provincia del receptor — usamos crem_ciudad si existe.
            // (Simplificación: si crem_ciudad ya está, asumimos misma provincia por el JOIN)
            if (empty($r['crem_ciudad']) && !empty($r['ciudad_lead'])) {
                // Skipped: no podemos confirmar misma provincia desde lead suelto.
                // Para evitar falsos positivos, ignoramos los leads sin crematorio_id.
                continue;
            }

            $stats['total_leads']++;
            $porCiudad[$ciudad]   = ($porCiudad[$ciudad] ?? 0) + 1;
            $svc = $r['servicio'] ?: 'Otro';
            $porServicio[$svc]    = ($porServicio[$svc] ?? 0) + 1;

            if (count($mockup) < 4) {
                $mockup[] = [
                    'ciudad'   => $ciudad,
                    'servicio' => $svc,
                    'tamano'   => $r['mascota_tamano'] ?? '',
                ];
            }
        }

        // Ordenar y formatear
        arsort($porCiudad);
        arsort($porServicio);

        $stats['por_ciudad'] = array_map(
            fn($ciu, $cnt) => ['ciudad' => $ciu, 'count' => $cnt],
            array_keys($porCiudad),
            array_values($porCiudad)
        );
        $stats['por_servicio'] = $porServicio;
        $stats['leads_mockup'] = $mockup;

        return $stats;
    }
}
