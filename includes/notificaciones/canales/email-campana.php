<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CANAL: email de la campaña de prospección al NEGOCIO (emails 1-3)
 * ═══════════════════════════════════════════════════════════
 *
 * Eventos: campana.invitacion · campana.recordatorio · campana.ultimo_aviso
 * Datos:   ['crematorio_id' => int, 'paso' => 1|2|3, 'email_prueba' => ?string, 'marca' => ?string]
 *
 * Destinatario: el primer email válido de la ficha (o email_prueba, solo en fichas demo).
 * No escribe a quien ya respondió, se dio de baja o tiene la ficha verificada. Al enviar bien,
 * avanza campana_fichas.estado (enviado_N) y apunta el envío en campana_envios (tope diario).
 * Lleva enlace de baja de un clic y List-Unsubscribe.
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../../plantillas-email/campana-prospeccion.php';
require_once __DIR__ . '/../../campana-prospeccion.php';

if (!function_exists('notifCanal_email_campana')) {

    function notifCanal_email_campana(\PDO $pdo, string $evento, array $datos): array
    {
        $paso = (int)($datos['paso'] ?? 0);
        if (!isset(CAMPANA_EVENTOS[$paso]) || CAMPANA_EVENTOS[$paso] !== $evento) return ['omitir' => 'paso_invalido'];

        $st = $pdo->prepare("SELECT c.id, c.nombre, c.estado, c.verificado, c.email, c.email_clientes, c.emails_json, cf.estado AS campana_estado
                               FROM crematorios c LEFT JOIN campana_fichas cf ON cf.crematorio_id = c.id
                              WHERE c.id = ? LIMIT 1");
        $st->execute([(int)($datos['crematorio_id'] ?? 0)]);
        $f = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$f) return ['omitir' => 'ficha_no_existe'];

        $prueba = !empty($datos['email_prueba']);
        if ($prueba && $f['estado'] !== 'demo')   return ['omitir' => 'prueba_solo_demo'];
        if (!$prueba && $f['estado'] !== 'activa' && $f['estado'] !== 'demo') return ['omitir' => 'ficha_no_activa'];
        if ((int)$f['verificado'] === 1)          return ['omitir' => 'ya_verificada'];
        if (in_array($f['campana_estado'], ['respondio', 'llamada', 'formulario', 'baja'], true)) return ['omitir' => 'ya_respondio'];

        $emails = campanaEmailsFicha($f);
        if (!$emails) return ['omitir' => 'sin_email'];
        if (!$prueba && campanaFichaSuprimida($pdo, $f)) return ['omitir' => 'baja'];

        $email = $prueba ? mb_strtolower(trim((string)$datos['email_prueba'])) : $emails[0];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['omitir' => 'email_invalido'];

        $tpl = renderEmailCampana($paso, ['id' => (int)$f['id'], 'nombre' => (string)$f['nombre']], $email);

        return [
            'destinatario' => $email,
            'email_baja'   => $email,
            'enviar'       => function () use ($pdo, $f, $paso, $email, $prueba, $tpl) {
                $r = enviarMailHtml(
                    $email, $tpl['asunto'], $tpl['html'], $tpl['texto'],
                    'info@crematoriosdemascotas.com', '', 'html', notifUrlBaja($email)
                );
                if (!empty($r['ok'])) {
                    try {
                        $pdo->prepare("INSERT INTO campana_fichas (crematorio_id, estado, ultimo_envio) VALUES (?, ?, NOW())
                                       ON DUPLICATE KEY UPDATE
                                         estado = IF(estado IN ('sin_enviar','enviado_1','enviado_2','enviado_3'), VALUES(estado), estado),
                                         ultimo_envio = NOW()")
                            ->execute([(int)$f['id'], 'enviado_' . $paso]);
                        $pdo->prepare("INSERT INTO campana_envios (crematorio_id, paso, email, prueba, dia_es) VALUES (?, ?, ?, ?, ?)")
                            ->execute([(int)$f['id'], $paso, $email, $prueba ? 1 : 0, campanaAhoraEspana()->format('Y-m-d')]);
                    } catch (\Throwable $e) {
                        error_log('[campana] email enviado pero no se pudo registrar: ' . $e->getMessage());
                    }
                }
                return $r;
            },
        ];
    }
}
