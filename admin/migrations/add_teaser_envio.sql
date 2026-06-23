-- ═══════════════════════════════════════════════════════════
-- MIGRATION: tracking de envío del teaser ofuscado
-- ═══════════════════════════════════════════════════════════
-- Agrega:
--   crematorios.teaser_ultimo_envio → DATETIME del último teaser enviado
-- Usado por cron/enviar-teasers-leads.php para throttle por frecuencia.
-- ═══════════════════════════════════════════════════════════

ALTER TABLE crematorios
    ADD COLUMN teaser_ultimo_envio DATETIME NULL DEFAULT NULL AFTER email_notif_leads;

CREATE INDEX idx_crematorios_teaser_envio ON crematorios (teaser_ultimo_envio);
