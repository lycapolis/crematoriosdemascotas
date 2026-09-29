<?php
/**
 * Subida de imágenes del blog (AJAX).
 *
 * Formato de respuesta compatible con @editorjs/image:
 *   { success: 1, file: { url, ruta, media, ancho, alto } }
 *
 * Dos modos:
 *   - multipart con campo "image" (arrastrar / elegir archivo)
 *   - JSON { "url": "https://…" } → se descarga y se guarda localmente
 *     (lo usa Editor.js al pegar texto con imágenes desde Google Docs/Word)
 *
 * Parámetro opcional "nombre" (o ?nombre=) para el nombre SEO del archivo.
 */

require_once __DIR__ . '/_blog-comun.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') blogJson(['success' => 0, 'mensaje' => 'Método no permitido'], 405);

$esJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
$body   = $esJson ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_POST;

$token = $body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validarTokenCSRF((string) $token)) blogJson(['success' => 0, 'mensaje' => 'La sesión expiró. Recargá la página.'], 403);

$nombre     = trim($body['nombre'] ?? ($_GET['nombre'] ?? '')) ?: 'imagen-blog';
$articuloId = (int) ($body['articulo_id'] ?? ($_GET['articulo_id'] ?? 0)) ?: null;
$maxAncho   = (($body['uso'] ?? $_GET['uso'] ?? '') === 'autor') ? 400 : 1600;

$tmpDescarga = null;
try {
    if (!empty($_FILES['image']['tmp_name'])) {
        $val = ImagenHelper::validar($_FILES['image']);
        if (!$val['ok']) throw new Exception($val['error']);
        $tmp = $_FILES['image']['tmp_name'];
        if ($nombre === 'imagen-blog') $nombre = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
        $origen = 'subida';
    } elseif (!empty($body['url'])) {
        $url = trim($body['url']);
        if (str_starts_with($url, 'data:image/')) {
            // Imagen pegada como base64 (algunos navegadores / Word)
            if (!preg_match('#^data:image/(png|jpe?g|gif|webp);base64,(.+)$#', $url, $m)) throw new Exception('Formato de imagen no soportado.');
            $bin = base64_decode($m[2], true);
            if ($bin === false || strlen($bin) > ImagenHelper::MAX_SIZE_MB * 1024 * 1024) throw new Exception('La imagen pegada no es válida o es demasiado grande.');
            $tmpDescarga = blogGuardarTemp($bin);
            $origen = 'pegada';
        } else {
            $tmpDescarga = blogDescargarATemp($url);
            $origen = 'url';
        }
        $tmp = $tmpDescarga;
    } else {
        throw new Exception('No se recibió ninguna imagen.');
    }

    $res = blogProcesarImagen($tmp, $nombre, $maxAncho);
    blogRegistrarImagen($articuloId, $res, $origen);

    blogJson(['success' => 1, 'file' => [
        'url'   => blogUrlArchivo($res['ruta']),
        'ruta'  => $res['ruta'],
        'media' => $res['media'],
        'ancho' => $res['ancho'],
        'alto'  => $res['alto'],
    ]]);
} catch (Throwable $e) {
    blogJson(['success' => 0, 'mensaje' => $e->getMessage()], 200);
} finally {
    if ($tmpDescarga && is_file($tmpDescarga)) @unlink($tmpDescarga);
}
