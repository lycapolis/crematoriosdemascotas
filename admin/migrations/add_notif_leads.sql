-- ═══════════════════════════════════════════════════════════
-- MIGRATION: Notificación al negocio cuando recibe un lead B2C
-- ═══════════════════════════════════════════════════════════
-- Agrega:
--   1. leads_b2c.negocio_notificado  → flag de envío exitoso
--   2. leads_b2c.negocio_notificado_at → timestamp del envío
--   3. crematorios.recibe_notif_leads  → opt-in (default 1)
--   4. crematorios.email_notif_leads   → email alternativo (NULL = usa el público)
-- ═══════════════════════════════════════════════════════════

ALTER TABLE leads_b2c
    ADD COLUMN negocio_notificado    TINYINT(1) NOT NULL DEFAULT 0 AFTER webhook_enviado,
    ADD COLUMN negocio_notificado_at DATETIME       NULL DEFAULT NULL AFTER negocio_notificado;

ALTER TABLE crematorios
    ADD COLUMN recibe_notif_leads TINYINT(1)   NOT NULL DEFAULT 1 AFTER tier,
    ADD COLUMN email_notif_leads  VARCHAR(190)     NULL DEFAULT NULL AFTER recibe_notif_leads;

-- Índice para queries de throttle (último envío al mismo crematorio)
CREATE INDEX idx_leads_b2c_notif_negocio ON leads_b2c (crematorio_id, negocio_notificado_at);
