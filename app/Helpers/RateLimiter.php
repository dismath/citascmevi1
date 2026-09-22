<?php
namespace App\Helpers;

class RateLimiter
{
    /**
     * Revisa si se ha excedido el límite de peticiones (Rate Limit).
     * Si se excede, detiene la ejecución, manda HTTP 429 y cabecera Retry-After.
     *
     * @param string $endpoint Identificador de la ruta (ej: 'login_web', 'api_global')
     * @param int $limit Límite de peticiones permitidas
     * @param int $windowSeconds Ventana de tiempo en segundos (ej: 60 para 1 minuto)
     * @param string|null $ip IP específica (si es null se toma de $_SERVER)
     */
    public static function check(string $endpoint, int $limit, int $windowSeconds, ?string $ip = null): void
    {
        $ip = $ip ?? self::resolveIp();
        $time = time();
        
        // Calcular el bucket de tiempo actual. 
        // Ejemplo: si son las 12:05:30 y windowSeconds=60, el bucket es 12:05:00.
        $bucket = floor($time / $windowSeconds) * $windowSeconds;
        $key = "rl:{$endpoint}:{$ip}:{$bucket}";

        $hits = 1;

        if (function_exists('apcu_inc')) {
            // OPCIÓN 1: APCu (Memoria RAM, ultra rápido)
            $hits = apcu_inc($key, 1, $success, $windowSeconds + 10);
            if (!$success) {
                // Si la clave no existía, apcu_inc la crea pero devuelve false en success a veces dependiendo de versión.
                // Aseguramos guardarla si falló el incremento.
                apcu_store($key, 1, $windowSeconds + 10);
                $hits = 1;
            }
        } else {
            // OPCIÓN 2: MySQL (Fallback seguro con Upsert)
            try {
                $db = Database::getInstance();
                
                // Self-healing: crear la tabla si no existe (normalmente hecho en migraciones)
                self::ensureTableExists($db);

                // INSERT ... ON DUPLICATE KEY UPDATE es atómico y rápido
                $sql = "INSERT INTO rate_limits (ip_address, endpoint, time_bucket, hits) 
                        VALUES (?, ?, ?, 1) 
                        ON DUPLICATE KEY UPDATE hits = hits + 1";
                $db->execute($sql, [$ip, $endpoint, $bucket]);

                // Recuperar el valor actual
                $row = $db->fetch(
                    "SELECT hits FROM rate_limits WHERE ip_address = ? AND endpoint = ? AND time_bucket = ?",
                    [$ip, $endpoint, $bucket]
                );
                $hits = (int)($row['hits'] ?? 1);

                // Probabilidad del 1% de limpiar buckets viejos (Garbage Collection)
                if (mt_rand(1, 100) === 1) {
                    $db->execute("DELETE FROM rate_limits WHERE time_bucket < ?", [$time - ($windowSeconds * 2)]);
                }
            } catch (\Throwable $e) {
                // Fallback silencioso: si la DB falla (ej: falta tabla o permisos),
                // permitimos la petición para no bloquear todo el sistema con error 500.
                error_log('[RateLimiter] Error de base de datos: ' . $e->getMessage());
                $hits = 1;
            }
        }

        // ¿Límite excedido?
        if ($hits > $limit) {
            $retryAfter = ($bucket + $windowSeconds) - $time;
            if ($retryAfter < 0) {
                $retryAfter = $windowSeconds;
            }

            \App\Models\SecurityAudit::log(
                null,
                \App\Models\SecurityAudit::EVENTO_RATE_LIMIT_EXCEDIDO,
                \App\Models\SecurityAudit::RESULTADO_BLOQUEADO,
                ['endpoint' => $endpoint, 'ip' => $ip, 'hits' => $hits, 'limit' => $limit, 'retry_after' => $retryAfter]
            );

            \App\Models\SecurityAudit::logCriticalAlert(
                "Rate limit excedido en endpoint [{$endpoint}] ({$hits}/{$limit})",
                ['endpoint' => $endpoint, 'ip' => $ip, 'hits' => $hits, 'limit' => $limit]
            );

            http_response_code(429);
            header("Retry-After: " . $retryAfter);

            $isJson = (
                strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
                strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false ||
                (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            );

            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Demasiadas peticiones (Too Many Requests). Por favor, intenta de nuevo más tarde.',
                    'retry_after' => $retryAfter
                ]);
            } else {
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>429 Too Many Requests</title>';
                echo '<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc;color:#1e293b}';
                echo '.box{background:#fff;padding:2.5rem;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.05);max-width:480px;text-align:center}';
                echo 'h1{color:#ef4444;margin:0 0 1rem;font-size:1.5rem}p{color:#64748b;line-height:1.6}</style></head><body>';
                echo '<div class="box"><h1>Demasiadas peticiones</h1><p>Has superado el límite de solicitudes permitidas. Por seguridad, espera <b>' . (int)$retryAfter . ' segundos</b> antes de intentar nuevamente.</p></div></body></html>';
            }
            exit;
        }
    }

    /**
     * Limita ráfagas concurrentes instantáneas (ej. más de 25 peticiones en 2 segundos).
     * Mitiga ataques DoS y fuzzing de endpoints.
     */
    public static function checkBurst(?string $ip = null, int $maxBurst = 25, int $windowSeconds = 2): void
    {
        $ip = $ip ?? self::resolveIp();
        self::check('burst_protection', $maxBurst, $windowSeconds, $ip);
    }

    /**
     * Limpia los intentos de una IP y endpoint (útil para cuando el login es exitoso)
     */
    public static function clear(string $endpoint, ?string $ip = null): void
    {
        $ip = $ip ?? self::resolveIp();
        $time = time();
        
        // Como no sabemos la ventana exacta desde fuera, limpiamos en MySQL todos los de esa IP y endpoint
        if (!function_exists('apcu_inc')) {
            $db = Database::getInstance();
            try {
                $db->execute("DELETE FROM rate_limits WHERE ip_address = ? AND endpoint = ?", [$ip, $endpoint]);
            } catch (\Exception $e) {}
        }
    }

    /**
     * Resuelve la IP real del cliente, considerando proxies y CDN (Cloudflare).
     */
    public static function resolveIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $rawIp = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($rawIp, FILTER_VALIDATE_IP)) {
                    return $rawIp;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private static function ensureTableExists($db): void
    {
        // Caché estática para evitar consultar INFORMATION_SCHEMA en cada request
        static $checked = false;
        if ($checked) return;

        try {
            $db->execute("
                CREATE TABLE IF NOT EXISTS `rate_limits` (
                    `ip_address` VARCHAR(45) NOT NULL,
                    `endpoint` VARCHAR(100) NOT NULL,
                    `time_bucket` INT NOT NULL,
                    `hits` INT DEFAULT 1,
                    PRIMARY KEY (`ip_address`, `endpoint`, `time_bucket`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");
            $checked = true;
        } catch (\Exception $e) {
            // Ignorar errores concurrentes de creación de tabla
        }
    }
}
