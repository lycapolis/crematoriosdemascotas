-- ═══════════════════════════════════════════════════════════
-- CREMATORIOS DE MASCOTAS - IMPORTACIÓN DE DATOS
-- ═══════════════════════════════════════════════════════════
-- Autor: Facundo M. Campos
-- Empresa: Lycapolis LLC
-- Fecha: 2026-01-25
-- Fuente: 20260125-bbdd.csv (99 registros)
-- ═══════════════════════════════════════════════════════════

USE crematorios_mascotas;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────────────────────────────────────
-- PASO 1: INSERTAR COMUNIDADES AUTÓNOMAS
-- ───────────────────────────────────────────────────────────
INSERT INTO comunidades_autonomas (nombre, slug) VALUES
('Andalucía', 'andalucia'),
('Aragón', 'aragon'),
('Castilla y León', 'castilla-y-leon'),
('Castilla-La Mancha', 'castilla-la-mancha'),
('Cataluña', 'cataluna'),
('Comunidad de Madrid', 'comunidad-de-madrid'),
('Comunidad Foral de Navarra', 'comunidad-foral-de-navarra'),
('Comunidad Valenciana', 'comunidad-valenciana'),
('País Vasco', 'pais-vasco'),
('Región de Murcia', 'region-de-murcia');

-- ───────────────────────────────────────────────────────────
-- PASO 2: INSERTAR PROVINCIAS
-- ───────────────────────────────────────────────────────────

-- Andalucía
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'andalucia'), 'Córdoba', 'cordoba'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'andalucia'), 'Málaga', 'malaga'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'andalucia'), 'Sevilla', 'sevilla');

-- Aragón
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'aragon'), 'Huesca', 'huesca'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'aragon'), 'Zaragoza', 'zaragoza');

-- Castilla y León
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-y-leon'), 'Segovia', 'segovia'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-y-leon'), 'Valladolid', 'valladolid'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-y-leon'), 'Zamora', 'zamora');

-- Castilla-La Mancha
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-la-mancha'), 'Ciudad Real', 'ciudad-real'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-la-mancha'), 'Cuenca', 'cuenca'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-la-mancha'), 'Guadalajara', 'guadalajara'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'castilla-la-mancha'), 'Toledo', 'toledo');

-- Cataluña
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'cataluna'), 'Barcelona', 'barcelona'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'cataluna'), 'Girona', 'girona');

-- Comunidad de Madrid
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'comunidad-de-madrid'), 'Madrid', 'madrid');

-- Comunidad Foral de Navarra
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'comunidad-foral-de-navarra'), 'Navarra', 'navarra');

-- Comunidad Valenciana
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'comunidad-valenciana'), 'Alicante', 'alicante'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'comunidad-valenciana'), 'Castellón', 'castellon'),
((SELECT id FROM comunidades_autonomas WHERE slug = 'comunidad-valenciana'), 'Valencia', 'valencia');

-- País Vasco
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'pais-vasco'), 'Gipuzkoa', 'gipuzkoa');

-- Región de Murcia
INSERT INTO provincias (comunidad_id, nombre, slug) VALUES
((SELECT id FROM comunidades_autonomas WHERE slug = 'region-de-murcia'), 'Murcia', 'murcia');

-- ───────────────────────────────────────────────────────────
-- PASO 3: CREAR TABLA TEMPORAL PARA CARGA DEL CSV
-- ───────────────────────────────────────────────────────────
DROP TABLE IF EXISTS tmp_crematorios_csv;
CREATE TABLE tmp_crematorios_csv (
    name VARCHAR(255),
    subtypes VARCHAR(500),
    phone VARCHAR(50),
    website VARCHAR(500),
    address VARCHAR(500),
    street VARCHAR(255),
    city VARCHAR(100),
    county VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(10),
    latitude VARCHAR(20),
    longitude VARCHAR(20),
    rating VARCHAR(10),
    reviews VARCHAR(10),
    reviews_link VARCHAR(1000),
    reviews_per_score TEXT,
    reviews_per_score_1 VARCHAR(10),
    reviews_per_score_2 VARCHAR(10),
    reviews_per_score_3 VARCHAR(10),
    reviews_per_score_4 VARCHAR(10),
    reviews_per_score_5 VARCHAR(10),
    photo VARCHAR(500),
    street_view VARCHAR(500),
    logo VARCHAR(500),
    business_status VARCHAR(50),
    working_hours TEXT,
    price_range VARCHAR(100),
    prices TEXT,
    booking_appointment_link VARCHAR(500),
    menu_link VARCHAR(500),
    about TEXT,
    description TEXT,
    verified VARCHAR(10),
    location_link TEXT,
    location_reviews_link TEXT,
    place_id VARCHAR(100),
    cid VARCHAR(50),
    reviews_id VARCHAR(50),
    slug VARCHAR(255),
    comunidad_autonoma VARCHAR(100),
    email VARCHAR(255),
    destacado VARCHAR(10),
    activo VARCHAR(10),
    prestaciones TEXT,
    descripcion TEXT,
    servicios TEXT,
    facilidades TEXT,
    accesibilidad TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────
-- PASO 4: CARGAR CSV EN TABLA TEMPORAL
-- ───────────────────────────────────────────────────────────
-- NOTA: Ajustar la ruta según tu instalación de XAMPP
-- Windows: C:/xampp/htdocs/crematoriosdemascotas/sql/20260125-bbdd.csv
-- Asegúrate de que el archivo tenga permisos de lectura

LOAD DATA INFILE 'C:/xampp/htdocs/crematoriosdemascotas/sql/20260125-bbdd.csv'
INTO TABLE tmp_crematorios_csv
CHARACTER SET utf8mb4
FIELDS TERMINATED BY ','
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 ROWS
(name, subtypes, phone, website, address, street, city, county, state,
 postal_code, latitude, longitude, rating, reviews, reviews_link,
 reviews_per_score, reviews_per_score_1, reviews_per_score_2,
 reviews_per_score_3, reviews_per_score_4, reviews_per_score_5,
 photo, street_view, logo, business_status, working_hours, price_range,
 prices, booking_appointment_link, menu_link, about, description, verified,
 location_link, location_reviews_link, place_id, cid, reviews_id, slug,
 comunidad_autonoma, email, destacado, activo, prestaciones, descripcion,
 servicios, facilidades, accesibilidad);

-- ───────────────────────────────────────────────────────────
-- PASO 5: INSERTAR CREMATORIOS DESDE TABLA TEMPORAL
-- ───────────────────────────────────────────────────────────
INSERT INTO crematorios (
    provincia_id,
    nombre,
    slug,
    subtypes,
    telefono,
    email,
    website,
    direccion_completa,
    calle,
    ciudad,
    distrito,
    codigo_postal,
    latitud,
    longitud,
    rating,
    reviews_total,
    reviews_link,
    reviews_1,
    reviews_2,
    reviews_3,
    reviews_4,
    reviews_5,
    foto_principal,
    street_view,
    logo,
    business_status,
    verificado,
    destacado,
    activo,
    horarios,
    booking_link,
    menu_link,
    location_link,
    location_reviews_link,
    about,
    rango_precios,
    precios,
    descripcion_google,
    descripcion,
    prestaciones,
    servicios,
    facilidades,
    accesibilidad,
    place_id,
    cid,
    reviews_id
)
SELECT
    p.id AS provincia_id,
    t.name,
    t.slug,
    NULLIF(t.subtypes, ''),
    NULLIF(t.phone, ''),
    NULLIF(t.email, ''),
    NULLIF(t.website, ''),
    NULLIF(t.address, ''),
    NULLIF(t.street, ''),
    NULLIF(t.city, ''),
    NULLIF(t.county, ''),
    NULLIF(t.postal_code, ''),
    NULLIF(CAST(t.latitude AS DECIMAL(10,7)), 0),
    NULLIF(CAST(t.longitude AS DECIMAL(10,7)), 0),
    CASE WHEN t.rating = '' THEN NULL ELSE CAST(t.rating AS DECIMAL(2,1)) END,
    CASE WHEN t.reviews = '' THEN 0 ELSE CAST(t.reviews AS UNSIGNED) END,
    NULLIF(t.reviews_link, ''),
    CASE WHEN t.reviews_per_score_1 = '' THEN 0 ELSE CAST(t.reviews_per_score_1 AS UNSIGNED) END,
    CASE WHEN t.reviews_per_score_2 = '' THEN 0 ELSE CAST(t.reviews_per_score_2 AS UNSIGNED) END,
    CASE WHEN t.reviews_per_score_3 = '' THEN 0 ELSE CAST(t.reviews_per_score_3 AS UNSIGNED) END,
    CASE WHEN t.reviews_per_score_4 = '' THEN 0 ELSE CAST(t.reviews_per_score_4 AS UNSIGNED) END,
    CASE WHEN t.reviews_per_score_5 = '' THEN 0 ELSE CAST(t.reviews_per_score_5 AS UNSIGNED) END,
    NULLIF(t.photo, ''),
    NULLIF(t.street_view, ''),
    NULLIF(t.logo, ''),
    COALESCE(NULLIF(t.business_status, ''), 'OPERATIONAL'),
    CASE WHEN t.verified = '1' THEN 1 ELSE 0 END,
    CASE WHEN t.destacado = '1' THEN 1 ELSE 0 END,
    CASE WHEN t.activo = '1' THEN 1 ELSE 0 END,
    CASE WHEN t.working_hours = '' THEN NULL ELSE t.working_hours END,
    NULLIF(t.booking_appointment_link, ''),
    NULLIF(t.menu_link, ''),
    NULLIF(t.location_link, ''),
    NULLIF(t.location_reviews_link, ''),
    CASE WHEN t.about = '' THEN NULL ELSE t.about END,
    NULLIF(t.price_range, ''),
    NULLIF(t.prices, ''),
    NULLIF(t.description, ''),
    NULLIF(t.descripcion, ''),
    NULLIF(t.prestaciones, ''),
    NULLIF(t.servicios, ''),
    NULLIF(t.facilidades, ''),
    NULLIF(t.accesibilidad, ''),
    t.place_id,
    NULLIF(t.cid, ''),
    NULLIF(t.reviews_id, '')
FROM tmp_crematorios_csv t
INNER JOIN provincias p ON p.nombre = t.state;

-- ───────────────────────────────────────────────────────────
-- PASO 6: LIMPIAR TABLA TEMPORAL
-- ───────────────────────────────────────────────────────────
DROP TABLE IF EXISTS tmp_crematorios_csv;

-- ───────────────────────────────────────────────────────────
-- VERIFICACIÓN DE DATOS IMPORTADOS
-- ───────────────────────────────────────────────────────────
SELECT 'RESUMEN DE IMPORTACIÓN' AS info;

SELECT 'Comunidades Autónomas' AS tabla, COUNT(*) AS total FROM comunidades_autonomas
UNION ALL
SELECT 'Provincias', COUNT(*) FROM provincias
UNION ALL
SELECT 'Crematorios', COUNT(*) FROM crematorios;

SELECT 'Crematorios por Comunidad Autónoma:' AS info;
SELECT * FROM v_estadisticas_comunidad ORDER BY total_crematorios DESC;

-- ───────────────────────────────────────────────────────────
-- RESTAURAR CONFIGURACIÓN
-- ───────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════
-- FIN DEL SCRIPT DE IMPORTACIÓN
-- ═══════════════════════════════════════════════════════════
