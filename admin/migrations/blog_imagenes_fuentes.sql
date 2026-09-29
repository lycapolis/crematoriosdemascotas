-- ═══════════════════════════════════════════════════════════
-- MIGRATION: imágenes del blog desde banco de fotos + IA
-- ═══════════════════════════════════════════════════════════
-- - blog_imagenes: nuevos orígenes (pexels, pixabay, ia) + metadatos SEO
--   (alt, title, caption), crédito del autor y prompt usado.
-- - ia_config_secciones: nuevo tipo 'imagen' + 3 secciones del blog,
--   editables en admin/configuracion-ia.php (reusa OPENROUTER_API_KEY).
-- - Corrige las etiquetas de ia_config_secciones que quedaron con
--   caracteres rotos ("Descripci├│n") al correr la migración original
--   desde la consola de Windows sin UTF-8.
-- Idempotente salvo los ALTER ... ADD COLUMN (correr una sola vez).
--
-- Ejecutar desde phpMyAdmin (pestaña SQL o Importar). Si se usa la consola
-- de MySQL, SIEMPRE con --default-character-set=utf8mb4.
-- ═══════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- ─── Etiquetas legibles (arregla la codificación rota) ─────
UPDATE ia_config_secciones SET label = 'Horarios (interpretar texto libre)'                   WHERE seccion = 'horarios';
UPDATE ia_config_secciones SET label = 'Descripción — sugerir versión mejorada'               WHERE seccion = 'contenido';
UPDATE ia_config_secciones SET label = 'Descripción avanzada (SEO + búsqueda con IA)'         WHERE seccion = 'descripcion_avanzada';
UPDATE ia_config_secciones SET label = 'Zonas y ciudades de cobertura'                        WHERE seccion = 'cobertura';
UPDATE ia_config_secciones SET label = 'Detectar servicios ofrecidos'                         WHERE seccion = 'servicios';
UPDATE ia_config_secciones SET label = 'Meta description SEO'                                 WHERE seccion = 'seo';
UPDATE ia_config_secciones SET label = 'Estructurar precios desde texto libre'                WHERE seccion = 'precios';
UPDATE ia_config_secciones SET label = 'Generar slug único al aprobar una solicitud'          WHERE seccion = 'slug';
UPDATE ia_config_secciones SET label = 'Mensaje de WhatsApp — variante con IA'                WHERE seccion = 'mensaje_whatsapp';
UPDATE ia_config_secciones SET label = 'Analizar imagen de ficha: categoría + alt + slug'     WHERE seccion = 'vision_categoria';
UPDATE ia_config_secciones SET label = 'Regenerar el texto alternativo (alt) de las fichas'   WHERE seccion = 'vision_alt_text';

ALTER TABLE blog_imagenes
    MODIFY origen ENUM('subida','url','pegada','pexels','pixabay','ia') NOT NULL DEFAULT 'subida',
    ADD COLUMN alt_text       VARCHAR(255) NULL AFTER alto,
    ADD COLUMN titulo         VARCHAR(255) NULL AFTER alt_text,
    ADD COLUMN caption        VARCHAR(500) NULL AFTER titulo,
    ADD COLUMN credito_nombre VARCHAR(150) NULL AFTER origen,
    ADD COLUMN credito_url    VARCHAR(500) NULL AFTER credito_nombre,
    ADD COLUMN fuente_id      VARCHAR(60)  NULL AFTER credito_url,
    ADD COLUMN fuente_url     VARCHAR(500) NULL AFTER fuente_id,
    ADD COLUMN prompt_ia      TEXT         NULL AFTER fuente_url,
    ADD COLUMN etiqueta_ia    TINYINT(1)   NOT NULL DEFAULT 0 AFTER prompt_ia,
    ADD COLUMN con_logo       TINYINT(1)   NOT NULL DEFAULT 0 AFTER etiqueta_ia,
    ADD KEY idx_blog_imagenes_ruta (ruta(191));

ALTER TABLE ia_config_secciones
    MODIFY tipo ENUM('texto','vision','imagen') NOT NULL DEFAULT 'texto';

INSERT IGNORE INTO ia_config_secciones
    (seccion, label, tipo, proveedor, modelo, max_tokens)
VALUES
    ('blog_imagen_prompt',  'Blog — proponer búsqueda de fotos y escena para la IA', 'texto',  'openrouter', 'openai/gpt-4o-mini',            500),
    ('blog_imagen_seo',     'Blog — alt, nombre de archivo y pie de foto',           'vision', 'claude',     'claude-haiku-4-5-20251001',     400),
    ('blog_imagen_generar', 'Blog — generar imagen con IA',                           'imagen', 'openrouter', 'google/gemini-2.5-flash-image', 0);
