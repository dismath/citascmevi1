<?php
namespace App\Helpers;

/**
 * Env Helper - Parse and load .env files
 */
class Env
{
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (trim($line) === '' || strpos(trim($line), '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) < 2) {
                continue;
            }
            $name = trim($parts[0]);
            $value = trim($parts[1]);
            
            // Remove quotes if present
            if (preg_match('/^"(.+)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.+)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            } else {
                // If it exists but is empty in $_SERVER, overwrite it
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $val = getenv($key);
        return $val !== false ? $val : $default;
    }

    /**
     * Resuelve la URL base de la aplicación de forma confiable.
     * Soporta detección de host activo en desarrollo local y dominios en producción.
     */
    public static function getAppUrl(): string
    {
        $appUrl = self::get('APP_URL');
        if (empty($appUrl) || str_contains($appUrl, 'localhost')) {
            if (!empty($_SERVER['HTTP_HOST'])) {
                $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
            } else {
                $appUrl = 'http://localhost';
            }
        }
        return rtrim($appUrl, '/');
    }
}
