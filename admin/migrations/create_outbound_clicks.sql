-- ───────────────────────────────────────────────────────────────────────────
-- Outbound clicks (tracking ligero de salidas del dominio)
-- Loguea TODOS los clicks salientes interceptados, aunque el usuario NO
-- complete el form de lead-capture (saltó / canceló).
-- Sin datos personales — solo métricas anónimas.
-- Permite calcular: clicks_totales, completion_rate por ficha y por acción.
-- Idempotente.
-- ───────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS outbound_clicks (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,

    crematorio_id   INT UNSIGNED NULL
        COMMENT 'NULL si clic fuera de una ficha (home, directorio, etc)',
    accion          VARCHAR(20)  NOT NULL
        COMMENT 'tel | wa | maps | web',
    destino_url     VARCHAR(500) NULL,
    pagina_origen   VARCHAR(500) NULL,
    modal_action    VARCHAR(20)  NOT NULL DEFAULT 'click'
        COMMENT 'click | sent | skipped | cancelled — qué hizo el usuario en el modal',

    -- Tracking técnico mínimo
    ip              VARCHAR(45)  NULL,
    user_agent      TEXT NULL,
    referrer        VARCHAR(500) NULL,

    -- Si el clic terminó en lead completado, referencia al lead_b2c
    lead_b2c_id     INT UNSIGNED NULL
        COMMENT 'FK a leads_b2c.id si modal_action = sent',

    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_crematorio (crematorio_id),
    KEY idx_accion (accion),
    KEY idx_modal_action (modal_action),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
