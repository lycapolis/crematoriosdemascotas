<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ALIAS LEGACY — editar-crematorio.php
 * ═══════════════════════════════════════════════════════════
 *
 * Este archivo se renombró a editar-ficha-negocio.php el 2026-05-13
 * (preparación para soporte de otros rubros en el futuro).
 *
 * Este stub redirige con 301 (Moved Permanently) al nuevo nombre
 * preservando query string. Se mantiene temporalmente por:
 *   - Bookmarks viejos del admin.
 *   - Tabs / pestañas abiertas con la URL anterior.
 *   - Cache de navegadores.
 *
 * Cuando se confirme que nadie está accediendo más a este archivo
 * (revisar logs durante 30+ días), se puede eliminar definitivamente.
 *
 * NOTA: este archivo NO requiere auth.php — solo redirige. La
 * autenticación se aplica en el destino (editar-ficha-negocio.php).
 */

require_once dirname(__DIR__) . '/includes/config.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$url   = BASE_URL . '/admin/editar-ficha-negocio.php' . ($query !== '' ? '?' . $query : '');

header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $url);
exit;
