<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CAMPAÑA DE PROSPECCIÓN — motor de envío (tramo 3)
 * ═══════════════════════════════════════════════════════════
 * Selección de fichas, ventana horaria, tope diario, interruptor de pausa y envío de los
 * emails 1-3 (invitación / recordatorio / último aviso). Lo usan cron/enviar-campana-prospeccion.php
 * y admin/campana-prospeccion.php.
 *
 * Zonas horarias: el día y la hora «de España» se calculan en PHP con zona explícita
 * (Europe/Madrid); la antigüedad del último envío se compara en SQL con NOW().
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/notificaciones/despachador.php';

const CAMPANA_DIAS_RECORDATORIO = 7;    // días desde la invitación
const CAMPANA_DIAS_ULTIMO_AVISO = 14;   // días desde el recordatorio
const CAMPANA_HORA_DESDE = 9;           // 9:00 (incluida)
const CAMPANA_HORA_HASTA = 14;          // 14:00 (excluida)
const CAMPANA_EVENTOS = [1 => 'campana.invitacion', 2 => 'campana.recordatorio', 3 => 'campana.ultimo_aviso'];

if (!function_exists('campanaConfig')) {

    function campanaConfig(\PDO $pdo, string $clave, string $defecto = ''): string
    {
        try {
            $st = $pdo->prepare("SELECT valor FROM campana_config WHERE clave = ?");
            $st->execute([$clave]);
            $v = $st->fetchColumn();
            return $v === false ? $defecto : (string)$v;
        } catch (\Throwable $e) {
            return $defecto;   // migración sin correr
        }
    }

    function campanaFijarConfig(\PDO $pdo, string $clave, string $valor): void
    {
        $pdo->prepare("INSERT INTO campana_config (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
            ->execute([$clave, $valor]);
    }

    /** Sin tabla de configuración se considera pausada (lo seguro). */
    function campanaPausada(\PDO $pdo): bool { return campanaConfig($pdo, 'pausada', '1') !== '0'; }

    function campanaTope(\PDO $pdo): int { return max(1, (int)campanaConfig($pdo, 'tope_diario', '10')); }

    function campanaAhoraEspana(): \DateTimeImmutable { return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')); }

    /** ¿Estamos en horario de envío? Lunes a viernes, 9:00-13:59 hora de España. */
    function campanaEnVentana(?\DateTimeImmutable $ahora = null): bool
    {
        $ahora = $ahora ?? campanaAhoraEspana();
        $h = (int)$ahora->format('G');
        return (int)$ahora->format('N') <= 5 && $h >= CAMPANA_HORA_DESDE && $h < CAMPANA_HORA_HASTA;
    }

    function campanaEnviadosHoy(\PDO $pdo, bool $prueba): int
    {
        $st = $pdo->prepare("SELECT COUNT(*) FROM campana_envios WHERE dia_es = ? AND prueba = ?");
        $st->execute([campanaAhoraEspana()->format('Y-m-d'), $prueba ? 1 : 0]);
        return (int)$st->fetchColumn();
    }

    /** Emails válidos conocidos de una ficha, en orden de preferencia (público, clientes, resto). */
    function campanaEmailsFicha(array $f): array
    {
        $out = [$f['email'] ?? '', $f['email_clientes'] ?? ''];
        foreach (json_decode((string)($f['emails_json'] ?? ''), true) ?: [] as $m) $out[] = $m['email'] ?? '';
        $out = array_map(fn($x) => mb_strtolower(trim((string)$x)), $out);
        return array_values(array_unique(array_filter($out, fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL))));
    }

    /** ¿Alguno de los emails de la ficha está dado de baja? (la baja es por ficha, como en verificar-ficha.php) */
    function campanaFichaSuprimida(\PDO $pdo, array $f): bool
    {
        foreach (campanaEmailsFicha($f) as $m) if (notifEstaSuprimido($pdo, $m)) return true;
        return false;
    }

    /** Paso que toca a una ficha según su estado de campaña (0 = ninguno). */
    function campanaPasoDe(?string $estado): int
    {
        return match ($estado) { null, '', 'sin_enviar' => 1, 'enviado_1' => 2, 'enviado_2' => 3, default => 0 };
    }

    /**
     * Fichas a las que toca escribir ahora, sin aplicar el tope. $demo = true → solo las fichas demo (pruebas);
     * en las pruebas se ignoran los días de espera entre pasos.
     * @return array<int, array> fichas con 'paso', 'email' (destinatario) y 'campana_estado'
     */
    function campanaCandidatas(\PDO $pdo, bool $demo = false): array
    {
        $espera = $demo ? "1=1" : "(cf.estado = 'sin_enviar' OR cf.estado IS NULL
                   OR (cf.estado = 'enviado_1' AND cf.ultimo_envio <= NOW() - INTERVAL " . CAMPANA_DIAS_RECORDATORIO . " DAY)
                   OR (cf.estado = 'enviado_2' AND cf.ultimo_envio <= NOW() - INTERVAL " . CAMPANA_DIAS_ULTIMO_AVISO . " DAY))";
        $sql = "SELECT c.id, c.nombre, c.slug, c.estado, c.email, c.email_clientes, c.emails_json,
                       cf.estado AS campana_estado, cf.ultimo_envio
                  FROM crematorios c
             LEFT JOIN campana_fichas cf ON cf.crematorio_id = c.id
                 WHERE c.estado = :est AND c.verificado = 0
                   AND (cf.estado IS NULL OR cf.estado IN ('sin_enviar','enviado_1','enviado_2'))
                   AND $espera
              ORDER BY (cf.estado IS NOT NULL AND cf.estado <> 'sin_enviar') DESC, c.id ASC";
        $st = $pdo->prepare($sql);
        $st->execute([':est' => $demo ? 'demo' : 'activa']);

        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $f) {
            $paso = campanaPasoDe($f['campana_estado']);
            $emails = campanaEmailsFicha($f);
            if (!$paso || !$emails || campanaFichaSuprimida($pdo, $f)) continue;
            $out[] = $f + ['paso' => $paso, 'email' => $emails[0]];
        }
        return $out;
    }

    /**
     * Envía el email de campaña de $paso a una ficha. $emailPrueba (solo para fichas demo) redirige el envío.
     * @return array resultado del despachador por canal
     */
    function campanaEnviar(\PDO $pdo, int $fichaId, int $paso, ?string $emailPrueba = null): array
    {
        $datos = ['crematorio_id' => $fichaId, 'paso' => $paso];
        if ($emailPrueba) { $datos['email_prueba'] = $emailPrueba; $datos['marca'] = date('YmdHis'); }
        return notificarEvento($pdo, CAMPANA_EVENTOS[$paso], $datos);
    }

    /** Resumen por estado + envíos y clics, para el panel del admin. */
    function campanaMetricas(\PDO $pdo): array
    {
        $m = ['estados' => [], 'envios' => [1 => 0, 2 => 0, 3 => 0], 'clics' => ['llamada' => 0, 'formulario' => 0]];
        foreach ($pdo->query("SELECT cf.estado, COUNT(*) n FROM campana_fichas cf JOIN crematorios c ON c.id = cf.crematorio_id
                               WHERE c.estado = 'activa' GROUP BY cf.estado")->fetchAll(\PDO::FETCH_ASSOC) as $r) $m['estados'][$r['estado']] = (int)$r['n'];
        foreach ($pdo->query("SELECT paso, COUNT(*) n FROM campana_envios WHERE prueba = 0 GROUP BY paso")->fetchAll(\PDO::FETCH_ASSOC) as $r) $m['envios'][(int)$r['paso']] = (int)$r['n'];
        foreach ($pdo->query("SELECT boton, COUNT(*) n FROM campana_clics GROUP BY boton")->fetchAll(\PDO::FETCH_ASSOC) as $r) $m['clics'][$r['boton']] = (int)$r['n'];
        return $m;
    }
}
