-- ───────────────────────────────────────────────────────────────────────────
-- Respuestas del negocio a una reseña (siempre moderadas antes de publicarse).
-- Una reseña admite UNA respuesta viva (pendiente o aprobada); si se rechaza,
-- el negocio puede reintentarlo pasadas 24 h. Idempotente. Aplicar una vez
-- (phpMyAdmin o consola con --default-character-set=utf8mb4).
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS resenas_respuestas (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    resena_id       INT UNSIGNED NOT NULL,
    texto           TEXT NOT NULL,
    estado          ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    motivo_rechazo  VARCHAR(500) NULL,
    ip_hash         CHAR(64) NULL COMMENT 'sha256 de la IP (anti-abuso, no se guarda la IP)',
    moderado_por    INT UNSIGNED NULL,
    moderado_en     TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_resena_estado (resena_id, estado),
    KEY idx_estado_fecha (estado, created_at),
    KEY idx_ip_fecha (ip_hash, created_at),
    CONSTRAINT fk_respuesta_resena    FOREIGN KEY (resena_id)    REFERENCES resenas (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_respuesta_moderador FOREIGN KEY (moderado_por) REFERENCES admins (id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
