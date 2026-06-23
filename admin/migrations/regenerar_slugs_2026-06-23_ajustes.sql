-- ═══════════════════════════════════════════════════════════════════
-- AJUSTES POST-REVIEW — 5 slugs ajustados — 2026-06-23 (2da iteración)
-- ═══════════════════════════════════════════════════════════════════
-- Después de aplicar la regeneración masiva (admin/migrations/regenerar_slugs_2026-06-23.sql)
-- el usuario revisó casos límite y aprobó 5 ajustes finos:
--   #6  cel-amic: pasa de "crematorio-mascotas" → "funeraria-mascotas" (es Funeraria)
--   #14 incivet: agrega "tanatorio" (el negocio es tanatorio + crematorio)
--   #20 semper-fidelis: agrega "tanatorio" delante (es Tanatorio)
--   #27 elisia: quita "crematorio" (es Cementerio de mascotas, no crematorio)
--   #47 campo-de-gibraltar: agrega "tanatorio" delante (es Tanatorio)
-- ═══════════════════════════════════════════════════════════════════

UPDATE crematorios SET slug = 'cel-amic-funeraria-mascotas-sant-fruitos-de-bages',     updated_at = NOW() WHERE id = 6;
UPDATE crematorios SET slug = 'incivet-tanatorio-crematorio-mascotas-chapineria',      updated_at = NOW() WHERE id = 14;
UPDATE crematorios SET slug = 'tanatorio-mascotas-semper-fidelis-esquivias',           updated_at = NOW() WHERE id = 20;
UPDATE crematorios SET slug = 'elisia-cementerio-mascotas-pozo-los-palos',             updated_at = NOW() WHERE id = 27;
UPDATE crematorios SET slug = 'tanatorio-mascotas-campo-de-gibraltar-algeciras',       updated_at = NOW() WHERE id = 47;

-- Verificación
SELECT id, slug FROM crematorios WHERE id IN (6, 14, 20, 27, 47);
