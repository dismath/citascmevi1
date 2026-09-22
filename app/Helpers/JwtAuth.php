<?php
namespace App\Helpers;

/**
 * JWT Authentication Helper
 * Handles generating and verifying JSON Web Tokens without external dependencies.
 */
class JwtAuth
{
    private static function getSecret(): string
    {
        $key = $_ENV['APP_KEY'] ?? $_SERVER['APP_KEY'] ?? getenv('APP_KEY');
        if (!$key) {
            throw new \Exception("APP_KEY environment variable not set.");
        }
        return $key;
    }

    /**
     * Generate an Access Token valid for a short time (e.g., 30 minutes)
     */
    public static function generateAccessToken(int $userId, string $role): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        
        $payload = json_encode([
            'user_id' => $userId,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + (30 * 60) // 30 minutes
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::getSecret(), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Generate a random Refresh Token
     */
    public static function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate an Access Token and return its payload if valid
     */
    public static function validateAccessToken(string $token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        list($header64, $payload64, $signature64) = $parts;

        $signature = hash_hmac('sha256', $header64 . "." . $payload64, self::getSecret(), true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        if (!hash_equals($base64UrlSignature, $signature64)) {
            return false;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload64)), true);

        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return false; // Expired
        }

        return $payload;
    }
}
