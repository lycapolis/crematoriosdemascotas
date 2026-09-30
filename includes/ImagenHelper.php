<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * IMAGEN HELPER - CREMATORIOS DE MASCOTAS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Clase para procesar, optimizar y gestionar imágenes subidas.
 * Convierte a WebP, redimensiona y genera nombres SEO-friendly.
 *
 * Autor: Facundo M. Campos
 * Empresa: Lycapolis LLC
 * Fecha: Febrero 2026
 * ═══════════════════════════════════════════════════════════════════════════
 */

class ImagenHelper
{
    // Categorías válidas para el análisis LLM
    const CATEGORIAS_VALIDAS = [
        'logo', 'exterior', 'interior_sala', 'interior_recepcion',
        'interior_amenities', 'produccion_tecnologia', 'recuerdos_souvenires',
        'equipo_personas', 'fotos_clientes', 'otro'
    ];
    // Configuración
    const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const MAX_SIZE_MB = 5;
    const CALIDAD_WEBP = 80;
    const MAX_LOGO = 300;      // px ancho máximo para logos
    const MAX_GALERIA = 1200;  // px ancho máximo para galería

    // Directorios solicitudes (siguen igual)
    const DIR_SOLICITUDES_LOGOS   = 'uploads/solicitudes/logos/';
    const DIR_SOLICITUDES_GALERIA = 'uploads/solicitudes/galeria/';

    /**
     * Devuelve la ruta relativa de la carpeta de un crematorio (forward slashes, sin barra final).
     * Formato: uploads/img-fichas/0089/
     */
    public static function dirCrematorio(int $id): string {
        return 'uploads/img-fichas/' . str_pad($id, 4, '0', STR_PAD_LEFT) . '/';
    }

    public static function dirClientesCrematorio(int $id): string {
        return self::dirCrematorio($id) . 'img-clientes/';
    }

    /**
     * Valida un archivo de imagen
     *
     * @param array $archivo $_FILES['campo']
     * @return array ['ok' => bool, 'error' => string|null]
     */
    public static function validar($archivo)
    {
        // Verificar que existe
        if (!isset($archivo['tmp_name']) || empty($archivo['tmp_name'])) {
            return ['ok' => false, 'error' => 'No se recibió archivo'];
        }

        // Verificar errores de subida
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo permitido por el servidor',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo del formulario',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE    => 'No se subió ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir archivo',
                UPLOAD_ERR_EXTENSION  => 'Extensión no permitida',
            ];
            return ['ok' => false, 'error' => $errores[$archivo['error']] ?? 'Error desconocido'];
        }

        // Verificar tamaño
        $maxBytes = self::MAX_SIZE_MB * 1024 * 1024;
        if ($archivo['size'] > $maxBytes) {
            return ['ok' => false, 'error' => 'El archivo excede ' . self::MAX_SIZE_MB . 'MB'];
        }

        // Verificar tipo MIME real (no confiar en el navegador)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->file($archivo['tmp_name']);

        if (!in_array($mimeReal, self::TIPOS_PERMITIDOS)) {
            return ['ok' => false, 'error' => 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP'];
        }

        // Verificar que es una imagen válida
        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'El archivo no es una imagen válida'];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Procesa una imagen: redimensiona y convierte a WebP
     *
     * @param array $archivo $_FILES['campo']
     * @param string $tipo 'logo' o 'galeria'
     * @param string $slug Slug para nombre de archivo
     * @param string $destino 'solicitudes' o 'crematorios'
     * @param int|null $indice Índice para galería (1, 2, 3...)
     * @return array ['ok' => bool, 'ruta' => string|null, 'nombre' => string|null, 'error' => string|null]
     */
    public static function procesar($archivo, $tipo, $slug, $destino = 'solicitudes', $indice = null, $crematorioId = null)
    {
        error_log("ImagenHelper::procesar - tipo=$tipo, slug=$slug, destino=$destino, crematorioId=$crematorioId");

        // Validar primero
        $validacion = self::validar($archivo);
        if (!$validacion['ok']) {
            error_log("ImagenHelper::procesar - Validación falló: " . $validacion['error']);
            return ['ok' => false, 'ruta' => null, 'nombre' => null, 'error' => $validacion['error']];
        }
        error_log("ImagenHelper::procesar - Validación OK");

        // Determinar directorio
        if ($destino === 'crematorios' && $crematorioId) {
            // tipo='cliente' (imágenes de reseñas) van a subcarpeta dedicada img-clientes/
            $dir      = ($tipo === 'cliente')
                ? self::dirClientesCrematorio((int) $crematorioId)
                : self::dirCrematorio((int) $crematorioId);
            $maxAncho = ($tipo === 'logo') ? self::MAX_LOGO : self::MAX_GALERIA;
        } elseif ($tipo === 'logo') {
            $dir      = self::DIR_SOLICITUDES_LOGOS;
            $maxAncho = self::MAX_LOGO;
        } else {
            $dir      = self::DIR_SOLICITUDES_GALERIA;
            $maxAncho = self::MAX_GALERIA;
        }
        error_log("ImagenHelper::procesar - dir=$dir, maxAncho=$maxAncho");

        // Asegurar que existe el directorio (compatible Windows/Linux)
        $baseDir = dirname(__DIR__);
        $rutaCompleta = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        error_log("ImagenHelper::procesar - rutaCompleta=$rutaCompleta");
        error_log("ImagenHelper::procesar - is_dir=" . (is_dir($rutaCompleta) ? 'SI' : 'NO'));

        if (!is_dir($rutaCompleta)) {
            error_log("ImagenHelper::procesar - Intentando crear directorio...");
            if (!@mkdir($rutaCompleta, 0755, true)) {
                $error = error_get_last();
                error_log("ImagenHelper::procesar - ERROR creando dir: " . ($error['message'] ?? 'desconocido'));
                return ['ok' => false, 'ruta' => null, 'nombre' => null, 'error' => 'No se pudo crear el directorio de destino'];
            }
            error_log("ImagenHelper::procesar - Directorio creado OK");
        }

        // Generar nombre SEO
        $nombreArchivo = self::generarNombreSEO($slug, $tipo, $indice);

        // Ruta destino (con separador de Windows/Linux)
        $rutaDestino = $rutaCompleta . $nombreArchivo;

        // Procesar imagen
        try {
            error_log("ImagenHelper::procesar - Llamando convertirWebP: origen={$archivo['tmp_name']}, destino=$rutaDestino");
            $resultado = self::convertirWebP($archivo['tmp_name'], $rutaDestino, $maxAncho);
            error_log("ImagenHelper::procesar - convertirWebP retornó: " . ($resultado ? 'true' : 'false'));

            if ($resultado) {
                // Verificar que el archivo se creó
                $existe = file_exists($rutaDestino);
                error_log("ImagenHelper::procesar - Archivo existe: " . ($existe ? 'SI' : 'NO'));
                if (!$existe) {
                    return ['ok' => false, 'ruta' => null, 'nombre' => null, 'error' => 'El archivo no se guardó correctamente'];
                }

                $rutaFinal = $dir . $nombreArchivo;
                error_log("ImagenHelper::procesar - Éxito! ruta=$rutaFinal");
                return [
                    'ok' => true,
                    'ruta' => $rutaFinal,  // Guardar con slash forward para la BD
                    'nombre' => $nombreArchivo,
                    'nombre_original' => $archivo['name'],
                    'tamano' => filesize($rutaDestino),
                    'error' => null
                ];
            } else {
                error_log("ImagenHelper::procesar - convertirWebP retornó false");
                return ['ok' => false, 'ruta' => null, 'nombre' => null, 'error' => 'Error al procesar la imagen'];
            }
        } catch (Exception $e) {
            error_log("ImagenHelper::procesar - EXCEPCIÓN: " . $e->getMessage());
            return ['ok' => false, 'ruta' => null, 'nombre' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convierte una imagen a WebP con redimensionado
     *
     * @param string $origen Ruta del archivo original
     * @param string $destino Ruta de destino (sin extensión, se añade .webp)
     * @param int $maxAncho Ancho máximo en px
     * @return bool
     */
    public static function convertirWebP($origen, $destino, $maxAncho)
    {
        error_log("convertirWebP - origen=$origen, destino=$destino, maxAncho=$maxAncho");

        // Verificar que GD soporta WebP
        if (!function_exists('imagewebp')) {
            error_log("convertirWebP - ERROR: imagewebp no existe");
            throw new Exception('El servidor no soporta conversión a WebP');
        }
        error_log("convertirWebP - imagewebp existe OK");

        // Verificar que el archivo origen existe
        if (!file_exists($origen)) {
            error_log("convertirWebP - ERROR: archivo origen no existe: $origen");
            throw new Exception('El archivo origen no existe');
        }
        error_log("convertirWebP - archivo origen existe, tamaño=" . filesize($origen));

        // Obtener información de la imagen
        $info = @getimagesize($origen);
        if ($info === false) {
            error_log("convertirWebP - ERROR: getimagesize falló");
            throw new Exception('No se pudo leer la imagen');
        }
        error_log("convertirWebP - getimagesize OK: " . json_encode($info));

        list($anchoOrig, $altoOrig, $tipoImg) = $info;

        // Crear recurso de imagen según tipo (con supresión de errores)
        $imagen = false;
        switch ($tipoImg) {
            case IMAGETYPE_JPEG:
                $imagen = @imagecreatefromjpeg($origen);
                break;
            case IMAGETYPE_PNG:
                $imagen = @imagecreatefrompng($origen);
                break;
            case IMAGETYPE_GIF:
                $imagen = @imagecreatefromgif($origen);
                break;
            case IMAGETYPE_WEBP:
                $imagen = @imagecreatefromwebp($origen);
                break;
            default:
                throw new Exception('Tipo de imagen no soportado: ' . $tipoImg);
        }

        if (!$imagen) {
            error_log("convertirWebP - ERROR: no se pudo crear recurso de imagen");
            throw new Exception('Error al crear recurso de imagen. Verifique que el archivo no esté corrupto.');
        }
        error_log("convertirWebP - Recurso de imagen creado OK");

        // Calcular nuevas dimensiones
        if ($anchoOrig > $maxAncho) {
            $nuevoAncho = $maxAncho;
            $nuevoAlto = intval($altoOrig * ($maxAncho / $anchoOrig));
        } else {
            $nuevoAncho = $anchoOrig;
            $nuevoAlto = $altoOrig;
        }
        error_log("convertirWebP - Dimensiones: {$nuevoAncho}x{$nuevoAlto}");

        // Crear imagen redimensionada
        $imagenNueva = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        // Preservar transparencia para PNG/GIF
        if ($tipoImg === IMAGETYPE_PNG || $tipoImg === IMAGETYPE_GIF) {
            imagecolortransparent($imagenNueva, imagecolorallocatealpha($imagenNueva, 0, 0, 0, 127));
            imagealphablending($imagenNueva, false);
            imagesavealpha($imagenNueva, true);
        }

        // Redimensionar
        imagecopyresampled(
            $imagenNueva, $imagen,
            0, 0, 0, 0,
            $nuevoAncho, $nuevoAlto, $anchoOrig, $altoOrig
        );
        error_log("convertirWebP - Imagen redimensionada OK");

        // Verificar que el directorio de destino existe
        $dirDestino = dirname($destino);
        if (!is_dir($dirDestino)) {
            error_log("convertirWebP - Creando directorio: $dirDestino");
            @mkdir($dirDestino, 0755, true);
        }

        // Guardar como WebP
        error_log("convertirWebP - Intentando guardar en: $destino");
        $resultado = @imagewebp($imagenNueva, $destino, self::CALIDAD_WEBP);
        error_log("convertirWebP - imagewebp retornó: " . ($resultado ? 'true' : 'false'));

        if (!$resultado) {
            $error = error_get_last();
            error_log("convertirWebP - ERROR en imagewebp: " . ($error['message'] ?? 'desconocido'));
        }

        // Verificar que se creó el archivo
        if ($resultado && file_exists($destino)) {
            error_log("convertirWebP - Archivo creado OK, tamaño: " . filesize($destino));
        } else {
            error_log("convertirWebP - ADVERTENCIA: archivo no existe después de guardar");
        }

        // Liberar memoria
        imagedestroy($imagen);
        imagedestroy($imagenNueva);

        return $resultado;
    }

    /**
     * Estampa un logo (PNG con transparencia) en la esquina inferior derecha.
     * Se aplica sobre la imagen de origen ANTES de convertirWebP(), así todas
     * las versiones redimensionadas heredan el logo en la misma proporción.
     * El resultado se guarda como PNG (sin pérdida) en $destino.
     *
     * Sin placa: se mide la luminosidad del fondo justo donde irá el logo. Si es
     * oscuro se usa $logoOscuro (versión de marca con texto claro); sin ese archivo,
     * los trazos oscuros del logo se recolorean a crema. Sin sombras ni placas.
     *
     * @param float       $anchoRel   Ancho del logo relativo al de la imagen (0.14 = 14 %)
     * @param int         $opacidad   0-100
     * @param string|null $logoOscuro PNG para fondos oscuros (mismas proporciones)
     */
    public static function aplicarLogo(string $origen, string $destino, string $logoPng, float $anchoRel = 0.14, int $opacidad = 90, ?string $logoOscuro = null): bool
    {
        if (!is_file($logoPng) || !is_file($origen)) return false;

        $info = @getimagesize($origen);
        if ($info === false) return false;
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($origen); break;
            case IMAGETYPE_PNG:  $img = @imagecreatefrompng($origen);  break;
            case IMAGETYPE_GIF:  $img = @imagecreatefromgif($origen);  break;
            case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($origen); break;
            default: return false;
        }
        if (!$img) return false;
        imagepalettetotruecolor($img);

        $ancho = imagesx($img);
        $alto  = imagesy($img);

        // Logo recortado a su contenido y escalado (conserva el canal alfa)
        $lw = max(80, (int) round($ancho * $anchoRel));
        $esc = self::logoEscalado($logoPng, $lw);
        if (!$esc) return false;
        $lh = imagesy($esc);

        // Posición: esquina inferior derecha, margen del 2,5 % del ancho
        $margen = (int) round($ancho * 0.025);
        $px = max(0, $ancho - $lw - $margen);
        $py = max(0, $alto - $lh - $margen);

        // Luminosidad relativa media (WCAG, canales linealizados) del fondo bajo el logo,
        // muestreando cada 3 px. Con el logo marrón (L≈0,09) y el claro (L=1) el contraste
        // se iguala en L≈0,30: por debajo de eso el fondo es "oscuro" y se usa el logo claro.
        $suma = 0; $n = 0;
        $lin = fn(int $v) => pow($v / 255, 2.2);
        for ($x = max(0, $px - 6); $x < min($ancho, $px + $lw + 6); $x += 3) {
            for ($y = max(0, $py - 6); $y < min($alto, $py + $lh + 6); $y += 3) {
                $c = imagecolorat($img, $x, $y);
                $suma += 0.2126 * $lin(($c >> 16) & 0xFF) + 0.7152 * $lin(($c >> 8) & 0xFF) + 0.0722 * $lin($c & 0xFF);
                $n++;
            }
        }
        $fondoOscuro = $n > 0 && ($suma / $n) < 0.30;

        // Fondo oscuro: se usa la versión de marca pensada para fondos oscuros (texto claro).
        // Sin ese archivo, se recolorean los trazos oscuros a crema como plan B.
        $recolorear = false;
        if ($fondoOscuro) {
            $alt = $logoOscuro ? self::logoEscalado($logoOscuro, $lw) : null;
            if ($alt) {
                imagedestroy($esc);
                $esc = $alt;
                $lh = imagesy($esc);
                $py = max(0, $alto - $lh - $margen);
            } else {
                $recolorear = true;
            }
        }

        // Opacidad (+ plan B de recoloreado) en una pasada.
        // GD no combina la opacidad con el alfa del PNG, así que se ajusta píxel a píxel.
        $factor = max(0, min(100, $opacidad)) / 100;
        for ($x = 0; $x < $lw; $x++) {
            for ($y = 0; $y < $lh; $y++) {
                $c = imagecolorat($esc, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 127) continue;
                $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
                if ($recolorear && (0.299 * $r + 0.587 * $g + 0.114 * $b) < 110) { $r = 250; $g = 243; $b = 235; }
                $nuevoA = 127 - (int) round((127 - $a) * $factor);
                imagesetpixel($esc, $x, $y, ($nuevoA << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }

        imagealphablending($img, true);
        imagecopy($img, $esc, $px, $py, 0, 0, $lw, $lh);

        $ok = imagepng($img, $destino, 3);
        imagedestroy($img);
        imagedestroy($esc);
        return $ok;
    }

    /**
     * ¿Sigue visible el logo de marca (estampado por aplicarLogo en la esquina inferior derecha,
     * a un margen del 2,5 % del ancho) tras conservar solo la zona $ventana de la imagen?
     * $ventana: salida de encuadrar16x9; null = no se recortó nada (o se encajó entera).
     */
    public static function logoSobreviveAlRecorte(?array $ventana): bool
    {
        if (!$ventana) return true;
        $mitadMargen = $ventana['w'] * 0.025 / 2;
        return ($ventana['cy'] + $ventana['ch'] >= $ventana['h'] - $mitadMargen)
            && ($ventana['cx'] + $ventana['cw'] >= $ventana['w'] - $mitadMargen);
    }

    /** Carga un logo PNG, recorta sus márgenes transparentes y lo escala al ancho dado (con alfa). */
    private static function logoEscalado(string $ruta, int $lw)
    {
        if (!is_file($ruta)) return null;
        $logo = @imagecreatefrompng($ruta);
        if (!$logo) return null;
        imagepalettetotruecolor($logo);
        $x0 = imagesx($logo); $y0 = imagesy($logo); $x1 = -1; $y1 = -1;
        for ($x = 0; $x < imagesx($logo); $x++) {
            for ($y = 0; $y < imagesy($logo); $y++) {
                if ((((imagecolorat($logo, $x, $y) >> 24) & 0x7F)) < 120) {
                    $x0 = min($x0, $x); $y0 = min($y0, $y); $x1 = max($x1, $x); $y1 = max($y1, $y);
                }
            }
        }
        if ($x1 < 0) { imagedestroy($logo); return null; } // completamente transparente
        $cw = $x1 - $x0 + 1;
        $ch = $y1 - $y0 + 1;
        $lh = max(1, (int) round($ch * ($lw / $cw)));
        $esc = imagecreatetruecolor($lw, $lh);
        imagealphablending($esc, false);
        imagesavealpha($esc, true);
        imagefill($esc, 0, 0, imagecolorallocatealpha($esc, 0, 0, 0, 127));
        imagecopyresampled($esc, $logo, 0, 0, $x0, $y0, $lw, $lh, $cw, $ch);
        imagedestroy($logo);
        return $esc;
    }

    /**
     * Encuadra una imagen a 16:9 sin esconder lo importante (portadas del blog).
     *
     * - Si ya es (casi) 16:9, no toca nada.
     * - Si no, corta una ventana 16:9 que CONTENGA la caja del elemento principal
     *   ($caja en 0..1: x0, y0, x1, y1), centrada en ella.
     * - Si no hay caja o no cabe en la ventana (foto muy vertical, sujeto enorme),
     *   NO recorta: encaja la foto entera sobre un fondo desenfocado de sí misma.
     * El resultado se guarda como PNG en $destino (se pasa luego por aplicarLogo y WebP).
     *
     * @param array|null $caja ['x0'=>, 'y0'=>, 'x1'=>, 'y1'=>] normalizada 0..1
     * @return string|false  'igual' | 'recorte' | 'recorte-centrado' | 'fondo' | false si falla
     */
    public static function encuadrar16x9(string $origen, string $destino, ?array $caja, int $anchoSalida = 1600, ?array &$ventana = null)
    {
        $ventana = null; // zona de la imagen original que se ha conservado (cx, cy, cw, ch sobre w × h)
        $info = @getimagesize($origen);
        if ($info === false) return false;
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($origen); break;
            case IMAGETYPE_PNG:  $img = @imagecreatefrompng($origen);  break;
            case IMAGETYPE_GIF:  $img = @imagecreatefromgif($origen);  break;
            case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($origen); break;
            default: return false;
        }
        if (!$img) return false;
        imagepalettetotruecolor($img);
        $w = imagesx($img);
        $h = imagesy($img);
        $R = 16 / 9;
        $ratio = $w / $h;

        // Ya es 16:9 (±2 %): se conserva tal cual
        if (abs($ratio / $R - 1) <= 0.02) {
            $ok = imagepng($img, $destino, 3);
            imagedestroy($img);
            return $ok ? 'igual' : false;
        }

        $modo = 'fondo';
        $cx = $cy = $cw = $ch = 0;

        // Cajas a respetar, de más a menos ambiciosa: la completa y, si esa no cabe, la
        // del motivo principal ($caja['min']). Se intenta con un respiro del 3 % y sin él.
        $cajas = [];
        foreach ([$caja, is_array($caja) ? ($caja['min'] ?? null) : null] as $c) {
            if (is_array($c) && isset($c['x0'], $c['y0'], $c['x1'], $c['y1'])) $cajas[] = $c;
        }

        $vertical = $ratio < $R; // más alta que 16:9 → se corta arriba/abajo; si no, a los lados
        if ($vertical) { $cw = $w; $ch = (int) round($w / $R); $cx = 0; $eje = $h; $ven = $ch; }
        else           { $ch = $h; $cw = (int) round($h * $R); $cy = 0; $eje = $w; $ven = $cw; }

        if (!$cajas) {
            $pos = (int) round(($eje - $ven) / 2);
            if ($vertical) $cy = $pos; else $cx = $pos;
            $modo = 'recorte-centrado'; // sin caja: plan B centrado
        } else {
            foreach ($cajas as $c) {
                [$i0, $i1] = $vertical ? [$c['y0'], $c['y1']] : [$c['x0'], $c['x1']];
                foreach ([0.03, 0.0] as $m) {
                    $a = max(0.0, ((float) $i0 - $m) * $eje); $b = min((float) $eje, ((float) $i1 + $m) * $eje);
                    if ($b - $a >= 4 && $b - $a <= $ven) {
                        $pos = (int) round(max(0, min($eje - $ven, ($a + $b) / 2 - $ven / 2)));
                        if ($vertical) $cy = $pos; else $cx = $pos;
                        $modo = 'recorte';
                        break 2;
                    }
                }
            }
        }

        $esRecorte = $modo !== 'fondo';
        $ventana = $esRecorte ? ['cx' => $cx, 'cy' => $cy, 'cw' => $cw, 'ch' => $ch, 'w' => $w, 'h' => $h] : null;
        $salW = min($anchoSalida, $esRecorte ? $cw : $anchoSalida);
        $salW = max(320, $salW);
        $salH = (int) round($salW / $R);
        $out = imagecreatetruecolor($salW, $salH);

        if ($esRecorte) {
            imagecopyresampled($out, $img, 0, 0, $cx, $cy, $salW, $salH, $cw, $ch);
        } else {
            // Fondo: la misma foto rellenando el lienzo, muy reducida y desenfocada
            $pw = 320; $ph = (int) round($pw / $R);
            $peq = imagecreatetruecolor($pw, $ph);
            $esc = max($pw / $w, $ph / $h);
            $sw = (int) ceil($w * $esc); $sh = (int) ceil($h * $esc);
            $tmp = imagecreatetruecolor($sw, $sh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $sw, $sh, $w, $h);
            imagecopy($peq, $tmp, 0, 0, (int) (($sw - $pw) / 2), (int) (($sh - $ph) / 2), $pw, $ph);
            imagedestroy($tmp);
            for ($i = 0; $i < 25; $i++) imagefilter($peq, IMG_FILTER_GAUSSIAN_BLUR);
            imagefilter($peq, IMG_FILTER_BRIGHTNESS, -25);
            imagecopyresampled($out, $peq, 0, 0, 0, 0, $salW, $salH, $pw, $ph);
            imagedestroy($peq);
            // Foto entera, centrada, ajustada al alto
            $fh = $salH; $fw = (int) round($w * ($salH / $h));
            if ($fw > $salW) { $fw = $salW; $fh = (int) round($h * ($salW / $w)); }
            imagecopyresampled($out, $img, (int) (($salW - $fw) / 2), (int) (($salH - $fh) / 2), 0, 0, $fw, $fh, $w, $h);
        }

        $ok = imagepng($out, $destino, 3);
        imagedestroy($img);
        imagedestroy($out);
        return $ok ? $modo : false;
    }

    /**
     * Genera un nombre de archivo SEO-friendly
     *
     * @param string $slug Slug base
     * @param string $tipo 'logo' o 'galeria'
     * @param int|null $indice Índice para galería
     * @return string
     */
    public static function generarNombreSEO($slug, $tipo, $indice = null)
    {
        $slug = substr(preg_replace('/[^a-z0-9-]/', '', strtolower($slug)), 0, 50);
        $n    = str_pad((int)($indice ?? 1), 3, '0', STR_PAD_LEFT);

        // Logos: prefijo "logo-" para que se agrupen visualmente al abrir la carpeta
        // y no se confundan con archivos de galería que empiezan por NNN-.
        // Galería / portada / cliente: formato clásico NNN-slug-tipo.webp.
        if ($tipo === 'logo') {
            return 'logo-' . $n . '-' . $slug . '.webp';
        }
        return $n . '-' . $slug . '-' . $tipo . '.webp';
    }

    /**
     * Elimina una imagen
     *
     * @param string $ruta Ruta relativa desde la raíz del proyecto
     * @return bool
     */
    public static function eliminar($ruta)
    {
        if (empty($ruta)) {
            return false;
        }

        $rutaNormalizada = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta);
        $rutaCompleta = dirname(__DIR__) . DIRECTORY_SEPARATOR . $rutaNormalizada;

        if (file_exists($rutaCompleta)) {
            return @unlink($rutaCompleta);
        }

        return false;
    }

    /**
     * Copia imágenes de solicitud a crematorio y las registra en crematorio_imagenes.
     *
     * @param array  $imagenes     Array de rutas de imágenes de solicitud
     * @param string $nuevoSlug    Slug del nuevo crematorio
     * @param string $tipo         'logo' o 'galeria'
     * @param int    $crematorioId ID del crematorio (necesario para registrar en DB)
     * @return array Array de nuevas rutas
     */
    public static function copiarACrematorio($imagenes, $nuevoSlug, $tipo, $crematorioId = 0, $origen = 'manual_negocio')
    {
        $nuevasRutas = [];
        $baseDir = dirname(__DIR__);

        foreach ($imagenes as $indice => $ruta) {
            $rutaNormalizada = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta);
            $rutaOrigen = $baseDir . DIRECTORY_SEPARATOR . $rutaNormalizada;

            if (!file_exists($rutaOrigen)) {
                continue;
            }

            $nuevoNombre  = self::generarNombreSEO($nuevoSlug, $tipo, $indice + 1);
            $dirDestino   = $crematorioId > 0 ? self::dirCrematorio($crematorioId) : 'uploads/img-fichas/0000/';
            $rutaCompleta = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dirDestino);

            if (!is_dir($rutaCompleta)) {
                @mkdir($rutaCompleta, 0755, true);
            }

            $rutaDestino = $rutaCompleta . $nuevoNombre;
            if (@copy($rutaOrigen, $rutaDestino)) {
                $rutaRelativa = $dirDestino . $nuevoNombre;
                $nuevasRutas[] = $rutaRelativa;

                // Registrar en DB si tenemos crematorioId
                // Los logos subidos por humanos se marcan directamente como procesados
                if ($crematorioId > 0) {
                    $categoriaDirecta = ($tipo === 'logo') ? 'logo' : null;
                    self::guardarEnDB($crematorioId, $tipo, $rutaRelativa, $nuevoNombre, $categoriaDirecta, null, null, $origen);
                }
            }
        }

        return $nuevasRutas;
    }

    /**
     * Registra una imagen en crematorio_imagenes.
     * Si se pasa $categoria (subida manual por humano) → estado procesada, sin cola LLM.
     * Si no (scraping/crawling) → estado pendiente, pasa por cola LLM.
     *
     * @param int         $crematorioId
     * @param string      $tipo          'logo' | 'galeria' | 'portada' | 'cliente'
     * @param string      $ruta          Ruta relativa del archivo (forward slashes)
     * @param string      $nombreArchivo Nombre del archivo
     * @param string|null $categoria     Categoría conocida (salta LLM). Null = necesita LLM.
     * @param string|null $altText       Alt text descriptivo (opcional)
     * @param int|null    $resenaId      ID de reseña vinculada (solo para tipo='cliente'). Null si no aplica.
     * @return int|false ID insertado o false si falla
     */
    public static function guardarEnDB($crematorioId, $tipo, $ruta, $nombreArchivo, $categoria = null, $altText = null, $resenaId = null, $origen = 'desconocido')
    {
        require_once __DIR__ . '/conexion_db.php';
        $pdo = obtenerConexion();
        if (!$pdo) return false;

        // Subida humana con categoría conocida → procesada de inmediato
        // Scraping/crawling sin categoría → pendiente (necesita LLM)
        $estadoLlm = ($categoria !== null) ? 'procesada' : 'pendiente';

        try {
            $sql = "INSERT INTO crematorio_imagenes
                        (crematorio_id, resena_id, tipo, origen, nombre_archivo, ruta, estado_llm, categoria, alt_text, created_at)
                    VALUES
                        (:crematorio_id, :resena_id, :tipo, :origen, :nombre_archivo, :ruta, :estado_llm, :categoria, :alt_text, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':crematorio_id'  => (int) $crematorioId,
                ':resena_id'      => $resenaId !== null ? (int) $resenaId : null,
                ':tipo'           => $tipo,
                ':origen'         => $origen,
                ':nombre_archivo' => $nombreArchivo,
                ':ruta'           => $ruta,
                ':estado_llm'     => $estadoLlm,
                ':categoria'      => $categoria,
                ':alt_text'       => $altText,
            ]);
            return (int) $pdo->lastInsertId();
        } catch (Exception $e) {
            error_log('ImagenHelper::guardarEnDB - ERROR: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía email al admin cuando hay imágenes pendientes de análisis LLM.
     * Agrupa notificaciones: solo envía si no se envió en las últimas 4 horas.
     *
     * @param int $totalPendientes Total de imágenes en cola (incluida la recién agregada)
     * @return bool
     */
    public static function notificarAdminImagenesPendientes($totalPendientes)
    {
        require_once __DIR__ . '/config.php';

        $lockFile = sys_get_temp_dir() . '/crematorios_llm_notif.lock';
        $cooldownHoras = 4;

        // Evitar spam: no reenviar si el lock file tiene menos de 4 horas
        if (file_exists($lockFile)) {
            $diff = (time() - filemtime($lockFile)) / 3600;
            if ($diff < $cooldownHoras) {
                return false;
            }
        }

        $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'lycapolis@gmail.com';
        $baseUrl    = defined('BASE_URL')    ? BASE_URL    : '';

        $asunto  = "[Crematorios de Mascotas] {$totalPendientes} imágenes pendientes de análisis LLM";
        $cuerpo  = "Hola,\n\n";
        $cuerpo .= "Hay {$totalPendientes} imagen(es) pendiente(s) de procesar con Claude Vision.\n\n";
        $cuerpo .= "Accedé al panel de administración para ejecutar el batch:\n";
        $cuerpo .= $baseUrl . "/admin/imagenes-cola.php\n\n";
        $cuerpo .= "O ejecutá el script desde la línea de comandos:\n";
        $cuerpo .= "php scripts/procesar-imagenes-llm.php\n\n";
        $cuerpo .= "— Sistema automático de Crematorios de Mascotas";

        $headers = "From: no-reply@crematoriosdemascotas.com\r\nContent-Type: text/plain; charset=UTF-8";

        $enviado = @mail($adminEmail, $asunto, $cuerpo, $headers);

        if ($enviado) {
            // Actualizar lock file
            file_put_contents($lockFile, time());
        }

        return $enviado;
    }

    /**
     * Procesa múltiples archivos de galería
     *
     * @param array $archivos $_FILES['galeria'] (múltiples archivos)
     * @param string $slug Slug para nombres
     * @param string $destino 'solicitudes' o 'crematorios'
     * @param int $maxImagenes Máximo de imágenes permitidas
     * @return array ['ok' => bool, 'imagenes' => array, 'errores' => array]
     */
    public static function procesarGaleria($archivos, $slug, $destino = 'solicitudes', $maxImagenes = 10)
    {
        $resultado = [
            'ok' => true,
            'imagenes' => [],
            'errores' => []
        ];

        // Verificar estructura
        if (!isset($archivos['name']) || !is_array($archivos['name'])) {
            return $resultado;
        }

        $total = min(count($archivos['name']), $maxImagenes);

        for ($i = 0; $i < $total; $i++) {
            // Verificar que hay archivo
            if (empty($archivos['tmp_name'][$i])) {
                continue;
            }

            // Construir array de archivo individual
            $archivo = [
                'name'     => $archivos['name'][$i],
                'type'     => $archivos['type'][$i],
                'tmp_name' => $archivos['tmp_name'][$i],
                'error'    => $archivos['error'][$i],
                'size'     => $archivos['size'][$i]
            ];

            // Procesar
            $procesado = self::procesar($archivo, 'galeria', $slug, $destino, $i + 1);

            if ($procesado['ok']) {
                $resultado['imagenes'][] = $procesado;
            } else {
                $resultado['errores'][] = "Imagen " . ($i + 1) . ": " . $procesado['error'];
            }
        }

        return $resultado;
    }
}
