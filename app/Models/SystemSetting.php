<?php
namespace App\Models;

class SystemSetting extends BaseModel
{
    protected string $table = 'system_settings';
    protected string $primaryKey = 'setting_key';

    public function get(string $key, ?string $default = null): ?string
    {
        $result = $this->db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = ?",
            [$key]
        );
        return $result['setting_value'] ?? $default;
    }

    public function set(string $key, string $value): void
    {
        $exists = $this->db->fetch(
            "SELECT setting_key FROM system_settings WHERE setting_key = ?",
            [$key]
        );

        if ($exists) {
            $this->db->execute(
                "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?",
                [$value, $key]
            );
        } else {
            $this->db->execute(
                "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)",
                [$key, $value]
            );
        }
    }

    public function getBankInfo(): array
    {
        $keys = [
            'bank_name', 'bank_account_number', 'bank_account_owner',
            'bank_account_type', 'bank_email', 'bank_id_number'
        ];
        
        $info = [];
        foreach ($keys as $key) {
            $info[$key] = $this->get($key, '');
        }
        return $info;
    }

    public function getAll(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM system_settings ORDER BY setting_key");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }
}
