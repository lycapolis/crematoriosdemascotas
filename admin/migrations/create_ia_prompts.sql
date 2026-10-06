-- ═══════════════════════════════════════════════════════════
-- MIGRATION: prompts de IA editables desde el admin
-- ═══════════════════════════════════════════════════════════
-- - ia_prompts: SOLO guarda lo que el super_admin edita (texto + temas a evitar).
--   Si una clave no tiene fila, el código usa su texto original
--   (catálogo en includes/ia-prompts.php). "Restaurar original" = borrar la fila.
-- - ia_prompts_versiones: historial de cada guardado/restauración.
-- Nombre, "qué hace" y "dónde se ve" de cada prompt viven en el catálogo del código.
--
-- Idempotente (se puede correr más de una vez). No inserta filas.
-- Ejecutar desde phpMyAdmin (pestaña SQL o Importar). Si se usa la consola
-- de MySQL, SIEMPRE con --default-character-set=utf8mb4.
-- ═══════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS ia_prompts (
    clave       VARCHAR(60)  NOT NULL PRIMARY KEY,
    texto       MEDIUMTEXT   NOT NULL,
    evitar      TEXT         NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by  INT          NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_prompts_versiones (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    clave       VARCHAR(60)  NOT NULL,
    texto       MEDIUMTEXT   NOT NULL,
    evitar      TEXT         NULL,
    nota        VARCHAR(120) NULL,
    creado_en   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_por  INT          NULL,
    KEY idx_clave_fecha (clave, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
