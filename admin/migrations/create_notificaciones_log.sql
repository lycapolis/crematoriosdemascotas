-- ═══════════════════════════════════════════════════════════
-- MIGRATION: capa de notificaciones por evento (fase 1)
-- ═══════════════════════════════════════════════════════════
-- Crea:
--   notificaciones_log          → qué se envió (o falló/omitió), con idempotencia
--   notificaciones_supresiones  → emails que pidieron no recibir avisos
--
-- Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < create_notificaciones_log.sql
-- (en producción: phpMyAdmin)
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS notificaciones_log (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento        VARCHAR(60)  NOT NULL,                 -- ej: resena.recibida
    clave         VARCHAR(120) NOT NULL,                 -- idempotencia, ej: resena.aprobada:123
    canal         VARCHAR(30)  NOT NULL,                 -- email_usuario, (futuro) whatsapp_admins…
    destinatario  VARCHAR(190) NOT NULL,                 -- email o teléfono
    estado        ENUM('pendiente','enviada','fallida','omitida') NOT NULL DEFAULT 'pendiente',
    motivo        VARCHAR(60)  NULL,                     -- por qué se omitió (baja, fuente_no_propia…)
    error         VARCHAR(500) NULL,
    intentos      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviado_en    DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notif_clave_canal_dest (clave, canal, destinatario),
    KEY idx_notif_evento (evento, created_at),
    KEY idx_notif_estado (estado, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bajas de notificaciones (NO afecta a marketing). Se guarda el hash del email.
-- suprimido=1 → no se envían avisos. Si el mismo email envía algo nuevo (reseña),
-- se reactiva: suprimido=0 + reactivado_en.
CREATE TABLE IF NOT EXISTS notificaciones_supresiones (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_hash    CHAR(64)     NOT NULL,                 -- sha256(lower(trim(email)))
    suprimido     TINYINT(1)   NOT NULL DEFAULT 1,
    baja_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reactivado_en DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_supresion_email (email_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
