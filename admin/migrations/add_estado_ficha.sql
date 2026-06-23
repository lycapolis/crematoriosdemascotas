-- ═══════════════════════════════════════════════════════════
-- MIGRATION: Ciclo de vida de la ficha (estado) + preservación histórica
-- ═══════════════════════════════════════════════════════════
-- 1. crematorios.estado  → ENUM con ciclo de vida real
-- 2. Migra el binario `activo` actual al nuevo enum
-- 3. outbound_clicks.crematorio_nombre → preserva contexto al borrar fichas
--
-- Estados:
--   activa     → operativa, visible al público (default)
--   pausada    → pausa temporal (vacaciones, etc.) — invisible
--   cerrada    → cerró definitivamente — visible con badge "Cerrado"
--   archivada  → soft-delete — invisible, recuperable
--
-- La columna `activo` se MANTIENE como cache sincronizado (1 solo si
-- estado='activa') para no romper queries existentes que la usan.
-- ═══════════════════════════════════════════════════════════

ALTER TABLE crematorios
    ADD COLUMN estado ENUM('activa','pausada','cerrada','archivada')
        NOT NULL DEFAULT 'activa' AFTER activo;

-- Migración de datos: activo=1 → activa, activo=0 → pausada
UPDATE crematorios SET estado = 'activa'  WHERE activo = 1;
UPDATE crematorios SET estado = 'pausada' WHERE activo = 0 OR activo IS NULL;

CREATE INDEX idx_crematorios_estado ON crematorios (estado);

-- Preservar nombre del negocio en los clicks (registro histórico aunque
-- la ficha se borre). leads_b2c YA tiene crematorio_nombre.
ALTER TABLE outbound_clicks
    ADD COLUMN crematorio_nombre VARCHAR(200) NULL DEFAULT NULL AFTER crematorio_id;

-- Backfill: copiar el nombre actual de los crematorios existentes
UPDATE outbound_clicks oc
JOIN crematorios c ON c.id = oc.crematorio_id
SET oc.crematorio_nombre = c.nombre
WHERE oc.crematorio_nombre IS NULL;
