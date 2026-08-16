-- ───────────────────────────────────────────────────────────────────────────
-- Rate-limit por IP para los endpoints de api-ai/asistente/*.php (protegidos
-- hoy solo por API key, sin límite de requests). Mismo patrón que
-- solicitudes_rate_limit / resenas_rate_limit: ventana fija + contador,
-- falla abierta si la BD no responde. Ver asistenteRateLimitOk() en
-- includes/funciones.php.
-- Idempotente (IF NOT EXISTS). Aplicar una vez.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS api_asistente_rate_limit (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash         CHAR(64) NOT NULL,
    endpoint        VARCHAR(60) NOT NULL,
    ventana         DATETIME NOT NULL COMMENT 'Ventana de 1 minuto (fecha truncada a minuto)',
    intentos        INT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_ip_endpoint_ventana (ip_hash, endpoint, ventana),
    KEY idx_actualizado (actualizado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
