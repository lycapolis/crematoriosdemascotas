-- Añade columna de reglas de contacto a la tabla tiers.
-- JSON: {"sidebar":"negocio|soporte","burbuja":"negocio|soporte"}
-- NULL = fallback hardcodeado (estándar de la matriz de negocio).

ALTER TABLE tiers
    ADD COLUMN contacto_reglas JSON NULL
    COMMENT 'Reglas de destino del WhatsApp por contexto';

-- El fallback por defecto según la matriz de negocio:
--   sidebar: negocio; burbuja: negocio.
-- Este UPDATE marca explícitamente la regla actual para los tiers existentes,
-- de modo que el admin pueda cambiarla más tarde desde editar-tier.php.
UPDATE tiers SET contacto_reglas = JSON_OBJECT('sidebar', 'negocio', 'burbuja', 'negocio')
WHERE contacto_reglas IS NULL;
