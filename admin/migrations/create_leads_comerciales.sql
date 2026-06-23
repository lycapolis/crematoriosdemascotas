-- ───────────────────────────────────────────────────────────────────────────
-- Leads comerciales B2B (form "Promocionar mi crematorio": popup + futura landing)
-- Antes solo iban a email/webhooks → ahora se persisten + bandeja en admin.
-- Idempotente.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS leads_comerciales (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(150) NOT NULL,
    nombre_negocio  VARCHAR(200) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    telefono        VARCHAR(50)  NOT NULL,
    ciudad          VARCHAR(120) NULL,
    mensaje         TEXT NULL,
    origen          VARCHAR(20)  NOT NULL DEFAULT 'popup'
        COMMENT 'popup | landing',
    estado          ENUM('nuevo','en_proceso','cerrado','descartado') NOT NULL DEFAULT 'nuevo',
    notas_admin     TEXT NULL,
    ip_address      VARCHAR(45)  NULL,
    page_url        VARCHAR(500) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_estado (estado),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
