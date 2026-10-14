<?php
/**
 * ═══════════════════════════════════════════════════════════
 * DESPACHADOR DE NOTIFICACIONES POR EVENTO
 * ═══════════════════════════════════════════════════════════
 *
 *   notificarEvento(\PDO $pdo, string $evento, array $datos): array
 *
 * Busca el evento en el catálogo (eventos.php), y por cada canal:
 *   1. reserva una fila en notificaciones_log (idempotencia: UNIQUE clave+canal+destinatario)
 *   2. comprueba bajas (notificaciones_supresiones)
 *   3. envía y deja constancia (enviada / fallida / omitida)
 *
 * Nunca lanza excepciones: un fallo de notificación no debe romper el flujo principal.
 *
 * Utilidades públicas:
 *   notifCerrarRespuesta()            → cierra la respuesta HTTP al navegador antes de enviar
 *   notifUrlPublica(string $path)     → URL absoluta del sitio (también desde cron)
 *   notifUrlBaja(string $email)       → enlace firmado para dejar de recibir avisos
 *   notifTokenBaja(string $email)     → firma HMAC del email
 *   notifUrlRespuesta(int $resenaId)  → enlace firmado para que el negocio responda a una reseña
 *   notifEstaSuprimido / notifSuprimir / notifReactivar
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/eventos.php';
require_once __DIR__ . '/canales/email-usuario.php';
require_once __DIR__ . '/canales/email-negocio.php';
require_once __DIR__ . '/canales/email-solicitante.php';
require_once __DIR__ . '/canales/email-equipo.php';
require_once __DIR__ . '/canales/webhook.php';

if (!function_exists('notificarEvento')) {

    /**
     * @return array<string, array{estado:string, motivo?:string}> resultado por canal
     */
    function notificarEvento(\PDO $pdo, string $evento, array $datos): array
    {
        $resultado = [];
        try {
            $catalogo = notifCatalogoEventos();
            if (!isset($catalogo[$evento])) {
                error_log("[notif] evento desconocido: {$evento}");
                return ['_error' => ['estado' => 'fallida', 'motivo' => 'evento_desconocido']];
            }
            $def   = $catalogo[$evento];
            $clave = ($def['clave'])($datos);

            foreach ($def['canales'] as $canal) {
                $fn = 'notifCanal_' . $canal;
                if (!function_exists($fn)) {
                    $resultado[$canal] = ['estado' => 'fallida', 'motivo' => 'canal_inexistente'];
                    continue;
                }
                // Cada canal va aislado: si uno falla, los demás se siguen intentando
                try {
                    $resultado[$canal] = notifDespacharCanal($pdo, $evento, $def, $clave, $canal, $fn, $datos);
                } catch (\Throwable $e) {
                    error_log("[notif {$evento}] canal {$canal} excepción: " . $e->getMessage());
                    $resultado[$canal] = ['estado' => 'fallida', 'motivo' => 'excepcion', 'error' => mb_substr($e->getMessage(), 0, 500)];
                }
            }
        } catch (\Throwable $e) {
            error_log("[notif {$evento}] excepción: " . $e->getMessage());
            $resultado['_error'] = ['estado' => 'fallida', 'motivo' => 'excepcion'];
        }
        return $resultado;
    }

    /**
     * Cada canal expone notifCanal_<nombre>($pdo, $evento, $datos) que devuelve:
     *   ['destinatario'=>string, 'email_baja'=>?string, 'omitir'=>?string,
     *    'enviar'=>callable():array{ok:bool,error?:string}]
     * (preparar → el despachador decide → enviar)
     */
    function notifDespacharCanal(\PDO $pdo, string $evento, array $def, string $clave, string $canal, string $fn, array $datos): array
    {
        $prep = $fn($pdo, $evento, $datos);

        // Canal con varios destinos (webhook): cada uno se reserva, envía y registra por separado,
        // así un destino caído no bloquea a los demás ni se reenvía a los que ya lo recibieron.
        if (is_array($prep) && !empty($prep['multiples'])) {
            $estados = []; $errores = [];
            foreach ($prep['multiples'] as $p) {
                $r = notifEjecutarPrep($pdo, $evento, $def, $clave, $canal, $p);
                $estados[] = $r['estado'];
                if (!empty($r['error'])) $errores[] = $r['error'];
            }
            $estado = in_array('fallida', $estados, true) ? 'fallida'
                    : (in_array('enviada', $estados, true) ? 'enviada' : 'omitida');
            return ['estado' => $estado, 'destinos' => count($estados)] + ($errores ? ['error' => implode(' | ', $errores)] : []);
        }

        return notifEjecutarPrep($pdo, $evento, $def, $clave, $canal, is_array($prep) ? $prep : []);
    }

    /** Reserva la fila, comprueba condiciones y envía UN destinatario de un canal. */
    function notifEjecutarPrep(\PDO $pdo, string $evento, array $def, string $clave, string $canal, array $prep): array
    {
        if (empty($prep['destinatario'])) {
            return ['estado' => 'omitida', 'motivo' => $prep['omitir'] ?? 'sin_destinatario'];
        }
        $dest = (string)$prep['destinatario'];

        // Si recibir este evento es "el usuario volvió a escribirnos", reactivar avisos
        if (!empty($def['reactiva_supresion']) && !empty($prep['email_baja'])) {
            notifReactivar($pdo, $prep['email_baja']);
        }

        // 1. Reservar la fila (idempotencia)
        $ins = $pdo->prepare("INSERT IGNORE INTO notificaciones_log (evento, clave, canal, destinatario, estado, intentos)
                              VALUES (?, ?, ?, ?, 'pendiente', 0)");
        $ins->execute([$evento, $clave, $canal, $dest]);
        $reservada = $ins->rowCount() === 1;

        if (!$reservada) {
            // Ya existe: solo reintentamos si falló antes y quedan intentos
            $sel = $pdo->prepare("SELECT id, estado, intentos FROM notificaciones_log
                                  WHERE clave = ? AND canal = ? AND destinatario = ? LIMIT 1");
            $sel->execute([$clave, $canal, $dest]);
            $fila = $sel->fetch(\PDO::FETCH_ASSOC);
            if (!$fila || $fila['estado'] !== 'fallida' || (int)$fila['intentos'] >= 3) {
                return ['estado' => 'omitida', 'motivo' => 'duplicado'];
            }
        }
        $upd = $pdo->prepare("UPDATE notificaciones_log SET intentos = intentos + 1
                              WHERE clave = ? AND canal = ? AND destinatario = ?");
        $upd->execute([$clave, $canal, $dest]);

        // La fila queda registrada como «en curso» hasta que se cierre; si el proceso muere
        // antes (fatal, timeout), notifCerrarPendientesAlSalir() la pasa a «fallida» con la causa.
        notifFilaEnCurso($pdo, $clave, $canal, $dest, true);

        $cerrar = function (string $estado, ?string $motivo = null, ?string $error = null) use ($pdo, $clave, $canal, $dest): array {
            $error = $error ? mb_substr($error, 0, 500) : null;
            try {
                $sql = "UPDATE notificaciones_log SET estado = ?, motivo = ?, error = ?"
                     . ($estado === 'enviada' ? ", enviado_en = NOW()" : "")
                     . " WHERE clave = ? AND canal = ? AND destinatario = ?";
                $pdo->prepare($sql)->execute([$estado, $motivo, $error, $clave, $canal, $dest]);
            } catch (\Throwable $e) {
                // No dejar la fila en «pendiente» sin explicación
                error_log("[notif] no se pudo cerrar la fila {$clave}/{$canal}: " . $e->getMessage());
                try {
                    $pdo->prepare("UPDATE notificaciones_log SET estado = 'fallida', error = ? WHERE clave = ? AND canal = ? AND destinatario = ?")
                        ->execute([mb_substr('cierre de fila: ' . $e->getMessage(), 0, 500), $clave, $canal, $dest]);
                } catch (\Throwable $e2) { /* sin más recursos */ }
            }
            notifFilaEnCurso($pdo, $clave, $canal, $dest, false);
            return ['estado' => $estado] + ($motivo ? ['motivo' => $motivo] : []) + ($error ? ['error' => $error] : []);
        };

        // 2. Condiciones de omisión propias del canal (fuente no propia, estado cambiado…)
        if (!empty($prep['omitir'])) {
            return $cerrar('omitida', $prep['omitir']);
        }

        // 3. Baja
        if (!empty($prep['email_baja']) && notifEstaSuprimido($pdo, $prep['email_baja'])) {
            return $cerrar('omitida', 'baja');
        }

        // 4. Envío
        try {
            $envio = ($prep['enviar'])();
        } catch (\Throwable $e) {
            $envio = ['ok' => false, 'error' => 'excepción: ' . $e->getMessage()];
        }
        if (!empty($envio['ok'])) {
            return $cerrar('enviada');
        }
        error_log("[notif {$evento}] {$canal} → {$dest} falló: " . ($envio['error'] ?? '?'));
        return $cerrar('fallida', null, $envio['error'] ?? 'error desconocido');
    }

    /**
     * Registro de filas «en curso» (reservadas y aún sin cerrar). Si el script termina con alguna
     * abierta (error fatal, tiempo agotado), un cierre de emergencia las marca «fallida» con la
     * última causa conocida de PHP, para que nunca queden en «pendiente» sin explicación.
     */
    function notifFilaEnCurso(\PDO $pdo, string $clave, string $canal, string $dest, bool $abrir): void
    {
        static $abiertas = [];
        static $registrado = false;
        $k = $clave . '|' . $canal . '|' . $dest;

        if ($abrir) {
            $abiertas[$k] = [$pdo, $clave, $canal, $dest];
            if (!$registrado) {
                $registrado = true;
                register_shutdown_function(function () use (&$abiertas) {
                    if (!$abiertas) return;
                    $ult  = error_get_last();
                    $causa = 'proceso interrumpido' . ($ult ? ': ' . $ult['message'] : ' (sin error PHP: posible corte del hosting)');
                    foreach ($abiertas as [$p, $c, $ca, $d]) {
                        try {
                            $p->prepare("UPDATE notificaciones_log SET estado = 'fallida', error = ? WHERE clave = ? AND canal = ? AND destinatario = ? AND estado = 'pendiente'")
                              ->execute([mb_substr($causa, 0, 500), $c, $ca, $d]);
                        } catch (\Throwable $e) { /* nada más que hacer */ }
                    }
                });
            }
        } else {
            unset($abiertas[$k]);
        }
    }

    // ─── Bajas ───────────────────────────────────────────────

    function notifHashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    function notifEstaSuprimido(\PDO $pdo, string $email): bool
    {
        $st = $pdo->prepare("SELECT suprimido FROM notificaciones_supresiones WHERE email_hash = ? LIMIT 1");
        $st->execute([notifHashEmail($email)]);
        return (int)$st->fetchColumn() === 1;
    }

    function notifSuprimir(\PDO $pdo, string $email): void
    {
        $pdo->prepare("INSERT INTO notificaciones_supresiones (email_hash, suprimido, baja_en)
                       VALUES (?, 1, NOW())
                       ON DUPLICATE KEY UPDATE suprimido = 1, baja_en = NOW()")
            ->execute([notifHashEmail($email)]);
    }

    /** El mismo email vuelve a escribirnos → reactiva SOLO las notificaciones. */
    function notifReactivar(\PDO $pdo, string $email): void
    {
        $pdo->prepare("UPDATE notificaciones_supresiones
                       SET suprimido = 0, reactivado_en = NOW()
                       WHERE email_hash = ? AND suprimido = 1")
            ->execute([notifHashEmail($email)]);
    }

    // ─── Enlaces ─────────────────────────────────────────────

    /** Clave de firma: NOTIF_SECRET del .env; si falta, deriva una de las credenciales de BD. */
    function notifSecreto(): string
    {
        if (defined('NOTIF_SECRET') && NOTIF_SECRET !== '') return (string)NOTIF_SECRET;
        return hash('sha256', 'notif|' . (defined('DB_NAME') ? DB_NAME : '') . '|' . (defined('DB_USER') ? DB_USER : '') . '|' . (defined('DB_PASS') ? DB_PASS : ''));
    }

    function notifTokenBaja(string $email): string
    {
        return substr(hash_hmac('sha256', 'baja|' . mb_strtolower(trim($email)), notifSecreto()), 0, 32);
    }

    function notifTokenValido(string $email, string $token): bool
    {
        return $token !== '' && hash_equals(notifTokenBaja($email), $token);
    }

    function notifB64UrlEncode(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    function notifB64UrlDecode(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/')); }

    function notifUrlBaja(string $email): string
    {
        return notifUrlPublica('desuscribir-notificaciones.php')
            . '?e=' . notifB64UrlEncode(mb_strtolower(trim($email)))
            . '&t=' . notifTokenBaja($email);
    }

    /** Firma HMAC del enlace «Responder a la reseña» (identifica la reseña; el negocio no tiene cuenta). */
    function notifTokenRespuesta(int $resenaId): string
    {
        return substr(hash_hmac('sha256', 'respuesta|' . $resenaId, notifSecreto()), 0, 32);
    }

    function notifTokenRespuestaValido(int $resenaId, string $token): bool
    {
        return $resenaId > 0 && $token !== '' && hash_equals(notifTokenRespuesta($resenaId), $token);
    }

    function notifUrlRespuesta(int $resenaId): string
    {
        return notifUrlPublica('responder-resena.php')
            . '?r=' . $resenaId
            . '&t=' . notifTokenRespuesta($resenaId);
    }

    // ─── Respuesta HTTP ──────────────────────────────────────

    /**
     * Cierra la respuesta al navegador para que el envío de emails (SMTP puede tardar
     * 1-3 s) no retrase al usuario. Donde no existe finish_request (XAMPP/Apache),
     * simplemente envía sincrónicamente.
     */
    function notifCerrarRespuesta(): void
    {
        ignore_user_abort(true);
        @set_time_limit(60);
        // Si el script abrió buffers de salida (ob_start), hay que vaciarlos para que la respuesta salga ya
        while (ob_get_level() > 0) { @ob_end_flush(); }
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }
}
