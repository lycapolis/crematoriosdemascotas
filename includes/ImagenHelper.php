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
