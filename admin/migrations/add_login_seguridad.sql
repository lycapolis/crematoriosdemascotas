-- ───────────────────────────────────────────────────────────────────────────
-- Login admin: bloqueo por intentos fallidos + rate-limit por IP.
-- Idempotente (IF NOT EXISTS). Aplicar una vez.
-- ───────────────────────────────────────────────────────────────────────────

-- Bloqueo por cuenta: se incrementa en cada password incorrecto,
-- se resetea a 0 en login exitoso. Al llegar al umbral (ver admin/auth.php)
-- se setea bloqueado_hasta y no se puede loguear hasta que pase esa fecha.
ALTER TABLE admins
    ADD COLUMN IF NOT EXISTS intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Intentos de login fallidos consecutivos',
    ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL DEFAULT NULL
        COMMENT 'Si está seteado y es futuro, la cuenta no puede loguearse';

-- Rate-limit por IP para /admin/login.php (mismo patrón que solicitudes_rate_limit
-- / resenas_rate_limit / api_asistente_rate_limit). Ventana corta (10 min) porque
-- es un endpoint sensible a fuerza bruta, independiente de qué email se pruebe.
CREATE TABLE IF NOT EXISTS admin_login_rate_limit (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash         CHAR(64) NOT NULL,
    ventana         DATETIME NOT NULL,
    intentos        INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ip_ventana (ip_hash, ventana),
    KEY idx_actualizado (actualizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
