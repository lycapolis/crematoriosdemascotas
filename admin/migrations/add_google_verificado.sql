-- Migración: estado de verificación del Google Business Profile (señal B2B).
-- SEPARADA de `verificado` (que es el flag del directorio/Lycapolis, editable
-- por el admin). Antes el importador escribía `verified` del CSV en `verificado`
-- con un cast roto (limpiarEntero("True")=0) → se corrige aparte.
--
--   google_verificado = 1     → GBP verificado/reclamado por el dueño
--   google_verificado = 0     → NO verificado  (lead B2B: oportunidad Lycapolis)
--   google_verificado = NULL  → sin dato (no se pudo determinar)

ALTER TABLE crematorios
    ADD COLUMN IF NOT EXISTS google_verificado TINYINT(1) DEFAULT NULL
        COMMENT 'Verificación del Google Business Profile (1=sí,0=no,NULL=sin dato). Distinto de `verificado` (flag del directorio).'
        AFTER verificado;
