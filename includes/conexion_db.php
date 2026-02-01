<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CONEXIÓN A BASE DE DATOS (PDO)
 * ═══════════════════════════════════════════════════════════
 *
 * Proyecto: Crematorios de Mascotas
 * Autor: Lycapolis LLC
 */

/**
 * Obtiene la conexión PDO a la base de datos
 * Usa patrón Singleton para reutilizar la conexión
 *
 * @return PDO|null Conexión PDO o null si falla
 */
function obtenerConexion() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);

        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die('Error de conexión: ' . $e->getMessage());
            }
            return null;
        }
    }

    return $pdo;
}
