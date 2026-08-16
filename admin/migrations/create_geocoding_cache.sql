-- ───────────────────────────────────────────────────────────────────────────
-- Caché de geocoding (ciudad → lat/lng) para no gastar cuota de Google Maps
-- en cada request. Las coordenadas de una ciudad no cambian, así que el
-- caché no tiene expiración. Usado por geocodificarCiudadCache() en
-- includes/funciones.php (radio_km real en api-ai/asistente/recomendar-crematorios.php).
-- Idempotente (IF NOT EXISTS). Aplicar una vez.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS geocoding_cache (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    query       VARCHAR(190) NOT NULL COMMENT 'Texto normalizado (minúsculas, trim) usado como clave de caché',
    lat         DECIMAL(10,7) NOT NULL,
    lng         DECIMAL(10,7) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_query (query)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
