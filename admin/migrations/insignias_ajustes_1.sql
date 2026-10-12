-- Ajustes del sistema de insignias (tras revisión visual 2026-10-12).
--   mysql -uroot --default-character-set=utf8mb4 crematorios_mascotas < admin/migrations/insignias_ajustes_1.sql
-- Re-ejecutable.

-- 1) «Promocionado» queda DESACTIVADO hasta que exista un cliente que pague pauta.
--    Se quitan las 3 asignaciones que venían del antiguo `destacado` manual.
UPDATE insignias SET activa = 0 WHERE slug = 'promocionado';
DELETE f FROM insignias_ficha f JOIN insignias i ON i.id = f.insignia_id WHERE i.slug = 'promocionado';

-- 2) «Popular ahora»: opinión positiva reciente en el portal → 30 días de visibilidad extra.
--    Regla `resena_reciente`: reseña propia, aprobada, no spam, con calificación >= min_calificacion,
--    cuya fecha de aprobación (o de alta si no hay) cae dentro de los últimos `dias` días.
INSERT IGNORE INTO insignias (slug, nombre, tooltip, glosario, icono, color, tipo, regla, niveles, puntos, visible_tarjeta, visible_ficha, orden) VALUES
('popular_ahora', 'Popular ahora',
 'Ha recibido una opinión positiva reciente en nuestro portal y cumple nuestro criterio de popularidad. Haz clic para ver el detalle.',
 'Este negocio ha recibido hace poco una opinión de 4 o 5 estrellas de otra familia en nuestro portal. Durante los 30 días siguientes mostramos esta insignia y le damos más visibilidad en los listados, porque una opinión reciente es una señal muy útil. Un negocio también puede ser popular por otros motivos (muchos contactos en su provincia): eso se indica con la insignia «Popular». Es difícil conseguirla, así que cuando la veas, vale la pena echar un vistazo.',
 'trending-up', '#059669', 'auto',
 '{"tipo":"resena_reciente","dias":30,"min_calificacion":4}', NULL, 20, 1, 1, 45);

-- 3) El tooltip de «Popular» (3 niveles) también remite al glosario para ver el criterio completo.
UPDATE insignias SET niveles = '[{"nivel":1,"nombre":"Popular","tooltip":"Popular: cumple nuestro criterio de popularidad; muchas familias de su provincia contactan con este negocio. Haz clic para ver el detalle."},{"nivel":2,"nombre":"Muy popular","tooltip":"Muy popular: cumple nuestro criterio de popularidad y está entre los negocios más contactados de su provincia. Haz clic para ver el detalle."},{"nivel":3,"nombre":"Más popular","tooltip":"Más popular: cumple nuestro criterio de popularidad y es el negocio más contactado de su provincia. Haz clic para ver el detalle."}]'
 WHERE slug = 'popular';
