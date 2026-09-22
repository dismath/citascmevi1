<?php
namespace App\Models;

class TwoFactorCode extends BaseModel
{
    protected string $table = 'two_factor_codes';

    /**
     * Genera un nuevo código de verificación de 6 dígitos para el usuario.
     * Invalida códigos anteriores del mismo usuario y tipo.
     */
    public function generate(string $userType, int $userId, ?string $ip = null, ?string $userAgent = null): string
    {
        // Limpiar códigos anteriores del usuario
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE user_type = ? AND user_id = ?",
            [$userType, $userId]
        );

        // Código criptográficamente seguro de 6 dígitos
        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $this->db->execute(
            "INSERT INTO {$this->table} (user_type, user_id, code, ip_address, user_agent, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW())",
            [
                $userType,
                $userId,
                $code,
                $ip,
                substr($userAgent ?? '', 0, 255)
            ]
        );

        return $code;
    }

    /**
     * Verifica si el código ingresado coincide y aún es válido.
     * Si es válido, lo consume (elimina) y retorna true.
     */
    public function verify(string $userType, int $userId, string $code): bool
    {
        $cleanCode = preg_replace('/\s+/', '', trim($code));

        $record = $this->db->fetch(
            "SELECT id FROM {$this->table} 
             WHERE user_type = ? AND user_id = ? AND code = ? AND expires_at >= NOW()
             LIMIT 1",
            [$userType, $userId, $cleanCode]
        );

        if ($record) {
            // Código consumido con éxito, se elimina para evitar re-uso
            $this->db->execute(
                "DELETE FROM {$this->table} WHERE id = ?",
                [$record['id']]
            );
            return true;
        }

        return false;
    }

    /**
     * Verifica si puede reenviar un código según el tiempo de espera (cooldown).
     */
    public function canResend(string $userType, int $userId, int $cooldownSeconds = 60): bool
    {
        $last = $this->db->fetch(
            "SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) as elapsed FROM {$this->table}
             WHERE user_type = ? AND user_id = ?
             ORDER BY id DESC LIMIT 1",
            [$userType, $userId]
        );

        if (!$last || !isset($last['elapsed'])) {
            return true;
        }

        return ((int)$last['elapsed'] >= $cooldownSeconds);
    }

    /**
     * Obtiene los segundos restantes para que el código expire.
     */
    public function getSecondsRemaining(string $userType, int $userId): int
    {
        $last = $this->db->fetch(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) as remaining FROM {$this->table}
             WHERE user_type = ? AND user_id = ?
             ORDER BY id DESC LIMIT 1",
            [$userType, $userId]
        );

        if (!$last || !isset($last['remaining'])) {
            return 0;
        }

        return max(0, (int)$last['remaining']);
    }

    /**
     * Limpia códigos expirados hace más de 1 hora.
     */
    public function cleanup(): void
    {
        $this->db->execute(
            "DELETE FROM {$this->table} WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
    }
}
