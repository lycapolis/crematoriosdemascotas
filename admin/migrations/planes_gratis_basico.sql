-- ═══════════════════════════════════════════════════════════
-- MIGRATION: plan 00 «Gratis» + plan 01 «Básico» de pago (49 €/mes)
-- ═══════════════════════════════════════════════════════════
-- Decisiones (2026-10-12):
--   · Nace el plan 00 «Gratis», clon de las reglas ACTUALES del plan 01
--     (mismo aspecto público que hoy) pero con contacto → soporte del portal.
--   · El plan 01 pasa a «Básico» de pago: 49 €/mes, contacto → soporte del portal
--     (lateral y burbuja) y una ventaja mínima sobre el Gratis (mapa de Google en la ficha).
--   · TODAS las fichas pasan al plan 00.
--
-- ANTES DE CORRERLA EN PRODUCCIÓN: copia de seguridad de las tablas `tiers` y `crematorios`
-- (phpMyAdmin → Exportar). Es idempotente: se puede correr dos veces sin efectos.
-- Correr con utf8mb4:
--   mysql --default-character-set=utf8mb4 crematorios_mascotas < planes_gratis_basico.sql
-- ═══════════════════════════════════════════════════════════

-- 1) Crear el plan 00 clonando las reglas del 01 (ANTES de modificar el 01)
INSERT INTO tiers (
    id, nombre, descripcion, precio_mensual,
    logo_mostrar, logo_fuentes,
    portada_mostrar, portada_fuentes,
    galeria_principal_mostrar, galeria_principal_fuentes,
    galeria_categorias_mostrar, galeria_categorias_fuentes,
    activo, contacto_reglas
)
SELECT
    '00',
    'Plan 00 — Gratis',
    'Ficha del negocio en el directorio con su información pública y fotos. Las consultas de los visitantes se gestionan a través del portal.',
    0.00,
    t01.logo_mostrar, t01.logo_fuentes,
    t01.portada_mostrar, t01.portada_fuentes,
    t01.galeria_principal_mostrar, t01.galeria_principal_fuentes,
    t01.galeria_categorias_mostrar, t01.galeria_categorias_fuentes,
    1,
    '{"sidebar": "soporte", "burbuja": "soporte"}'
FROM tiers AS t01
WHERE t01.id = '01'
ON DUPLICATE KEY UPDATE id = tiers.id;

-- 2) El plan 01 pasa a «Básico» de pago
UPDATE tiers SET
    nombre         = 'Plan 01 — Básico',
    descripcion    = 'Todo lo del plan Gratis, con mapa de Google en la ficha. Recibe aviso de cada consulta que llega a su ficha.',
    precio_mensual = 49.00,
    contacto_reglas = '{"sidebar": "soporte", "burbuja": "soporte"}'
WHERE id = '01';

-- 2b) Imágenes: en TODOS los planes (también el Gratis) solo se muestran las alojadas en
--     nuestro servidor (procesadas y optimizadas), nunca por URL externa. Mejor experiencia.
UPDATE tiers SET
    logo_fuentes               = '["local"]',
    portada_fuentes            = '["local"]',
    galeria_principal_fuentes  = '["local"]',
    galeria_categorias_fuentes = '["local"]'
WHERE id IN ('00', '01', '02', '03');

-- 3) Todas las fichas al plan 00 (Gratis)
UPDATE crematorios SET tier = '00' WHERE tier <> '00';
