-- ───────────────────────────────────────────────────────────────────────────
-- Leads B2C (consumidores finales contactando negocios listados)
-- Capturados por el widget lead-capture interno (reemplaza al Lycapolis).
-- Datos completos: solo cuando el usuario completa el form.
-- Backward-compat con el JSON del widget anterior + campos nuevos contextuales.
-- Idempotente.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS leads_b2c (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Contexto del clic / acción
    channel_type        VARCHAR(20)  NOT NULL
        COMMENT 'tel | wa | maps | web',
    accion_destino      VARCHAR(500) NULL
        COMMENT 'URL final a la que se redirige al usuario (wa.me/tel:/maps/web)',
    crematorio_id       INT UNSIGNED NULL
        COMMENT 'NULL si form genérico (no estaba en ficha)',
    crematorio_nombre   VARCHAR(200) NULL
        COMMENT 'denormalizado para histórico — la ficha puede cambiar',
    phone_agent         VARCHAR(50)  NULL
        COMMENT 'tel/whatsapp del negocio destino (si aplica)',
    pagina_origen       VARCHAR(500) NULL
        COMMENT 'URL desde donde se hizo el clic',

    -- Datos del lead (form completado)
    servicio            VARCHAR(50)  NULL
        COMMENT 'Perro | Gato | Otro',
    mascota_tamano      VARCHAR(50)  NULL
        COMMENT 'rango de peso ej "15 - 25 kg"',
    nombre              VARCHAR(200) NOT NULL,
    email               VARCHAR(200) NOT NULL,
    country_code        VARCHAR(2)   NULL
        COMMENT 'ES, PT, MX, AR, etc',
    phone_code          VARCHAR(10)  NULL
        COMMENT 'prefijo telefónico ej 34, 351',
    whatsapp_number     VARCHAR(30)  NULL,
    ciudad_lead         VARCHAR(120) NULL
        COMMENT 'ciudad del USUARIO (no del crematorio)',
    mensaje             TEXT NULL,

    -- Tracking técnico
    ip                  VARCHAR(45)  NULL,
    user_agent          TEXT NULL,
    utm_source          VARCHAR(100) NULL,
    utm_medium          VARCHAR(100) NULL,
    utm_campaign        VARCHAR(100) NULL,
    referrer            VARCHAR(500) NULL,

    -- Estado de procesamiento
    webhook_enviado     TINYINT(1)   NOT NULL DEFAULT 0
        COMMENT '1 si el webhook a Make fue enviado con éxito',
    webhook_error       TEXT NULL,
    notif_negocio       TINYINT(1)   NOT NULL DEFAULT 0
        COMMENT '1 si se notificó al negocio (solo tiers premium)',
    estado              ENUM('nuevo','contactado','cerrado','descartado') NOT NULL DEFAULT 'nuevo',
    notas_admin         TEXT NULL,

    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_crematorio (crematorio_id),
    KEY idx_channel (channel_type),
    KEY idx_created (created_at),
    KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
