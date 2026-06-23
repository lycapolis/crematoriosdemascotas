-- Migración: visibilidad de fotos de cliente — UN solo campo, 4 niveles anidados.
-- (Reemplaza el modelo de flags sueltos; ocultar_de_galeria_principal se mantiene
--  como columna legacy sincronizada, no se borra, pero el render usa 'visibilidad'.)
--
-- Superficies públicas de una foto de cliente (reseña APROBADA):
--   #1+#2) galerías del negocio (principal + por categoría) — mismo pool 'galeria'
--   #3)    galería separada "Fotos de clientes"
--   #4)    mini-galería bajo su propia reseña
--
--   completa               → #1+#2 ✓ · #3 ✓ · #4 ✓   (default, = comportamiento actual sin flag)
--   solo_galerias_cliente  → #1+#2 ✗ · #3 ✓ · #4 ✓   (= ocultar_de_galeria_principal=1 actual)
--   solo_resena            → #1+#2 ✗ · #3 ✗ · #4 ✓
--   oculta                 → nada
--
-- Nada de esto aplica si la reseña no está aprobada (en ese caso no se ve, igual que hoy).

ALTER TABLE crematorio_imagenes
    ADD COLUMN IF NOT EXISTS visibilidad
        ENUM('completa','solo_galerias_cliente','solo_resena','oculta')
        NOT NULL DEFAULT 'completa'
        COMMENT 'Solo fotos tipo=cliente: nivel de visibilidad pública (4 niveles anidados)'
        AFTER ocultar_de_galeria_principal;

-- Backfill: las que hoy tienen el flag viejo conservan ese comportamiento exacto.
UPDATE crematorio_imagenes
   SET visibilidad = 'solo_galerias_cliente'
 WHERE tipo = 'cliente'
   AND ocultar_de_galeria_principal = 1
   AND visibilidad = 'completa';
