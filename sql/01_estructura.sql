-- ═══════════════════════════════════════════════════════════
-- CREMATORIOS DE MASCOTAS - ESTRUCTURA DE BASE DE DATOS
-- ═══════════════════════════════════════════════════════════
-- Autor: Facundo M. Campos
-- Empresa: Lycapolis LLC
-- Fecha: 2026-01-25
-- ═══════════════════════════════════════════════════════════

-- Configuración inicial
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ───────────────────────────────────────────────────────────
-- CREAR BASE DE DATOS (si no existe)
-- ───────────────────────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS crematorios_mascotas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE crematorios_mascotas;

-- ───────────────────────────────────────────────────────────
-- TABLA: comunidades_autonomas
-- ───────────────────────────────────────────────────────────
DROP TABLE IF EXISTS comunidades_autonomas;
CREATE TABLE comunidades_autonomas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_nombre (nombre),
    UNIQUE KEY uk_slug (slug),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────
-- TABLA: provincias
-- ───────────────────────────────────────────────────────────
DROP TABLE IF EXISTS provincias;
CREATE TABLE provincias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comunidad_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_nombre_comunidad (nombre, comunidad_id),
    UNIQUE KEY uk_slug (slug),
    INDEX idx_comunidad (comunidad_id),
    INDEX idx_activo (activo),

    CONSTRAINT fk_provincia_comunidad
        FOREIGN KEY (comunidad_id) REFERENCES comunidades_autonomas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────
-- TABLA: crematorios
-- ───────────────────────────────────────────────────────────
DROP TABLE IF EXISTS crematorios;
CREATE TABLE crematorios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Relaciones
    provincia_id INT UNSIGNED NOT NULL,

    -- Datos básicos
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    subtypes VARCHAR(500) DEFAULT NULL,
    telefono VARCHAR(50) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    website VARCHAR(500) DEFAULT NULL,

    -- Dirección
    direccion_completa VARCHAR(500) DEFAULT NULL,
    calle VARCHAR(255) DEFAULT NULL,
    ciudad VARCHAR(100) DEFAULT NULL,
    distrito VARCHAR(100) DEFAULT NULL,
    codigo_postal VARCHAR(10) DEFAULT NULL,

    -- Geolocalización
    latitud DECIMAL(10, 7) DEFAULT NULL,
    longitud DECIMAL(10, 7) DEFAULT NULL,

    -- Calificaciones
    rating DECIMAL(2, 1) DEFAULT NULL,
    reviews_total INT UNSIGNED DEFAULT 0,
    reviews_link VARCHAR(1000) DEFAULT NULL,
    reviews_1 INT UNSIGNED DEFAULT 0,
    reviews_2 INT UNSIGNED DEFAULT 0,
    reviews_3 INT UNSIGNED DEFAULT 0,
    reviews_4 INT UNSIGNED DEFAULT 0,
    reviews_5 INT UNSIGNED DEFAULT 0,

    -- Imágenes
    foto_principal VARCHAR(500) DEFAULT NULL,
    street_view VARCHAR(500) DEFAULT NULL,
    logo VARCHAR(500) DEFAULT NULL,

    -- Estado del negocio
    business_status VARCHAR(50) DEFAULT 'OPERATIONAL',
    verificado TINYINT(1) DEFAULT 0,
    destacado TINYINT(1) DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,

    -- Horarios (JSON)
    horarios JSON DEFAULT NULL,

    -- Enlaces adicionales
    booking_link VARCHAR(500) DEFAULT NULL,
    menu_link VARCHAR(500) DEFAULT NULL,
    location_link TEXT DEFAULT NULL,
    location_reviews_link TEXT DEFAULT NULL,

    -- Información adicional (JSON para datos complejos)
    about JSON DEFAULT NULL,
    rango_precios VARCHAR(100) DEFAULT NULL,
    precios TEXT DEFAULT NULL,

    -- Descripciones de texto
    descripcion_google TEXT DEFAULT NULL,
    descripcion TEXT DEFAULT NULL,
    prestaciones TEXT DEFAULT NULL,
    servicios TEXT DEFAULT NULL,
    facilidades TEXT DEFAULT NULL,
    accesibilidad TEXT DEFAULT NULL,

    -- IDs de Google
    place_id VARCHAR(100) DEFAULT NULL,
    cid VARCHAR(50) DEFAULT NULL,
    reviews_id VARCHAR(50) DEFAULT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Índices
    UNIQUE KEY uk_slug (slug),
    UNIQUE KEY uk_place_id (place_id),
    INDEX idx_provincia (provincia_id),
    INDEX idx_ciudad (ciudad),
    INDEX idx_rating (rating),
    INDEX idx_destacado (destacado),
    INDEX idx_activo (activo),
    INDEX idx_verificado (verificado),
    INDEX idx_coords (latitud, longitud),

    -- Foreign Keys
    CONSTRAINT fk_crematorio_provincia
        FOREIGN KEY (provincia_id) REFERENCES provincias(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────────────────────────────────────
-- VISTAS ÚTILES
-- ───────────────────────────────────────────────────────────

-- Vista: Crematorios con información completa de ubicación
CREATE OR REPLACE VIEW v_crematorios_completo AS
SELECT
    c.id,
    c.nombre,
    c.slug,
    c.subtypes,
    c.telefono,
    c.email,
    c.website,
    c.direccion_completa,
    c.calle,
    c.ciudad,
    c.distrito,
    c.codigo_postal,
    c.latitud,
    c.longitud,
    c.rating,
    c.reviews_total,
    c.reviews_1,
    c.reviews_2,
    c.reviews_3,
    c.reviews_4,
    c.reviews_5,
    c.foto_principal,
    c.street_view,
    c.logo,
    c.business_status,
    c.verificado,
    c.destacado,
    c.activo,
    c.horarios,
    c.descripcion,
    c.prestaciones,
    c.servicios,
    c.facilidades,
    c.accesibilidad,
    p.nombre AS provincia,
    p.slug AS provincia_slug,
    ca.nombre AS comunidad_autonoma,
    ca.slug AS comunidad_slug
FROM crematorios c
INNER JOIN provincias p ON c.provincia_id = p.id
INNER JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
WHERE c.activo = 1;

-- Vista: Estadísticas por provincia
CREATE OR REPLACE VIEW v_estadisticas_provincia AS
SELECT
    p.id AS provincia_id,
    p.nombre AS provincia,
    p.slug AS provincia_slug,
    ca.nombre AS comunidad_autonoma,
    ca.slug AS comunidad_slug,
    COUNT(c.id) AS total_crematorios,
    AVG(c.rating) AS rating_promedio,
    SUM(c.reviews_total) AS total_reviews
FROM provincias p
INNER JOIN comunidades_autonomas ca ON p.comunidad_id = ca.id
LEFT JOIN crematorios c ON c.provincia_id = p.id AND c.activo = 1
GROUP BY p.id, p.nombre, p.slug, ca.nombre, ca.slug;

-- Vista: Estadísticas por comunidad autónoma
CREATE OR REPLACE VIEW v_estadisticas_comunidad AS
SELECT
    ca.id AS comunidad_id,
    ca.nombre AS comunidad_autonoma,
    ca.slug AS comunidad_slug,
    COUNT(DISTINCT p.id) AS total_provincias,
    COUNT(c.id) AS total_crematorios,
    AVG(c.rating) AS rating_promedio,
    SUM(c.reviews_total) AS total_reviews
FROM comunidades_autonomas ca
LEFT JOIN provincias p ON p.comunidad_id = ca.id
LEFT JOIN crematorios c ON c.provincia_id = p.id AND c.activo = 1
GROUP BY ca.id, ca.nombre, ca.slug;

-- ───────────────────────────────────────────────────────────
-- RESTAURAR CONFIGURACIÓN
-- ───────────────────────────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════
-- FIN DEL SCRIPT DE ESTRUCTURA
-- ═══════════════════════════════════════════════════════════
