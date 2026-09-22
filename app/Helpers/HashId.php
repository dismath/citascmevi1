<?php
namespace App\Helpers;

class HashId
{
    private static string $cipher = 'AES-256-CBC';

    private static function getKey(): string
    {
        $key = Env::get('HASHID_SECRET_KEY', 'CmeviProSuperSecretKey2026!@#');
        return hash('sha256', $key);
    }

    private static function getIv(): string
    {
        $iv = Env::get('HASHID_SECRET_IV', 'CmeviProInitVector');
        return substr(hash('sha256', $iv), 0, 16);
    }

    /**
     * Encrypt an integer ID into a safe base64url string.
     */
    public static function encode(int $id): string
    {
        $key = self::getKey();
        $iv = self::getIv();
        
        $encrypted = openssl_encrypt((string)$id, self::$cipher, $key, 0, $iv);
        
        // Make it URL safe: replace + with -, / with _, and remove = padding
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encrypted));
    }

    /**
     * Decrypt a base64url string back to an integer ID.
     */
    public static function decode(string $hash): ?int
    {
        if (empty($hash) || is_numeric($hash)) {
            // Optional: Support raw IDs during transition
            // For strict security, uncomment this out so raw IDs fail:
            // return null; 
        }

        // Restore base64 padding and characters
        $base64 = str_replace(['-', '_'], ['+', '/'], $hash);
        $padding = strlen($base64) % 4;
        if ($padding) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $encrypted = base64_decode($base64);
        if ($encrypted === false) {
            return null;
        }

        $key = self::getKey();
        $iv = self::getIv();
        
        $decrypted = openssl_decrypt($encrypted, self::$cipher, $key, 0, $iv);
        
        if ($decrypted !== false && is_numeric($decrypted)) {
            return (int)$decrypted;
        }

        return null;
    }
}
