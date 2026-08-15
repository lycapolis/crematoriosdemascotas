-- ═══════════════════════════════════════════════════════════
-- MIGRATION: configuración de proveedor/modelo IA por sección
-- ═══════════════════════════════════════════════════════════
-- Permite a un super_admin elegir, por cada tarea de IA del panel
-- (texto o visión), qué proveedor (claude | openrouter) y qué modelo
-- usar — sin tocar código. Ver admin/configuracion-ia.php y el wrapper
-- unificado llamarLLM() en includes/funciones.php.
--
-- El seed de abajo replica EXACTAMENTE el proveedor/modelo/max_tokens
-- que ya estaba hardcodeado en cada punto de llamada antes de esta
-- migración — no cambia ningún comportamiento existente hasta que un
-- super_admin edite una fila desde el panel.
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS ia_config_secciones (
    seccion         VARCHAR(40)   NOT NULL PRIMARY KEY,
    label           VARCHAR(120)  NOT NULL,
    tipo            ENUM('texto','vision') NOT NULL DEFAULT 'texto',
    proveedor       ENUM('claude','openrouter') NOT NULL DEFAULT 'claude',
    modelo          VARCHAR(100)  NOT NULL,
    max_tokens      INT           NOT NULL DEFAULT 1500,
    actualizado_en  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por INT           DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO ia_config_secciones
    (seccion, label, tipo, proveedor, modelo, max_tokens)
VALUES
    ('horarios',              'Horarios (interpretar texto libre)',            'texto',  'claude', 'claude-haiku-4-5-20251001',  1500),
    ('contenido',              'Descripción — sugerir mejorada',                'texto',  'claude', 'claude-haiku-4-5-20251001',  2000),
    ('descripcion_avanzada',   'Descripción avanzada (SEO + IA-search)',        'texto',  'claude', 'claude-sonnet-4-5-20250929', 2500),
    ('cobertura',              'Zonas y ciudades de cobertura',                 'texto',  'claude', 'claude-haiku-4-5-20251001',   800),
    ('servicios',              'Detectar servicios booleanos',                  'texto',  'claude', 'claude-haiku-4-5-20251001',   700),
    ('seo',                    'Meta description SEO',                          'texto',  'claude', 'claude-haiku-4-5-20251001',   600),
    ('precios',                'Estructurar precios desde texto libre',          'texto',  'claude', 'claude-haiku-4-5-20251001',  1500),
    ('slug',                   'Generar slug único al aprobar solicitud',        'texto',  'claude', 'claude-haiku-4-5-20251001',   200),
    ('vision_categoria',       'Analizar imagen: categoría + alt + slug',        'vision', 'claude', 'claude-sonnet-4-6',           300),
    ('vision_alt_text',        'Re-generar alt text de imágenes',               'vision', 'claude', 'claude-sonnet-4-6',           200),
    ('mensaje_whatsapp',       'Mensaje WhatsApp — variante con IA',            'texto',  'openrouter', 'openai/gpt-4o-mini',      400);
