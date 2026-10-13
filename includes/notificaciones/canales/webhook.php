<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: webhook (Make, n8n, CRM…)
 * ═══════════════════════════════════════════════════════════
 *
 * Envía el payload de un evento a TODOS los destinos activos de
 * notificaciones_webhook_destinos que estén suscritos a él (panel: admin/webhooks.php).
 * Cada destino se registra por separado en notificaciones_log (destinatario «wh:<id>»).
 *
 * Dos formatos (se declaran por evento en eventos.php):
 *   heredado → el JSON que Make ya recibía, INTACTO (llega en $datos['payload']), con
 *              variables extra añadidas al final (evento, evento_version, evento_id,
 *              evento_fecha, evento_origen, sitio_url). Nunca pisa una clave existente.
 *   sobre    → {evento, version, id, fecha, origen, data} para los eventos nuevos.
 *
 * Respaldo: si la tabla de destinos no existe aún o está vacía, se usa WEBHOOK_URLS (config),
 * para que ningún envío se pierda antes de correr la migración.
 *
 * Cabeceras: X-Evento, X-Evento-Id y, si el destino tiene secreto, X-Firma: sha256=<hmac del cuerpo>.
 * ═══════════════════════════════════════════════════════════
 */

if (!function_exists('notifCanal_webhook')) {

    function notifCanal_webhook(\PDO $pdo, string $evento, array $datos): array
    {
        $catalogo = notifCatalogoEventos();
        $def      = $catalogo[$evento] ?? null;
        if (!$def) return ['omitir' => 'evento_desconocido'];

        $cfg = notifWebhookConfigEvento($pdo, $evento);
        if (!$cfg['activo']) return ['omitir' => 'evento_desactivado'];

        $destinos = notifWebhookDestinos($pdo, $evento);
        if (!$destinos) return ['omitir' => 'sin_destinos'];

        $clave  = ($def['clave'])($datos);
        $cuerpo = notifWebhookCuerpo($pdo, $evento, $def, $clave, $datos, $cfg['extras']);
        if ($cuerpo === null) return ['omitir' => 'sin_datos'];

        $json = json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $multiples = [];
        foreach ($destinos as $d) {
            $multiples[] = [
                'destinatario' => $d['clave_dest'],
                'enviar'       => fn() => notifWebhookPost($d, $json, $evento, $clave),
            ];
        }
        return ['multiples' => $multiples];
    }

    /** Ajustes del evento: activo (por defecto sí) y variables extra activas (por defecto todas). */
    function notifWebhookConfigEvento(\PDO $pdo, string $evento): array
    {
        $todas = array_keys(notifWebhookExtrasCatalogo());
        try {
            $st = $pdo->prepare("SELECT activo, extras FROM notificaciones_webhook_eventos WHERE evento = ? LIMIT 1");
            $st->execute([$evento]);
            $f = $st->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $f = false;   // tabla sin migrar → valores por defecto
        }
        if (!$f) return ['activo' => true, 'extras' => $todas];
        $extras = ($f['extras'] === null || $f['extras'] === '') ? $todas : (json_decode($f['extras'], true) ?: []);
        return ['activo' => (int)$f['activo'] === 1, 'extras' => array_values(array_intersect($todas, $extras))];
    }

    /**
     * Destinos activos suscritos al evento.
     * @return array<int, array{clave_dest:string, nombre:string, url:string, secreto:?string}>
     */
    function notifWebhookDestinos(\PDO $pdo, string $evento): array
    {
        $filas = null;
        try {
            $total = (int)$pdo->query("SELECT COUNT(*) FROM notificaciones_webhook_destinos")->fetchColumn();
            if ($total > 0) {
                $filas = $pdo->query("SELECT id, nombre, url, secreto, eventos FROM notificaciones_webhook_destinos WHERE activo = 1 ORDER BY id")
                             ->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            $filas = null;
        }

        $out = [];
        if ($filas === null) {
            // Respaldo: WEBHOOK_URLS del config
            $urls = json_decode(defined('WEBHOOK_URLS') ? WEBHOOK_URLS : '[]', true) ?: [];
            foreach (array_values($urls) as $i => $url) {
                if (!is_string($url) || $url === '') continue;
                $out[] = ['clave_dest' => 'wh:cfg' . ($i + 1), 'nombre' => 'Config ' . ($i + 1), 'url' => $url, 'secreto' => null];
            }
            return $out;
        }

        foreach ($filas as $f) {
            $suscritos = ($f['eventos'] === null || $f['eventos'] === '') ? null : (json_decode($f['eventos'], true) ?: []);
            if ($suscritos !== null && !in_array($evento, $suscritos, true)) continue;
            $out[] = ['clave_dest' => 'wh:' . (int)$f['id'], 'nombre' => $f['nombre'], 'url' => $f['url'], 'secreto' => $f['secreto'] ?: null];
        }
        return $out;
    }

    /** Cuerpo a enviar: JSON heredado + extras, o sobre normalizado. null si no hay datos. */
    function notifWebhookCuerpo(\PDO $pdo, string $evento, array $def, string $clave, array $datos, array $extrasActivos): ?array
    {
        $modo   = $def['webhook']['modo'] ?? 'sobre';
        $origen = parse_url(notifUrlPublica(), PHP_URL_HOST) ?: 'portal';

        if ($modo === 'heredado') {
            $p = $datos['payload'] ?? null;
            if (!is_array($p)) return null;
            $extra = [
                'evento'         => $evento,
                'evento_version' => 1,
                'evento_id'      => $clave,
                'evento_fecha'   => date('c'),
                'evento_origen'  => $origen,
                'sitio_url'      => rtrim(notifUrlPublica(), '/'),
            ];
            foreach ($extra as $k => $v) {
                if (in_array($k, $extrasActivos, true) && !array_key_exists($k, $p)) $p[$k] = $v;
            }
            return !empty($def['webhook']['lista']) ? [$p] : $p;
        }

        $data = notifWebhookData($pdo, $evento, $datos);
        if ($data === null) return null;
        return [
            'evento'  => $evento,
            'version' => 1,
            'id'      => $clave,
            'fecha'   => date('c'),
            'origen'  => $origen,
            'data'    => $data,
        ];
    }

    /** Contenido de `data` para los eventos de ciclo de vida (sobre normalizado). */
    function notifWebhookData(\PDO $pdo, string $evento, array $datos): ?array
    {
        switch ($evento) {
            case 'resena.aprobada':
                $st = $pdo->prepare("SELECT r.id, r.nombre, r.calificacion, r.comentario, r.fuente, r.estado,
                                            c.id AS neg_id, c.nombre AS neg_nombre, c.slug AS neg_slug, c.tier AS neg_tier
                                     FROM resenas r JOIN crematorios c ON c.id = r.crematorio_id WHERE r.id = ? LIMIT 1");
                $st->execute([(int)($datos['resena_id'] ?? 0)]);
                $r = $st->fetch(\PDO::FETCH_ASSOC);
                if (!$r) return null;
                return [
                    'resena_id'    => (int)$r['id'],
                    'autor'        => $r['nombre'],
                    'calificacion' => (int)$r['calificacion'],
                    'comentario'   => $r['comentario'],
                    'fuente'       => $r['fuente'],
                    'negocio'      => ['id' => (int)$r['neg_id'], 'nombre' => $r['neg_nombre'], 'slug' => $r['neg_slug'],
                                       'plan' => $r['neg_tier'], 'url' => notifUrlPublica((string)$r['neg_slug'])],
                ];

            case 'solicitud.aprobada':
            case 'solicitud.rechazada':
                $st = $pdo->prepare("SELECT s.id, s.nombre_negocio, s.contacto_nombre, s.contacto_email, s.contacto_telefono,
                                            s.motivo_rechazo, c.id AS crem_id, c.slug AS crem_slug
                                     FROM solicitudes_registro s LEFT JOIN crematorios c ON c.id = s.crematorio_id WHERE s.id = ? LIMIT 1");
                $st->execute([(int)($datos['solicitud_id'] ?? 0)]);
                $s = $st->fetch(\PDO::FETCH_ASSOC);
                if (!$s) return null;
                $d = [
                    'solicitud_id'   => (int)$s['id'],
                    'nombre_negocio' => $s['nombre_negocio'],
                    'contacto'       => ['nombre' => $s['contacto_nombre'], 'email' => $s['contacto_email'], 'telefono' => $s['contacto_telefono']],
                ];
                if ($evento === 'solicitud.aprobada') {
                    $d['ficha'] = $s['crem_id'] ? ['id' => (int)$s['crem_id'], 'slug' => $s['crem_slug'], 'url' => notifUrlPublica((string)$s['crem_slug'])] : null;
                } else {
                    $d['motivo'] = $s['motivo_rechazo'];
                }
                return $d;

            case 'lead.entregado':
                $st = $pdo->prepare("SELECT l.id, l.channel_type, l.negocio_notificado_at,
                                            c.id AS neg_id, c.nombre AS neg_nombre, c.slug AS neg_slug, c.tier AS neg_tier
                                     FROM leads_b2c l JOIN crematorios c ON c.id = l.crematorio_id WHERE l.id = ? LIMIT 1");
                $st->execute([(int)($datos['lead_id'] ?? 0)]);
                $l = $st->fetch(\PDO::FETCH_ASSOC);
                if (!$l) return null;
                $completos = json_decode(defined('TIERS_LEAD_COMPLETO') ? TIERS_LEAD_COMPLETO : '[]', true) ?: [];
                $variante  = in_array($l['neg_tier'], ['00', '01'], true) ? 'portal'
                           : (in_array($l['neg_tier'], $completos, true) ? 'completa' : 'ofuscada');
                return [
                    'lead_id'     => (int)$l['id'],
                    'canal_lead'  => $l['channel_type'],
                    'entregado_en'=> $l['negocio_notificado_at'],
                    'variante'    => $variante,
                    'negocio'     => ['id' => (int)$l['neg_id'], 'nombre' => $l['neg_nombre'], 'slug' => $l['neg_slug'], 'plan' => $l['neg_tier']],
                ];
        }
        return null;
    }

    /** POST del JSON a un destino. @return array{ok:bool,error?:string} */
    function notifWebhookPost(array $destino, string $json, string $evento, string $clave): array
    {
        $cab = ['Content-Type: application/json', 'X-Evento: ' . $evento, 'X-Evento-Id: ' . $clave];
        if (!empty($destino['secreto'])) {
            $cab[] = 'X-Firma: sha256=' . hash_hmac('sha256', $json, (string)$destino['secreto']);
        }
        $ch = curl_init($destino['url']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $cab,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($http >= 200 && $http < 300) return ['ok' => true];
        return ['ok' => false, 'error' => "{$destino['nombre']}: HTTP {$http}" . ($err ? " — {$err}" : '')];
    }
}
