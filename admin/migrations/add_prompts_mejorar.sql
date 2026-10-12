-- ═══════════════════════════════════════════════════════════
-- MIGRATION: tarea de IA «mejorar prompts» (panel admin/prompts-ia.php, Fase 2)
-- ═══════════════════════════════════════════════════════════
-- Registra la sección usada por el botón «Mejorar con IA». El modelo se puede
-- cambiar después en admin/configuracion-ia.php. Idempotente.
-- Ejecutar desde phpMyAdmin (pestaña SQL).
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

INSERT IGNORE INTO ia_config_secciones
    (seccion, label, tipo, proveedor, modelo, max_tokens)
VALUES
    ('prompts_mejorar', 'Panel de prompts — mejorar un prompt con IA', 'texto', 'openrouter', 'anthropic/claude-sonnet-4.6', 3000);
