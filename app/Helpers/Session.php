<?php
namespace App\Helpers;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Seguridad extrema para cookies de sesión
            ini_set('session.use_only_cookies', '1'); // Previene Session Fixation
            ini_set('session.use_strict_mode', '1'); // Rechaza IDs de sesión no inicializados por el servidor
            
            // Establecer parámetros de la cookie explícitamente
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            // FASE 2: Escalabilidad - Usar Redis si está configurado en entorno
            $redisHost = \App\Helpers\Env::get('REDIS_HOST');
            if ($redisHost) {
                ini_set('session.save_handler', 'redis');
                $redisPort = \App\Helpers\Env::get('REDIS_PORT', '6379');
                ini_set('session.save_path', "tcp://{$redisHost}:{$redisPort}");
            }

            session_start();

            // Cierre automático de sesión por inactividad (30 minutos = 1800 segundos)
            $timeout = 1800;
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
                // Preservar CSRF token para que formularios ya cargados puedan validarse
                $preservedCsrf = $_SESSION['csrf_token'] ?? null;
                session_unset();     // unset $_SESSION variable for the run-time
                session_destroy();   // destroy session data in storage
                session_start();     // start a new clean session
                $_SESSION['_flash']['error'] = 'Su sesión ha expirado por inactividad.';
                if ($preservedCsrf) {
                    $_SESSION['csrf_token'] = $preservedCsrf;
                }
            }
            $_SESSION['last_activity'] = time(); // update last activity time stamp
        }
    }

    /**
     * Libera el bloqueo del archivo de sesión.
     * CRÍTICO para alta concurrencia (500+ usuarios).
     * Permite que otras peticiones del mismo usuario se procesen en paralelo.
     */
    public static function releaseLock(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Regenera el ID de sesión de forma segura, preservando los datos.
     * Previene pérdida de datos en redirecciones rápidas (Session Fixation fix).
     */
    public static function regenerateId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $data = $_SESSION;
            // Usar false para evitar destruir la sesión en tránsito inmediatamente y prevenir condiciones de carrera
            session_regenerate_id(false);
            $_SESSION = $data;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function userRole(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function userType(): ?string
    {
        return $_SESSION['user_type'] ?? null;
    }

    public static function generateCsrf(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function csrfInput(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generateCsrf()) . '">';
    }
}
