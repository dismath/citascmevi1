<?php
namespace App\Helpers;

class ApiMiddleware
{
    /**
     * Handle CORS headers for API requests
     */
    public static function handleCors(): void
    {
        $allowedOrigin = Env::get('CORS_ALLOWED_ORIGIN', '*');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if ($allowedOrigin !== '*' && !empty($origin)) {
            $allowedList = array_map('trim', explode(',', $allowedOrigin));
            if (in_array($origin, $allowedList, true)) {
                header("Access-Control-Allow-Origin: " . $origin);
            }
        } else {
            header("Access-Control-Allow-Origin: " . $allowedOrigin);
        }

        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Timeout de ejecución de peticiones API (30 segundos)
        if (!headers_sent()) {
            @set_time_limit(30);
        }

        // Límite de ráfagas instantáneas (Anti-DoS)
        RateLimiter::checkBurst();

        // Límite de tamaño de payload para peticiones JSON (máx 256 KB)
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength > 256 * 1024) {
            View::json(['success' => false, 'message' => 'El tamaño del payload excede el límite permitido (máximo 256 KB)'], 413);
        }
    }

    /**
     * Protect API routes using JWT
     */
    public static function requireAuth(): array
    {
        self::handleCors();
        header('Content-Type: application/json; charset=utf-8');

        // Global API Rate Limit (60 requests per minute per IP) to mitigate DDoS
        RateLimiter::check('api_global', 60, 60);

        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            View::json(['success' => false, 'message' => 'Token no proporcionado o inválido'], 401);
        }

        $token = $matches[1];
        $payload = JwtAuth::validateAccessToken($token);

        if (!$payload) {
            View::json(['success' => false, 'message' => 'Token expirado o inválido'], 401);
        }

        return $payload;
    }

    /**
     * Require a specific role for API routes
     */
    public static function requireRole(array $roles): array
    {
        $payload = self::requireAuth();
        
        if (!in_array($payload['role'], $roles, true)) {
            View::json(['success' => false, 'message' => 'Acceso denegado. Rol insuficiente.'], 403);
        }

        return $payload;
    }
}
