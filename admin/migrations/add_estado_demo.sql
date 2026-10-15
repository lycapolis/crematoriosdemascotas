-- ═══════════════════════════════════════════════════════════
-- MIGRATION: estado «demo» en el ciclo de vida de la ficha
-- ═══════════════════════════════════════════════════════════
-- demo → ficha de demostración: operativa por URL directa, con noindex,
--        fuera de listados, mapas, sitemap y métricas (activo = 0).
-- Ejecutar con utf8mb4 (phpMyAdmin o --default-character-set=utf8mb4).
-- ═══════════════════════════════════════════════════════════

ALTER TABLE crematorios
    MODIFY COLUMN estado ENUM('activa','pausada','cerrada','archivada','demo')
        NOT NULL DEFAULT 'activa';
