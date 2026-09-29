-- ═══════════════════════════════════════════════════════════
-- MIGRATION: todas las tareas IA vía OpenRouter + avatar de marca
-- ═══════════════════════════════════════════════════════════
-- 1. Pasa a OpenRouter las secciones que usaban la API directa de Claude
--    (la cuenta de Anthropic quedó sin saldo). Se mantienen EXACTAMENTE los
--    mismos modelos Claude, ahora facturados vía OpenRouter (anthropic/claude-*).
--    El alt de imágenes del blog usa Gemini 2.5 Flash (probado 2026-09-29).
-- 2. El autor "Equipo" usa el avatar de marca (patita del logo invertida).
--
-- Correr DESPUÉS de blog_imagenes_fuentes.sql. Idempotente (se puede repetir).
-- Ejecutar desde phpMyAdmin (pestaña Importar o SQL).
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

UPDATE ia_config_secciones
SET proveedor = 'openrouter',
    modelo = CASE modelo
        WHEN 'claude-haiku-4-5-20251001'  THEN 'anthropic/claude-haiku-4.5'
        WHEN 'claude-sonnet-4-5-20250929' THEN 'anthropic/claude-sonnet-4.5'
        WHEN 'claude-sonnet-4-6'          THEN 'anthropic/claude-sonnet-4.6'
        ELSE modelo
    END
WHERE proveedor = 'claude';

UPDATE ia_config_secciones
SET proveedor = 'openrouter', modelo = 'google/gemini-2.5-flash'
WHERE seccion = 'blog_imagen_seo';

UPDATE blog_autores
SET foto = 'assets/img/marca/avatar-marca.svg'
WHERE foto = 'assets/img/blog-demo/avatar-equipo.webp';

-- Comprobación: todas las filas deberían decir 'openrouter'
SELECT seccion, proveedor, modelo FROM ia_config_secciones ORDER BY tipo, seccion;
