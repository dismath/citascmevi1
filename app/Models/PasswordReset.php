<?php
namespace App\Models;

use App\Helpers\Database;

class PasswordReset
{
    public const STATUS_VALID   = 'valid';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_USED    = 'used';
    public const STATUS_INVALID = 'invalid';

    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createToken(int $userId, int $expiryHours = 24): string
    {
        return $this->createTokenForType($userId, 'admin', $expiryHours);
    }

    public function createTokenForType(int $userId, string $userType, int $expiryHours = 24): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

        // Asegurar que el userType sea uno de los permitidos por el enum de la BD
        $validTypes = ['admin', 'doctor', 'patient'];
        if (!in_array($userType, $validTypes, true)) {
            $userType = 'admin';
        }

        // Invalidar tokens anteriores no utilizados del mismo usuario
        $this->db->execute(
            "UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL",
            [$userId]
        );

        $this->db->execute(
            "INSERT INTO password_resets (user_id, user_type, token_hash, expires_at) VALUES (?, ?, ?, ?)",
            [$userId, $userType, $hash, $expires]
        );

        return $token;
    }

    /**
     * Inspecciona y clasifica el estado exacto del token.
     * Permite distinguir si un token es válido, si ya fue utilizado,
     * o si caducó el tiempo establecido (24 horas).
     *
     * @param string $token
     * @return array{status: string, data: ?array}
     */
    public function inspectToken(string $token): array
    {
        if (empty($token)) {
            return ['status' => self::STATUS_INVALID, 'data' => null];
        }

        $hash = hash('sha256', $token);
        $row = $this->db->fetch(
            "SELECT *, (expires_at <= NOW()) as is_expired FROM password_resets 
             WHERE token_hash = ? 
             ORDER BY id DESC LIMIT 1",
            [$hash]
        );

        if (!$row) {
            // Retrocompatibilidad con hashes legacy de bcrypt si existieran
            $rows = $this->db->fetchAll("SELECT *, (expires_at <= NOW()) as is_expired FROM password_resets ORDER BY id DESC LIMIT 25");
            foreach ($rows as $r) {
                if (password_verify($token, $r['token_hash'])) {
                    $row = $r;
                    break;
                }
            }
        }

        if (!$row) {
            return ['status' => self::STATUS_INVALID, 'data' => null];
        }

        // 1. Si ya fue utilizado para establecer o cambiar la clave
        if (!empty($row['used_at'])) {
            return ['status' => self::STATUS_USED, 'data' => $row];
        }

        // 2. Verificar si caducó el tiempo establecido
        $isExpiredSql = !empty($row['is_expired']);
        $isExpiredPhp = (strtotime($row['expires_at']) <= time());
        if ($isExpiredSql || $isExpiredPhp) {
            return ['status' => self::STATUS_EXPIRED, 'data' => $row];
        }

        // 3. Válido y activo (permite crear o cambiar contraseña)
        return ['status' => self::STATUS_VALID, 'data' => $row];
    }

    public function validateToken(string $token): ?array
    {
        $inspection = $this->inspectToken($token);
        return ($inspection['status'] === self::STATUS_VALID) ? $inspection['data'] : null;
    }

    public function markUsed(string $token): void
    {
        $hash = hash('sha256', $token);
        $this->db->execute("UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?", [$hash]);

        $rows = $this->db->fetchAll("SELECT id, token_hash FROM password_resets WHERE used_at IS NULL");
        foreach ($rows as $r) {
            if (password_verify($token, $r['token_hash'])) {
                $this->db->execute("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [$r['id']]);
                break;
            }
        }
    }
}
