<?php
namespace App\Helpers;

/**
 * Crypto Helper - Symmetric encryption for sensitive data (AES-256-CBC)
 */
class Crypto
{
    private static string $cipher = 'aes-256-cbc';

    /**
     * Get the encryption key from environment
     */
    private static function getKey(): string
    {
        $key = \App\Helpers\Env::get('APP_KEY');
        if (!$key) {
            throw new \Exception("APP_KEY environment variable not set.");
        }
        return base64_decode($key);
    }

    /**
     * Encrypt a string
     */
    public static function encrypt(?string $data): ?string
    {
        if ($data === null || $data === '') {
            return $data;
        }

        // Check if already encrypted (starts with ENC:)
        if (strpos($data, 'ENC:') === 0) {
            return $data;
        }

        $key = self::getKey();
        $ivlen = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        
        $ciphertext = openssl_encrypt($data, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        
        // HMAC for integrity
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        
        // Format: ENC:base64(hmac . iv . ciphertext)
        return 'ENC:' . base64_encode($hmac . $iv . $ciphertext);
    }

    /**
     * Decrypt a string
     */
    public static function decrypt(?string $data): ?string
    {
        if ($data === null || $data === '') {
            return $data;
        }

        if (strpos($data, 'ENC:') !== 0) {
            // Not encrypted
            return $data;
        }

        $payload = base64_decode(substr($data, 4));
        if ($payload === false) {
            return $data; // Failed to decode
        }

        $key = self::getKey();
        $hmacLen = 32; // sha256 output length in bytes
        $ivlen = openssl_cipher_iv_length(self::$cipher);

        if (strlen($payload) < $hmacLen + $ivlen) {
            return $data; // Invalid payload
        }

        $hmac = substr($payload, 0, $hmacLen);
        $iv = substr($payload, $hmacLen, $ivlen);
        $ciphertext = substr($payload, $hmacLen + $ivlen);

        $calcmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        if (!hash_equals($hmac, $calcmac)) {
            return $data; // Integrity check failed, maybe old key or corrupted
        }

        $original = openssl_decrypt($ciphertext, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($original === false) {
            return $data; // Decryption failed
        }

        return $original;
    }
}
