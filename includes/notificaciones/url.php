<?php
/**
 * URL absoluta del sitio para enlaces e imágenes dentro de emails.
 * En local usa el host de la petición; desde cron (sin $_SERVER) cae al dominio público.
 */

if (!function_exists('notifUrlPublica')) {

    function notifUrlPublica(string $path = ''): string
    {
        $base = defined('BASE_URL') ? (string)BASE_URL : '';
        if (!preg_match('#^https?://#i', $base)) {
            if (!empty($_SERVER['HTTP_HOST'])) {
                $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $base = $esquema . '://' . $_SERVER['HTTP_HOST'] . $base;
            } else {
                $base = 'https://crematoriosdemascotas.com';
            }
        }
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}
