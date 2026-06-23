<?php
/**
 * Geo de referencia — ESPAÑA (liviano, sin BD).
 * Comunidad Autónoma / Ciudad Autónoma → sus provincias.
 *
 * Uso: alimenta los dropdowns dependientes del formulario público
 * (registrar-negocio.php) para evitar texto basura en CCAA/provincia.
 * La CIUDAD/municipio se carga a mano (texto libre) — se corrige en la
 * verificación humana. NO toca las tablas de navegación/URLs del sitio.
 *
 * Jerarquía España (estricta): CCAA › provincia › municipio. Una provincia
 * pertenece a UNA sola CCAA. Ceuta y Melilla son ciudades autónomas.
 *
 * `return` de un array asociativo  CCAA => [provincias...]
 */

return [
    'Andalucía'                  => ['Almería', 'Cádiz', 'Córdoba', 'Granada', 'Huelva', 'Jaén', 'Málaga', 'Sevilla'],
    'Aragón'                     => ['Huesca', 'Teruel', 'Zaragoza'],
    'Principado de Asturias'     => ['Asturias'],
    'Illes Balears'              => ['Illes Balears'],
    'Canarias'                   => ['Las Palmas', 'Santa Cruz de Tenerife'],
    'Cantabria'                  => ['Cantabria'],
    'Castilla y León'            => ['Ávila', 'Burgos', 'León', 'Palencia', 'Salamanca', 'Segovia', 'Soria', 'Valladolid', 'Zamora'],
    'Castilla-La Mancha'         => ['Albacete', 'Ciudad Real', 'Cuenca', 'Guadalajara', 'Toledo'],
    'Cataluña'                   => ['Barcelona', 'Girona', 'Lleida', 'Tarragona'],
    'Comunitat Valenciana'       => ['Alicante', 'Castellón', 'Valencia'],
    'Extremadura'                => ['Badajoz', 'Cáceres'],
    'Galicia'                    => ['A Coruña', 'Lugo', 'Ourense', 'Pontevedra'],
    'Comunidad de Madrid'        => ['Madrid'],
    'Región de Murcia'           => ['Murcia'],
    'Comunidad Foral de Navarra' => ['Navarra'],
    'País Vasco'                 => ['Álava', 'Gipuzkoa', 'Bizkaia'],
    'La Rioja'                   => ['La Rioja'],
    'Ceuta'                      => ['Ceuta'],
    'Melilla'                    => ['Melilla'],
];
