<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CATÁLOGO DE EVENTOS DE NOTIFICACIÓN
 * ═══════════════════════════════════════════════════════════
 *
 * Un solo sitio donde se declara qué pasa cuando ocurre un evento.
 * El código que lo dispara solo llama a notificarEvento($pdo, 'clave', [ids]).
 *
 * Campos de cada evento:
 *   canales            → lista de canales que lo reciben (ver despachador.php)
 *   clave              → callable(array $datos): string  — idempotencia: misma clave
 *                        + mismo canal + mismo destinatario = se envía UNA sola vez
 *   reactiva_supresion → true si recibir este evento cuenta como "el usuario volvió a
 *                        escribirnos" y reactiva sus avisos tras una baja
 *
 * Fase 1: solo email al usuario que deja una reseña.
 * Fase 3 añadirá canales 'whatsapp_admins' a estos mismos eventos.
 * ═══════════════════════════════════════════════════════════
 */

if (!function_exists('notifCatalogoEventos')) {

    function notifCatalogoEventos(): array
    {
        return [
            'resena.recibida' => [
                'canales'            => ['email_usuario'],
                'clave'              => fn(array $d) => 'resena.recibida:' . (int)$d['resena_id'],
                'reactiva_supresion' => true,
            ],
            'resena.aprobada' => [
                'canales'            => ['email_usuario', 'email_negocio'],   // autor + negocio (si es positiva)
                // Una sola vez por reseña aunque se pause y se vuelva a aprobar
                'clave'              => fn(array $d) => 'resena.aprobada:' . (int)$d['resena_id'],
                'reactiva_supresion' => false,
            ],
            // ── Alta de negocio: avisos al SOLICITANTE ──
            'solicitud.recibida' => [
                'canales'            => ['email_solicitante'],
                'clave'              => fn(array $d) => 'solicitud.recibida:' . (int)$d['solicitud_id'],
                'reactiva_supresion' => true,
            ],
            'solicitud.aprobada' => [
                'canales'            => ['email_solicitante'],
                'clave'              => fn(array $d) => 'solicitud.aprobada:' . (int)$d['solicitud_id'],
                'reactiva_supresion' => false,
            ],
            'solicitud.rechazada' => [
                'canales'            => ['email_solicitante'],
                // Si se re-evalúa y se rechaza con OTRO motivo se vuelve a avisar; con el mismo, no
                'clave'              => fn(array $d) => 'solicitud.rechazada:' . (int)$d['solicitud_id'] . ':' . substr(md5((string)($d['motivo'] ?? '')), 0, 8),
                'reactiva_supresion' => false,
            ],
            // 'resena.rechazada' → deliberadamente SIN aviso al usuario (decisión del 2026-10-12)
        ];
    }
}
