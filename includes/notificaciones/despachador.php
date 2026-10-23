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
 *   notifUrlBaja(string $email)       → enlace firmado para dejar de recibir avisos (baja total, un clic)
 *   notifUrlPreferencias($email)      → enlace firmado a la página de interruptores por categoría (negocios)
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
require_once __DIR__ . '/canales/email-actualizacion.php';
require_once __DIR__ . '/canales/email-campana.php';
require_once __DIR__ . '/canales/email-llamada.php';
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
     *   ['destinatario'=>string, 'email_baja'=>?string, 'categoria_baja'=>?string ('resenas'|'leads'|'informes'), 'omitir'=>?string,
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
                $r = notifEjecutarPrep($pdo, $evento, $def, $clave, $canal, $p, $datos);
                $estados[] = $r['estado'];
                if (!empty($r['error'])) $errores[] = $r['error'];
            }
            $estado = in_array('fallida', $estados, true) ? 'fallida'
                    : (in_array('enviada', $estados, true) ? 'enviada' : 'omitida');
            return ['estado' => $estado, 'destinos' => count($estados)] + ($errores ? ['error' => implode(' | ', $errores)] : []);
        }

        return notifEjecutarPrep($pdo, $evento, $def, $clave, $canal, is_array($prep) ? $prep : [], $datos);
    }

    /** Reserva la fila, comprueba condiciones y envía UN destinatario de un canal. */
    function notifEjecutarPrep(\PDO $pdo, string $evento, array $def, string $clave, string $canal, array $prep, array $datos = []): array
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

        // Copia de los datos del evento para que el cron pueda reintentar si falla (se vacía al cerrar bien).
        // Aparte y silenciosa: si la columna `datos` aún no existe (migración sin correr), no pasa nada.
        if ($datos) {
            try {
                $pdo->prepare("UPDATE notificaciones_log SET datos = ? WHERE clave = ? AND canal = ? AND destinatario = ?")
                    ->execute([json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), $clave, $canal, $dest]);
            } catch (\Throwable $e) { /* sin columna datos: los reintentos solo cubren eventos reconstruibles */ }
        }

        $cerrar = function (string $estado, ?string $motivo = null, ?string $error = null) use ($pdo, $clave, $canal, $dest): array {
            $error = $error ? mb_substr($error, 0, 500) : null;
            try {
                $sql = "UPDATE notificaciones_log SET estado = ?, motivo = ?, error = ?"
                     . ($estado === 'enviada' ? ", enviado_en = NOW()" : "")
                     . " WHERE clave = ? AND canal = ? AND destinatario = ?";
                $pdo->prepare($sql)->execute([$estado, $motivo, $error, $clave, $canal, $dest]);
                if ($estado !== 'fallida') {
                    try {
                        $pdo->prepare("UPDATE notificaciones_log SET datos = NULL WHERE clave = ? AND canal = ? AND destinatario = ?")
                            ->execute([$clave, $canal, $dest]);
                    } catch (\Throwable $e) { /* sin columna datos */ }
                }
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
        if (!empty($prep['email_baja']) && notifEstaSuprimido($pdo, $prep['email_baja'], $prep['categoria_baja'] ?? null)) {
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

    // ─── Reintentos (cron/reintentar-notificaciones.php) ─────

    /**
     * Datos para reenviar un evento fallido. Usa la copia guardada en la fila (`datos`); si no la hay,
     * reconstruye los eventos de ciclo de vida desde la clave (solo llevan un id). null = no reintentable.
     */
    function notifDatosParaReintento(\PDO $pdo, string $evento, string $clave, ?string $guardados): ?array
    {
        if ($guardados !== null && $guardados !== '') {
            $d = json_decode($guardados, true);
            if (is_array($d)) return $d;
        }
        $partes = explode(':', $clave);
        $id = (int)($partes[1] ?? 0);
        if ($id <= 0) return null;
        switch ($evento) {
            case 'resena.aprobada':     return ['resena_id' => $id];
            case 'solicitud.aprobada':  return ['solicitud_id' => $id];
            case 'lead.entregado':      return ['lead_id' => $id];
            case 'llamada.solicitada':  return ['llamada_id' => $id];
            case 'ficha.actualizacion_recibida':
            case 'ficha.actualizacion_revisada': return ['actualizacion_id' => $id];
            case 'respuesta.recibida':
            case 'respuesta.aprobada':  return ['respuesta_id' => $id];
            case 'solicitud.rechazada':
                $st = $pdo->prepare("SELECT motivo_rechazo FROM solicitudes_registro WHERE id = ? LIMIT 1");
                $st->execute([$id]);
                $m = $st->fetchColumn();
                return $m === false ? null : ['solicitud_id' => $id, 'motivo' => (string)$m];
        }
        return null;   // conversiones del sitio: su payload no se puede reconstruir sin la copia guardada
    }

    /**
     * Reintenta las filas «fallida» con menos de 3 intentos (últimos 7 días, de más de 2 minutos).
     * Las filas «pendiente» antiguas NO se tocan (se tratan como duplicado). Reenvía el evento entero:
     * lo ya «enviada» se omite como duplicado, solo se repite lo que falló.
     * @return array{evaluadas:int, reintentadas:int, enviadas:int, sin_datos:int, detalle:array<int,string>}
     */
    function notifReintentarFallidas(\PDO $pdo, bool $dryRun = false, int $limite = 50): array
    {
        $out = ['evaluadas' => 0, 'reintentadas' => 0, 'enviadas' => 0, 'sin_datos' => 0, 'detalle' => []];
        $catalogo = notifCatalogoEventos();

        try {
            $filas = $pdo->query("SELECT id, evento, clave, canal, destinatario, intentos, datos FROM notificaciones_log
                                  WHERE estado = 'fallida' AND intentos < 3
                                    AND created_at > NOW() - INTERVAL 7 DAY AND created_at < NOW() - INTERVAL 2 MINUTE
                                  ORDER BY id LIMIT " . (int)$limite)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Sin columna `datos` (migración sin correr): se leen igual, sin copia guardada
            $filas = $pdo->query("SELECT id, evento, clave, canal, destinatario, intentos, NULL AS datos FROM notificaciones_log
                                  WHERE estado = 'fallida' AND intentos < 3
                                    AND created_at > NOW() - INTERVAL 7 DAY AND created_at < NOW() - INTERVAL 2 MINUTE
                                  ORDER BY id LIMIT " . (int)$limite)->fetchAll(\PDO::FETCH_ASSOC);
        }

        $hechos = [];   // evento+clave ya reenviado en esta pasada (un evento puede tener varias filas fallidas)
        foreach ($filas as $f) {
            $out['evaluadas']++;
            $k = $f['evento'] . '|' . $f['clave'];
            if (isset($hechos[$k])) continue;

            $etq = "#{$f['id']} {$f['evento']} / {$f['canal']} → {$f['destinatario']} (intento " . ((int)$f['intentos'] + 1) . "/3)";
            $def = $catalogo[$f['evento']] ?? null;
            $datos = $def ? notifDatosParaReintento($pdo, $f['evento'], $f['clave'], $f['datos']) : null;

            // Si los datos ya no producen la misma clave (p. ej. cambió el motivo de rechazo), no se reenvía
            if ($datos === null || ($def['clave'])($datos) !== $f['clave']) {
                $out['sin_datos']++;
                $out['detalle'][] = "{$etq}: no reintentable (sin copia de los datos)";
                continue;
            }
            $hechos[$k] = true;

            if ($dryRun) {
                $out['detalle'][] = "{$etq}: se reintentaría";
                continue;
            }
            $out['reintentadas']++;
            $res = notificarEvento($pdo, $f['evento'], $datos);
            $r = $res[$f['canal']] ?? null;
            $estado = $r['estado'] ?? '?';
            if ($estado === 'enviada') $out['enviadas']++;
            $out['detalle'][] = "{$etq}: {$estado}" . (!empty($r['error']) ? ' — ' . $r['error'] : '');
        }
        return $out;
    }

    // ─── Bajas ───────────────────────────────────────────────

    function notifHashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /** Categorías de aviso que un negocio puede apagar por separado (clave → columna de notificaciones_supresiones). */
    function notifCategoriasBaja(): array
    {
        return ['resenas' => 'baja_resenas', 'leads' => 'baja_leads', 'informes' => 'baja_informes'];
    }

    /**
     * ¿Está dado de baja este email? Con $categoria, también cuenta si esa categoría está apagada.
     * Sin $categoria, solo la baja total.
     */
    function notifEstaSuprimido(\PDO $pdo, string $email, ?string $categoria = null): bool
    {
        $cats = notifCategoriasBaja();
        if ($categoria !== null && isset($cats[$categoria])) {
            try {
                $st = $pdo->prepare("SELECT suprimido, {$cats[$categoria]} FROM notificaciones_supresiones WHERE email_hash = ? LIMIT 1");
                $st->execute([notifHashEmail($email)]);
                $f = $st->fetch(\PDO::FETCH_NUM);
                return $f && ((int)$f[0] === 1 || (int)$f[1] === 1);
            } catch (\Throwable $e) {
                // Migración de categorías sin aplicar: se cae a la baja total
                error_log('[notif] categorías de baja no disponibles: ' . $e->getMessage());
            }
        }
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

    /**
     * Estado de las preferencias de un email: la baja total y cada categoría (true = recibe).
     * @return array{todo_apagado:bool, resenas:bool, leads:bool, informes:bool}
     */
    function notifPreferencias(\PDO $pdo, string $email): array
    {
        $cols = implode(', ', notifCategoriasBaja());
        $st = $pdo->prepare("SELECT suprimido, {$cols} FROM notificaciones_supresiones WHERE email_hash = ? LIMIT 1");
        $st->execute([notifHashEmail($email)]);
        $f = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
        $todo = (int)($f['suprimido'] ?? 0) === 1;
        $out = ['todo_apagado' => $todo];
        foreach (notifCategoriasBaja() as $clave => $col) {
            $out[$clave] = !$todo && (int)($f[$col] ?? 0) === 0;
        }
        return $out;
    }

    /**
     * Enciende o apaga UNA categoría. Encender una categoría quita también la baja total
     * (el negocio ha elegido recibir algo), dejando las demás categorías como estaban.
     */
    function notifGuardarCategoria(\PDO $pdo, string $email, string $categoria, bool $recibir): void
    {
        $cats = notifCategoriasBaja();
        if (!isset($cats[$categoria])) return;
        $col  = $cats[$categoria];
        $hash = notifHashEmail($email);
        if ($recibir) {
            // Si había baja total, las otras categorías pasan a «apagadas» para no reactivarlas sin querer
            $otras = array_diff(array_values($cats), [$col]);
            $set   = [];
            foreach ($otras as $o) $set[] = "{$o} = IF(suprimido = 1, 1, {$o})";
            $pdo->prepare("UPDATE notificaciones_supresiones
                           SET " . implode(', ', $set) . ", {$col} = 0, suprimido = 0, reactivado_en = NOW()
                           WHERE email_hash = ?")
                ->execute([$hash]);
        } else {
            $pdo->prepare("INSERT INTO notificaciones_supresiones (email_hash, suprimido, {$col}, baja_en)
                           VALUES (?, 0, 1, NOW())
                           ON DUPLICATE KEY UPDATE {$col} = 1, baja_en = NOW()")
                ->execute([$hash]);
        }
    }

    /** Vuelve a un estado guardado antes (para «Deshacer»). */
    function notifRestaurarPreferencias(\PDO $pdo, string $email, bool $todoApagado, array $categorias): void
    {
        $sets = ['suprimido = ' . ($todoApagado ? 1 : 0)];
        foreach (notifCategoriasBaja() as $clave => $col) {
            $sets[] = "{$col} = " . (empty($categorias[$clave]) ? 1 : 0);
        }
        $hash = notifHashEmail($email);
        $pdo->prepare("INSERT IGNORE INTO notificaciones_supresiones (email_hash, suprimido, baja_en) VALUES (?, 0, NOW())")->execute([$hash]);
        $pdo->prepare("UPDATE notificaciones_supresiones SET " . implode(', ', $sets) . " WHERE email_hash = ?")->execute([$hash]);
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

    /** Firma HMAC de la página de preferencias del negocio (prefijo propio: un enlace de baja simple no sirve aquí). */
    function notifTokenPreferencias(string $email): string
    {
        return substr(hash_hmac('sha256', 'preferencias|' . mb_strtolower(trim($email)), notifSecreto()), 0, 32);
    }

    function notifTokenPreferenciasValido(string $email, string $token): bool
    {
        return $token !== '' && hash_equals(notifTokenPreferencias($email), $token);
    }

    function notifUrlPreferencias(string $email): string
    {
        return notifUrlPublica('preferencias-notificaciones.php')
            . '?e=' . notifB64UrlEncode(mb_strtolower(trim($email)))
            . '&t=' . notifTokenPreferencias($email);
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

    /** Firma HMAC del enlace «Quiero recibir sus datos» (identifica el lead; el negocio no tiene cuenta). */
    function notifTokenEntrega(int $leadId): string
    {
        return substr(hash_hmac('sha256', 'entrega|' . $leadId, notifSecreto()), 0, 32);
    }

    function notifTokenEntregaValido(int $leadId, string $token): bool
    {
        return $leadId > 0 && $token !== '' && hash_equals(notifTokenEntrega($leadId), $token);
    }

    function notifUrlEntrega(int $leadId): string
    {
        return notifUrlPublica('recibir-datos.php')
            . '?l=' . $leadId
            . '&t=' . notifTokenEntrega($leadId);
    }

    /** Firma HMAC del enlace de la campaña «Verifica tu ficha» (identifica la ficha; el negocio no tiene cuenta). */
    function notifTokenVerificacion(int $crematorioId): string
    {
        return substr(hash_hmac('sha256', 'verificar|' . $crematorioId, notifSecreto()), 0, 32);
    }

    function notifTokenVerificacionValido(int $crematorioId, string $token): bool
    {
        return $crematorioId > 0 && $token !== '' && hash_equals(notifTokenVerificacion($crematorioId), $token);
    }

    function notifUrlVerificarFicha(int $crematorioId): string
    {
        return notifUrlPublica('verificar-ficha.php')
            . '?f=' . $crematorioId
            . '&t=' . notifTokenVerificacion($crematorioId);
    }

    // ─── Cupo de entregas de leads (planes con límite, p. ej. Gratis) ───

    /**
     * Cupo de entregas de una ficha: límite del plan (tiers.cupo_leads) y entregas ya usadas
     * (filas de lead_entregas, de por vida). Devuelve null si el plan es ilimitado o no hay ficha.
     * @return array{limite:int, usadas:int, restantes:int, agotado:bool}|null
     */
    function notifCupoLeads(\PDO $pdo, int $crematorioId): ?array
    {
        if ($crematorioId <= 0) return null;
        try {
            // Dos consultas (sin JOIN): crematorios.tier y tiers.id tienen collations distintas en algunas bases
            $st = $pdo->prepare("SELECT tier FROM crematorios WHERE id = ?");
            $st->execute([$crematorioId]);
            $tier = $st->fetchColumn();
            if ($tier === false) return null;
            $st = $pdo->prepare("SELECT cupo_leads FROM tiers WHERE id = ?");
            $st->execute([(string)$tier]);
            $limite = $st->fetchColumn();
            if ($limite === false || $limite === null) return null;
            $limite = (int)$limite;
            $st = $pdo->prepare("SELECT COUNT(*) FROM lead_entregas WHERE crematorio_id = ?");
            $st->execute([$crematorioId]);
            return notifCupoDesdeCifras($limite, (int)$st->fetchColumn());
        } catch (\Throwable $e) {
            error_log('[cupo-leads] ' . $e->getMessage());
            return null;
        }
    }

    /** @return array{limite:int, usadas:int, restantes:int, agotado:bool} */
    function notifCupoDesdeCifras(int $limite, int $usadas): array
    {
        $usadas = max(0, min($usadas, $limite));
        return ['limite' => $limite, 'usadas' => $usadas, 'restantes' => $limite - $usadas, 'agotado' => $usadas >= $limite];
    }

    /**
     * Texto y colores del contador (mismo en el email y en recibir-datos.php).
     * Quedan 3 o más: verde · quedan 2: ámbar · queda 1 o ninguno: rojo.
     * @return array{texto:string, cifra:string, nivel:string, color:string, fondo:string}
     */
    function notifContadorCupo(array $cupo): array
    {
        $r = $cupo['restantes'];
        if ($cupo['usadas'] === 0)      { $texto = 'Tienes ' . $cupo['limite'] . ' leads disponibles'; $nivel = 'verde'; }
        elseif ($r === 0)               { $texto = 'Ya has usado todos tus leads gratuitos';           $nivel = 'rojo'; }
        elseif ($r === 1)               { $texto = 'Te queda 1 solo lead gratuito';                    $nivel = 'rojo'; }
        elseif ($r === 2)               { $texto = 'Te quedan solo 2 leads';                           $nivel = 'ambar'; }
        else                            { $texto = 'Te quedan ' . $r . ' leads';                       $nivel = 'verde'; }

        $paleta = [
            'verde' => ['#2E7D32', '#E8F5E9'],
            'ambar' => ['#B26A00', '#FFF3E0'],
            'rojo'  => ['#C62828', '#FDECEA'],
        ];
        return [
            'texto' => $texto,
            'cifra' => $cupo['usadas'] . '/' . $cupo['limite'],
            'nivel' => $nivel,
            'color' => $paleta[$nivel][0],
            'fondo' => $paleta[$nivel][1],
        ];
    }

    /** Destino puente de «Mejorar mi plan» hasta que exista la página de planes (tarea 05). */
    function notifUrlMejorarPlan(string $negocioNombre): string
    {
        $para = defined('EMAIL_CONTACTO') ? EMAIL_CONTACTO : 'info@crematoriosdemascotas.com';
        $asunto = 'Quiero mejorar mi plan';
        $cuerpo = "Hola,\n\nQuiero mejorar el plan de mi ficha" . ($negocioNombre !== '' ? " ({$negocioNombre})" : '')
                . " para seguir recibiendo los datos de los clientes que me contactan.\n\nGracias.";
        return 'mailto:' . $para . '?subject=' . rawurlencode($asunto) . '&body=' . rawurlencode($cuerpo);
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
