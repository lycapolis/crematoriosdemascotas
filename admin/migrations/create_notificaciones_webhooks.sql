-- ═══════════════════════════════════════════════════════════
-- MIGRATION: canal webhook de la capa de notificaciones
-- ═══════════════════════════════════════════════════════════
-- Crea:
--   notificaciones_webhook_destinos → URLs que reciben el payload (Make, n8n, CRM…)
--   notificaciones_webhook_eventos  → ajustes por evento (activo + variables extra)
--
-- La URL de Make que hoy está en WEBHOOK_URLS (config) se importa sola como primer
-- destino la primera vez que se abre admin/webhooks.php. Mientras la tabla esté vacía
-- (o sin migrar), el canal usa WEBHOOK_URLS como respaldo: nunca se deja de enviar.
--
-- Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < create_notificaciones_webhooks.sql
-- (en producción: phpMyAdmin)
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS notificaciones_webhook_destinos (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(80)  NOT NULL,
    url         VARCHAR(500) NOT NULL,
    secreto     VARCHAR(120) NULL,                 -- si existe, se firma el cuerpo (cabecera X-Firma: sha256=…)
    eventos     TEXT         NULL,                 -- JSON con los eventos que recibe; NULL = todos
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificaciones_webhook_eventos (
    evento      VARCHAR(60)  NOT NULL,             -- ej: resena.recibida
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    extras      TEXT         NULL,                 -- JSON con las variables extra activas; NULL = todas
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
