-- ═══════════════════════════════════════════════════════════
-- MIGRATION: precios de la ficha (precios_json)
-- ═══════════════════════════════════════════════════════════
-- Lista de ítems de precio. Cada ítem:
--   {
--     id: "p1",
--     tipo: "rango" | "fijo" | "desde" | "custom",
--     nombre: "Cremación individual perro pequeño",
--     descripcion: "Hasta 10 kg, incluye urna básica",
--     min: 120,            (número o null)
--     max: 200,            (número o null — solo para tipo=rango)
--     destacado: false
--   }
-- Visualización:
--   fijo   → "120 €"
--   desde  → "Desde 120 €"
--   rango  → "120 € – 200 €"
--   custom → solo nombre + descripción (sin monto: "Consultar", etc.)
-- ═══════════════════════════════════════════════════════════

ALTER TABLE crematorios
    ADD COLUMN precios_json LONGTEXT NULL DEFAULT NULL AFTER metas_json;
