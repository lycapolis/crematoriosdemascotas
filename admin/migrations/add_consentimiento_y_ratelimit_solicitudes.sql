-- ───────────────────────────────────────────────────────────────────────────
-- Registro público: persistir consentimientos (RGPD) + rate-limit anti-spam.
-- Idempotente (IF NOT EXISTS). Aplicar una vez.
-- ───────────────────────────────────────────────────────────────────────────

-- Consentimientos del formulario de registro (antes solo se validaban en JS).
ALTER TABLE solicitudes_registro
    ADD COLUMN IF NOT EXISTS consentimiento TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Acepta revisión + inclusión en el directorio'
        AFTER comentarios_admin,
    ADD COLUMN IF NOT EXISTS consentimiento_comunicaciones TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Acepta notificaciones de su ficha + ofertas comerciales (marketing)'
        AFTER consentimiento,
    ADD COLUMN IF NOT EXISTS consentimiento_fecha DATETIME NULL
        COMMENT 'Fecha/hora en que se otorgaron ambos consentimientos'
        AFTER consentimiento_comunicaciones;

-- Rate-limit por IP para el form de registro (mismo patrón que resenas_rate_limit).
CREATE TABLE IF NOT EXISTS solicitudes_rate_limit (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash         CHAR(64) NOT NULL,
    ventana         DATETIME NOT NULL,
    intentos        INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ip_ventana (ip_hash, ventana),
    KEY idx_actualizado (actualizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
