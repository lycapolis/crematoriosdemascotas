<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CABECERA COMÚN DE LOS EMAILS — logo oficial
 * ═══════════════════════════════════════════════════════════
 *
 * Imagen PNG del logo (patita + «Crematorios de Mascotas», blanco y terracota sobre
 * transparente) pensada para fondos oscuros: la cabecera de los emails es marrón #5A3E2F.
 * Se usa PNG porque Gmail/Outlook no muestran SVG. La URL es absoluta (el email se abre
 * fuera del sitio), así que en local solo se ve desde el propio XAMPP.
 *
 *   emailCabeceraLogo(): string   → <img> listo para meter dentro del <td> de la cabecera
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../notificaciones/url.php';

if (!function_exists('emailCabeceraLogo')) {

    function emailCabeceraLogo(): string
    {
        $sitio = defined('SITIO_NOMBRE') ? SITIO_NOMBRE : 'Crematorios de Mascotas';
        $src   = notifUrlPublica('assets/img/marca/logo-marca-agua-fondo-oscuro.png');

        // 900×200 original → 270×60. El texto alternativo lleva color por si el cliente bloquea imágenes.
        return '<img src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" width="270" height="60" alt="' . htmlspecialchars($sitio, ENT_QUOTES, 'UTF-8')
             . '" style="display:block;border:0;outline:none;width:270px;height:60px;font-family:Arial,sans-serif;font-size:18px;font-weight:700;color:#FFFBF7;">';
    }
}
