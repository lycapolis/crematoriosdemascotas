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
            $tmpDescarga = tempnam(sys_get_temp_dir(), 'blg');
            file_put_contents($tmpDescarga, $bin);
            $origen = 'pegada';
        } else {
            if (!preg_match('#^https?://#i', $url)) throw new Exception('URL de imagen no válida.');
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $ip = gethostbyname($host);
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception('No se pueden descargar imágenes de esa dirección.');
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; BlogImageFetcher/1.0)',
            ]);
            $bin = curl_exec($ch);
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($bin === false || $codigo >= 400) throw new Exception('No se pudo descargar la imagen desde la URL.');
            if (strlen($bin) > ImagenHelper::MAX_SIZE_MB * 1024 * 1024 * 2) throw new Exception('La imagen es demasiado grande.');
            $tmpDescarga = tempnam(sys_get_temp_dir(), 'blg');
            file_put_contents($tmpDescarga, $bin);
            $origen = 'url';
        }
        $tmp = $tmpDescarga;
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!in_array($finfo->file($tmp), ImagenHelper::TIPOS_PERMITIDOS, true) || !@getimagesize($tmp)) {
            throw new Exception('El archivo descargado no es una imagen válida (JPG, PNG, GIF o WebP).');
        }
    } else {
        throw new Exception('No se recibió ninguna imagen.');
    }

    $res = blogProcesarImagen($tmp, $nombre, $maxAncho);

    obtenerConexion()->prepare("INSERT INTO blog_imagenes (articulo_id, ruta, ruta_media, ancho, alto, origen) VALUES (:a, :r, :m, :w, :h, :o)")
        ->execute([':a' => $articuloId, ':r' => $res['ruta'], ':m' => $res['media'] ?: null, ':w' => $res['ancho'], ':h' => $res['alto'], ':o' => $origen]);

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
