-- ═══════════════════════════════════════════════════════════
-- MIGRATION: campo de precios en la solicitud de registro
-- ═══════════════════════════════════════════════════════════
-- 1. solicitudes_registro.precios → texto libre OPCIONAL que el negocio
--    carga al registrarse (precios/tarifas orientativas). Al aprobar la
--    solicitud, este texto pasa a crematorios.texto_origen_json['precios_texto']
--    como referencia inmutable — misma mecánica que servicios_texto/horarios_texto.
--
-- 2. Se elimina crematorios.precios_texto (columna editable que se había
--    creado por error). El texto de precios NO es un campo editable del
--    admin: vive en texto_origen_json (fuente inmutable). El admin estructura
--    los precios con el botón IA o los carga a mano con "+ Agregar precio".
-- ═══════════════════════════════════════════════════════════

ALTER TABLE solicitudes_registro
    ADD COLUMN precios TEXT NULL DEFAULT NULL AFTER horarios;

ALTER TABLE crematorios
    DROP COLUMN precios_texto;
