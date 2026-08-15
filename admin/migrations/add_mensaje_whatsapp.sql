-- ═══════════════════════════════════════════════════════════
-- MIGRATION: mensaje pre-formateado para WhatsApp (asistente IA / N8N)
-- ═══════════════════════════════════════════════════════════
-- mensajes_whatsapp_json: array de versiones candidatas, mismo patrón que
-- descripciones_json / metas_json. Cada ítem:
--   {
--     id: "w1",
--     origen: "auto" | "manual" | "ia",
--     valor: "🐾 Nombre\n📍 Ciudad...",
--     activo: true,
--     fecha: "2026-08-15"
--   }
-- "auto"   = plantilla determinística (sin IA), se regenera sola al guardar
--            la ficha si es la versión activa.
-- "manual" = editada a mano por el admin.
-- "ia"     = redactada por el modelo configurado en ia_config_secciones
--            (sección 'mensaje_whatsapp'), para variar la redacción.
--
-- mensaje_whatsapp: flat, sincronizado desde la versión "activo":true del
-- JSON de arriba — es lo que consulta el asistente vía
-- api-ai/asistente/recomendar-crematorios.php (evita parsear JSON en cada
-- request del bot).
-- ═══════════════════════════════════════════════════════════

ALTER TABLE crematorios
    ADD COLUMN mensajes_whatsapp_json LONGTEXT NULL DEFAULT NULL AFTER precios_json,
    ADD COLUMN mensaje_whatsapp        TEXT     NULL DEFAULT NULL AFTER mensajes_whatsapp_json;
