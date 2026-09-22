<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * ImageOptimizer Helper
 * Procesa, valida, comprime y renombra de forma segura las imágenes subidas al sistema.
 */
class ImageOptimizer
{
    /**
     * Extensiones y tipos MIME estrictamente permitidos
     */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/jpg'   => 'jpg',
        'image/png'   => 'png',
        'image/x-png' => 'png',
        'image/webp'  => 'webp',
        'image/gif'   => 'gif'
    ];

    /**
     * Valida, renombra aleatoriamente y comprime una imagen subida.
     *
     * @param array $file Array $_FILES['input_name']
     * @param string $subDir Subcarpeta dentro de public/uploads/ (ej: 'company')
     * @param int $maxWidth Ancho máximo permitido (reescala proporcionalmente si es mayor)
     * @param int $maxHeight Alto máximo permitido
     * @param int $quality Calidad de compresión (1-100 para JPEG/WebP, 0-9 para PNG)
     * @param int $maxSizeBytes Tamaño máximo en bytes (defecto 5MB)
     * @return string Ruta relativa del archivo guardado (ej: 'uploads/company/logo_abc123.png')
     * @throws \Exception
     */
    public static function processUpload(
        array $file,
        string $subDir = 'company',
        int $maxWidth = 600,
        int $maxHeight = 300,
        int $quality = 85,
        int $maxSizeBytes = 5 * 1024 * 1024
    ): string {
        // 1. Validar errores de subida nativos
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            throw new \Exception(self::getUploadErrorMessage($errorCode));
        }

        // 2. Validar que sea un archivo realmente subido por HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \Exception('El archivo proporcionado no es una subida válida.');
        }

        // 3. Validar límite de peso
        if ($file['size'] > $maxSizeBytes) {
            $mb = round($maxSizeBytes / (1024 * 1024), 1);
            throw new \Exception("La imagen excede el tamaño máximo permitido ({$mb} MB).");
        }

        // 4. Validar tipo MIME real inspeccionando los bytes del archivo (no confiar en la extensión enviada)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIMES[$mime])) {
            throw new \Exception('Formato de imagen no permitido. Solo se aceptan archivos PNG, JPG, JPEG o WEBP.');
        }

        // 5. Validar que la imagen sea legible por GD y obtener dimensiones reales
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new \Exception('El archivo subido no es una imagen válida o está dañado.');
        }

        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];
        $detectedMime = $imageInfo['mime'];

        if ($origWidth <= 0 || $origHeight <= 0) {
            throw new \Exception('Dimensiones de imagen inválidas.');
        }

        // 6. Determinar extensión segura y generar nombre aleatorio criptográfico
        $extension = self::ALLOWED_MIMES[$mime] ?? 'png';
        // Generamos un token aleatorio seguro para prevenir colisiones y ataques de adivinación
        $secureToken = bin2hex(random_bytes(16));
        $newFilename = 'logo_' . $secureToken . '.' . $extension;

        // 7. Preparar directorio de destino en public/uploads/{subDir}
        $publicPath = defined('PUBLIC_PATH') ? PUBLIC_PATH : (dirname(__DIR__, 2) . '/public');
        $targetDir = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subDir;

        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new \Exception('No se pudo crear el directorio de almacenamiento de imágenes.');
            }
        }

        // Crear archivo .htaccess protector en el directorio para impedir ejecución de scripts
        self::ensureDirectorySecurity($targetDir);

        $destinationPath = $targetDir . DIRECTORY_SEPARATOR . $newFilename;

        // 8. Cargar recurso GD según tipo MIME
        $srcImage = match ($detectedMime) {
            'image/jpeg', 'image/pjpeg', 'image/jpg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png', 'image/x-png'               => @imagecreatefrompng($file['tmp_name']),
            'image/webp'                             => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            'image/gif'                              => @imagecreatefromgif($file['tmp_name']),
            default                                  => false,
        };

        if (!$srcImage) {
            // Si la librería GD falla al cargar el recurso, mover el archivo con validación de seguridad
            if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
                throw new \Exception('No se pudo guardar la imagen en el servidor.');
            }
            return 'uploads/' . $subDir . '/' . $newFilename;
        }

        // 9. Calcular nuevas dimensiones manteniendo la relación de aspecto (sin estirar ni agrandar imágenes pequeñas)
        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
            $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
            $newWidth = (int)round($origWidth * $ratio);
            $newHeight = (int)round($origHeight * $ratio);
        }

        // 10. Crear lienzo destino optimizado
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG, WebP y GIF
        if ($extension === 'png' || $extension === 'webp' || $extension === 'gif') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            // Fondo blanco para JPEGs
            $white = imagecolorallocate($dstImage, 255, 255, 255);
            imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $white);
        }

        // 11. Remuestreo de alta calidad (resample)
        imagecopyresampled(
            $dstImage,
            $srcImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $origWidth, $origHeight
        );

        // 12. Guardar y comprimir la imagen
        $saved = false;
        switch ($extension) {
            case 'png':
                // Calidad PNG en GD va de 0 (sin compresión) a 9 (máxima compresión)
                $pngQuality = (int)round(9 - (($quality / 100) * 9));
                $pngQuality = max(0, min(9, $pngQuality ?: 8));
                $saved = imagepng($dstImage, $destinationPath, $pngQuality);
                break;

            case 'webp':
                if (function_exists('imagewebp')) {
                    $saved = imagewebp($dstImage, $destinationPath, max(60, min(100, $quality)));
                } else {
                    $saved = imagejpeg($dstImage, $destinationPath, max(60, min(100, $quality)));
                }
                break;

            case 'gif':
                $saved = imagegif($dstImage, $destinationPath);
                break;

            case 'jpg':
            default:
                $saved = imagejpeg($dstImage, $destinationPath, max(60, min(100, $quality)));
                break;
        }

        // Liberar memoria
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        if (!$saved || !file_exists($destinationPath)) {
            throw new \Exception('Ocurrió un error al comprimir y guardar la imagen en el servidor.');
        }

        // Retornar ruta relativa normalizada para almacenar en la base de datos
        return 'uploads/' . $subDir . '/' . $newFilename;
    }

    /**
     * Elimina de forma segura un archivo de imagen anterior del servidor.
     */
    public static function deleteFile(?string $relativePath): bool
    {
        if (empty($relativePath)) {
            return false;
        }

        // Evitar ataques de directory traversal sanitizando la ruta
        $cleanPath = str_replace(['..', "\0"], '', $relativePath);
        $cleanPath = ltrim($cleanPath, '/\\');

        $publicPath = defined('PUBLIC_PATH') ? PUBLIC_PATH : (dirname(__DIR__, 2) . '/public');
        $fullPath = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath);

        if (file_exists($fullPath) && is_file($fullPath)) {
            return @unlink($fullPath);
        }

        return false;
    }

    /**
     * Garantiza que el directorio de subidas tenga protección contra ejecución directa de scripts
     */
    private static function ensureDirectorySecurity(string $dir): void
    {
        $htaccessPath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $content = "# Deshabilitar ejecucion de scripts en carpeta de uploads\n" .
                       "<FilesMatch \"\.(php|php5|php7|php8|phtml|phar|pl|py|cgi|asp|js|sh)$\">\n" .
                       "    Order Deny,Allow\n" .
                       "    Deny from all\n" .
                       "</FilesMatch>\n";
            @file_put_contents($htaccessPath, $content);
        }
    }

    /**
     * Mensajes de error amigables para códigos UPLOAD_ERR
     */
    private static function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'El archivo excede el límite configurado en php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo permitido por el formulario.',
            UPLOAD_ERR_PARTIAL    => 'El archivo solo se subió parcialmente. Intente de nuevo.',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún archivo para subir.',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el disco.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión de PHP detuvo la subida del archivo.',
            default               => 'Error desconocido al subir el archivo.',
        };
    }
}
