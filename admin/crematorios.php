<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ALIAS LEGACY — crematorios.php
 * ═══════════════════════════════════════════════════════════
 *
 * Este archivo se renombró a fichas-negocios.php el 2026-05-13
 * (preparación para soporte de otros rubros — fichas-personas,
 * fichas-eventos, etc. en el futuro).
 *
 * Este stub redirige con 301 al nuevo nombre preservando query string.
 * Se mantiene temporalmente para cubrir bookmarks/tabs abiertas.
 * En el deploy a Hostinger se puede eliminar definitivamente.
 *
 * NOTA: no requiere auth.php — solo redirige. La autenticación
 * vive en el destino (fichas-negocios.php).
 */

require_once dirname(__DIR__) . '/includes/config.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$url   = BASE_URL . '/admin/fichas-negocios.php' . ($query !== '' ? '?' . $query : '');

header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $url);
exit;
