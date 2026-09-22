<?php
namespace App\Models;

/**
 * User — Modelo de autenticación unificada.
 *
 * Todas las autenticaciones (admin, recepcionista, doctor, paciente) se resuelven
 * con un único lookup en esta tabla. Doctors y patients tienen su user_id vinculado
 * aquí; el rol se determina por la tabla user_roles.
 */
class User extends BaseModel
{
    protected string $table = 'users';

    // ─────────────────────────────────────────────────────────────────────────
    // LOOKUP DE AUTENTICACIÓN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Busca un usuario por email solo en la tabla `users` (admins/recepcionistas).
     * Mantiene compatibilidad con el código existente.
     */
    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch(
            "SELECT u.*, GROUP_CONCAT(r.name) AS roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE u.email = ?
               AND u.status = 'active'
               AND (u.revoked_at IS NULL)
               AND (u.expires_at IS NULL OR u.expires_at > NOW())
             GROUP BY u.id",
            [$email]
        );
    }

    /**
     * LOGIN UNIFICADO — Busca en `users` independientemente del rol.
     *
     * Retorna el usuario con su rol primario si:
     *  - El email existe en la tabla users
     *  - El status es 'active'
     *  - La cuenta no está revocada (revoked_at IS NULL)
     *  - La cuenta no está expirada (expires_at IS NULL OR expires_at > NOW())
     *
     * Esto reemplaza el triple-lookup: users → doctors → patients.
     *
     * @return array|false  Incluye 'roles' (CSV), 'primary_role' y datos del usuario.
     */
    public function findUnifiedByLogin(string $login): array|false
    {
        $user = $this->db->fetch(
            "SELECT
                u.*,
                GROUP_CONCAT(r.name ORDER BY r.id ASC SEPARATOR ',') AS roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             WHERE (u.email = ? OR u.username = ?)
               AND u.status = 'active'
               AND (u.revoked_at IS NULL)
               AND (u.expires_at IS NULL OR u.expires_at > NOW())
             GROUP BY u.id
             LIMIT 1",
            [$login, $login]
        );

        if (!$user) {
            return false;
        }

        // Determinar rol primario para redirección y lógica de negocio
        $rolesList   = array_filter(array_map('trim', explode(',', $user['roles'] ?? '')));
        $primaryRole = $rolesList[0] ?? 'user';

        // Mapear rol a user_type para compatibilidad con código existente
        $user['primary_role'] = $primaryRole;
        $user['user_type']    = match(true) {
            in_array('doctor',        $rolesList) => 'doctor',
            in_array('patient',       $rolesList) => 'patient',
            in_array('admin',         $rolesList) => 'admin',
            in_array('receptionist',  $rolesList) => 'admin',
            default                               => 'admin',
        };

        return $user;
    }

    /**
     * Verifica que una cuenta no esté revocada ni expirada.
     * Útil en middlewares y guards.
     */
    public function isAccountValid(int $userId): bool
    {
        $result = $this->db->fetch(
            "SELECT id FROM users
             WHERE id = ?
               AND status = 'active'
               AND (revoked_at IS NULL)
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1",
            [$userId]
        );
        return (bool)$result;
    }

    /**
     * Asegura que las columnas de bloqueo temporal existan en la tabla users.
     */
    public function ensureLockoutColumns(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            $this->db->execute("
                ALTER TABLE `users`
                  ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0,
                  ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL DEFAULT NULL;
            ");
            $checked = true;
        } catch (\Throwable $e) {
            $checked = true;
        }
    }

    /**
     * Verifica si una cuenta de usuario está temporalmente bloqueada.
     */
    public function isAccountLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }

        try {
            $tz = new \DateTimeZone('America/Guayaquil');
            $lockedUntil = new \DateTime($user['locked_until'], $tz);
            $now = new \DateTime('now', $tz);
            return $lockedUntil > $now;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Incrementa los intentos fallidos de login consecutivos.
     * Si alcanza 5 fallos, bloquea la cuenta por 15 minutos y dispara una alerta.
     */
    public function incrementFailedAttempts(int $userId): int
    {
        $this->ensureLockoutColumns();
        try {
            $this->db->execute(
                "UPDATE users 
                 SET failed_login_attempts = failed_login_attempts + 1,
                     locked_until = IF(failed_login_attempts + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until)
                 WHERE id = ?",
                [$userId]
            );

            $row = $this->db->fetch("SELECT email, failed_login_attempts, locked_until FROM users WHERE id = ?", [$userId]);
            $attempts = (int)($row['failed_login_attempts'] ?? 0);

            if ($attempts >= 5) {
                SecurityAudit::log(
                    $userId,
                    SecurityAudit::EVENTO_CUENTA_BLOQUEADA,
                    SecurityAudit::RESULTADO_BLOQUEADO,
                    [
                        'email' => $row['email'] ?? '',
                        'attempts' => $attempts,
                        'locked_until' => $row['locked_until'] ?? ''
                    ]
                );

                SecurityAudit::logCriticalAlert(
                    "Cuenta bloqueada por 15 minutos tras 5 intentos fallidos consecutivos",
                    ['user_id' => $userId, 'email' => $row['email'] ?? '', 'attempts' => $attempts]
                );
            }

            return $attempts;
        } catch (\Throwable $e) {
            error_log('[User] Error incrementando failed attempts: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Limpia los intentos fallidos tras un login exitoso.
     */
    public function resetFailedAttempts(int $userId): void
    {
        $this->ensureLockoutColumns();
        try {
            $this->db->execute(
                "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
                [$userId]
            );
        } catch (\Throwable $e) {}
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GESTIÓN DE ACTIVIDAD Y SESIONES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Actualiza la marca de última actividad del usuario.
     * Se llama en cada login exitoso.
     */
    public function updateLastActivity(int $userId): void
    {
        try {
            $this->db->execute(
                "UPDATE users SET last_activity = NOW() WHERE id = ?",
                [$userId]
            );
        } catch (\Throwable $e) {
            error_log('[User] Error actualizando last_activity: ' . $e->getMessage());
        }
    }

    /**
     * Revoca TODAS las sesiones activas del usuario en `sesiones_usuario`.
     * Úselo cuando el usuario reporta robo de dispositivo o cuenta comprometida.
     */
    public function revokeAllSessions(int $userId): int
    {
        return $this->db->execute(
            "UPDATE sesiones_usuario
             SET revoked_at = NOW()
             WHERE user_id = ?
               AND revoked_at IS NULL",
            [$userId]
        );
    }

    /**
     * Registra una nueva sesión web en `sesiones_usuario`.
     * El token real NUNCA se guarda — solo su hash SHA-256.
     *
     * @param int    $userId
     * @param string $sessionId  ID de sesión PHP (session_id())
     * @param array  $meta       Datos opcionales: device_name, ip, user_agent
     * @return string            El hash almacenado (para validación posterior)
     */
    public function createSession(int $userId, string $sessionId, array $meta = []): string
    {
        $tokenHash  = hash('sha256', $sessionId);
        $expiresAt  = date('Y-m-d H:i:s', time() + 28800); // 8 horas por defecto
        $ip         = $meta['ip']          ?? ($_SERVER['REMOTE_ADDR'] ?? null);
        $userAgent  = $meta['user_agent']  ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $deviceName = $meta['device_name'] ?? $this->guessDeviceName($userAgent ?? '');

        $this->db->execute(
            "INSERT INTO sesiones_usuario
                (user_id, session_token_hash, ip_address, user_agent, device_name, created_at, expires_at, last_activity)
             VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW())
             ON DUPLICATE KEY UPDATE
                last_activity = NOW(),
                ip_address    = VALUES(ip_address),
                user_agent    = VALUES(user_agent)",
            [$userId, $tokenHash, $ip, substr($userAgent ?? '', 0, 500), $deviceName, $expiresAt]
        );

        return $tokenHash;
    }

    /**
     * Obtiene las sesiones activas de un usuario.
     * Útil para mostrar "sesiones abiertas" en el panel de cuenta.
     */
    public function getActiveSessions(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT id, ip_address, device_name, user_agent, created_at, last_activity, expires_at
             FROM sesiones_usuario
             WHERE user_id   = ?
               AND revoked_at IS NULL
               AND expires_at > NOW()
             ORDER BY last_activity DESC",
            [$userId]
        );
    }

    /**
     * Revoca una sesión específica por su ID.
     */
    public function revokeSession(int $sessionId, int $userId): int
    {
        return $this->db->execute(
            "UPDATE sesiones_usuario
             SET revoked_at = NOW()
             WHERE id = ? AND user_id = ?",
            [$sessionId, $userId]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GESTIÓN DE USUARIOS (métodos existentes — sin cambios funcionales)
    // ─────────────────────────────────────────────────────────────────────────

    public function getAllWithRoles(): array
    {
        return $this->db->fetchAll(
            "SELECT u.*, GROUP_CONCAT(r.name) AS roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             GROUP BY u.id
             ORDER BY u.created_at DESC"
        );
    }

    public function paginateWithRoles(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = '';
        $params = [];

        if (!empty($search)) {
            $where = "WHERE u.email LIKE ? OR u.nombre_usuario LIKE ?";
            $term = '%' . $search . '%';
            $params = [$term, $term];
        }

        $countSql = "SELECT COUNT(*) as total FROM users u {$where}";
        $totalRow = $this->db->fetch($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $sql = "SELECT u.*, GROUP_CONCAT(r.name) AS roles
             FROM users u
             LEFT JOIN user_roles ur ON u.id = ur.user_id
             LEFT JOIN roles r ON ur.role_id = r.id
             {$where}
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}";

        $data = $this->db->fetchAll($sql, $params);

        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $total,
            'total_pages' => $totalPages
        ];
    }

    public function createWithRole(string $email, string $password, string $role, ?string $nombreUsuario = null): int
    {
        $this->db->beginTransaction();
        try {
            $userId = $this->create([
                'email'          => $email,
                'nombre_usuario' => $nombreUsuario,
                'password'       => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'status'         => 'active',
            ]);

            $roleRow = $this->db->fetch("SELECT id FROM roles WHERE name = ? OR id = ?", [$role, $role]);
            if ($roleRow) {
                $this->db->execute(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$userId, $roleRow['id']]
                );
            }

            $this->db->commit();
            return $userId;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function createWithTempPassword(?string $email, string $role, ?string $nombreUsuario = null): array
    {
        $tempPassword = bin2hex(random_bytes(8)); // 16 caracteres temporales
        
        $baseName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombreUsuario ?? ($role === 'doctor' ? 'medico' : 'paciente')));
        if (empty($baseName)) $baseName = 'usuario';
        
        $username = $baseName . '_' . rand(1000, 9999);
        while ($this->db->fetch("SELECT id FROM users WHERE username = ?", [$username])) {
            $username = $baseName . '_' . rand(1000, 9999);
        }

        $this->db->beginTransaction();
        try {
            $userId = $this->create([
                'email'          => empty($email) ? null : $email,
                'username'       => $username,
                'nombre_usuario' => $nombreUsuario,
                'password'       => password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                'status'         => 'active',
            ]);

            $roleRow = $this->db->fetch("SELECT id FROM roles WHERE name = ? OR id = ?", [$role, $role]);
            if ($roleRow) {
                $this->db->execute(
                    "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
                    [$userId, $roleRow['id']]
                );
            }

            $this->db->commit();

            return [
                'user_id'       => $userId,
                'email'         => $email,
                'username'      => $username,
                'temp_password' => $tempPassword,
            ];
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function getUserRole(int $userId): ?string
    {
        $result = $this->db->fetch(
            "SELECT r.name FROM user_roles ur
             JOIN roles r ON ur.role_id = r.id
             WHERE ur.user_id = ?
             LIMIT 1",
            [$userId]
        );
        return $result['name'] ?? null;
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $this->db->execute("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
        $this->db->execute(
            "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)",
            [$userId, $roleId]
        );
    }

    public function getRoles(): array
    {
        return $this->db->fetchAll("SELECT * FROM roles ORDER BY name ASC");
    }

    /**
     * Genera una nueva contraseña para un usuario, la guarda con hash bcrypt,
     * desbloquea la cuenta si estuviera bloqueada y retorna la contraseña en texto plano y username.
     */
    public function generateAndSetNewPassword(int $userId): array
    {
        $charsAlpha = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $charsDigits = '23456789';
        $charsSymbols = '!@#$%';

        $password = '';
        for ($i = 0; $i < 6; $i++) {
            $password .= $charsAlpha[random_int(0, strlen($charsAlpha) - 1)];
        }
        for ($i = 0; $i < 3; $i++) {
            $password .= $charsDigits[random_int(0, strlen($charsDigits) - 1)];
        }
        $password .= $charsSymbols[random_int(0, strlen($charsSymbols) - 1)];
        $password = str_shuffle($password);

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->execute(
            "UPDATE users 
             SET password = ?, failed_login_attempts = 0, locked_until = NULL 
             WHERE id = ?",
            [$hash, $userId]
        );

        $user = $this->findById($userId);
        if ($user && empty($user['username'])) {
            $baseName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $user['nombre_usuario'] ?? 'usuario'));
            if (empty($baseName)) $baseName = 'user';
            $username = $baseName . '_' . rand(1000, 9999);
            while ($this->db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId])) {
                $username = $baseName . '_' . rand(1000, 9999);
            }
            $this->db->execute("UPDATE users SET username = ? WHERE id = ?", [$username, $userId]);
            $user['username'] = $username;
        }

        return [
            'user_id'      => $userId,
            'username'     => $user['username'] ?? '',
            'email'        => $user['email'] ?? '',
            'new_password' => $password
        ];
    }

    /**
     * Obtiene o crea credenciales para una entidad (doctor o patient)
     */
    public function getOrCreateCredentialsForEntity(string $type, int $id): array
    {
        $table = ($type === 'doctor') ? 'doctors' : 'patients';
        $role  = ($type === 'doctor') ? 'doctor' : 'patient';

        $entity = $this->db->fetch(
            "SELECT e.*, u.id as user_id, u.username, u.email as user_email, u.status as user_status
             FROM {$table} e
             LEFT JOIN users u ON e.user_id = u.id
             WHERE e.id = ?
             LIMIT 1",
            [$id]
        );

        if (!$entity) {
            throw new \InvalidArgumentException("Registro no encontrado.");
        }

        if (empty($entity['user_id'])) {
            $name = $entity['name'] ?? 'Usuario';
            $email = !empty($entity['email']) ? $entity['email'] : null;
            if ($email && $this->findOneWhere('email = ?', [$email])) {
                $email = null;
            }

            $userData = $this->createWithTempPassword($email, $role, $name);
            $this->db->execute("UPDATE {$table} SET user_id = ? WHERE id = ?", [$userData['user_id'], $id]);

            return [
                'entity_id'    => $id,
                'entity_type'  => $type,
                'name'         => $name,
                'user_id'      => $userData['user_id'],
                'username'     => $userData['username'],
                'email'        => $userData['email'] ?? ($entity['email'] ?? ''),
                'has_password' => true,
                'is_new'       => true,
                'temp_password'=> $userData['temp_password']
            ];
        }

        $userId = (int)$entity['user_id'];
        $username = $entity['username'] ?? '';
        if (empty($username)) {
            $baseName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $entity['name'] ?? 'usuario'));
            if (empty($baseName)) $baseName = 'user';
            $username = $baseName . '_' . rand(1000, 9999);
            while ($this->db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $userId])) {
                $username = $baseName . '_' . rand(1000, 9999);
            }
            $this->db->execute("UPDATE users SET username = ? WHERE id = ?", [$username, $userId]);
        }

        return [
            'entity_id'    => $id,
            'entity_type'  => $type,
            'name'         => $entity['name'] ?? '',
            'user_id'      => $userId,
            'username'     => $username,
            'email'        => $entity['user_email'] ?? $entity['email'] ?? '',
            'has_password' => true,
            'is_new'       => false,
            'temp_password'=> null
        ];
    }

    public function ensureUserPermissionsTable(): void
    {
        static $checked = false;
        if ($checked) return;

        try {
            $this->db->execute("
                CREATE TABLE IF NOT EXISTS `user_permissions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` INT NOT NULL,
                    `permission_id` INT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_user_permission` (`user_id`, `permission_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $checked = true;
        } catch (\Throwable $e) {
            $checked = true;
        }
    }

    public function getPermissions(int $userId): array
    {
        $this->ensureUserPermissionsTable();
        return $this->db->fetchAll(
            "SELECT DISTINCT p.name, p.id
             FROM permissions p
             WHERE p.id IN (
                 SELECT rp.permission_id 
                 FROM role_permissions rp
                 JOIN user_roles ur ON rp.role_id = ur.role_id
                 WHERE ur.user_id = ?
                 UNION
                 SELECT up.permission_id 
                 FROM user_permissions up
                 WHERE up.user_id = ?
             )",
            [$userId, $userId]
        );
    }

    public function getDirectPermissionIds(int $userId): array
    {
        $this->ensureUserPermissionsTable();
        $rows = $this->db->fetchAll(
            "SELECT permission_id FROM user_permissions WHERE user_id = ?",
            [$userId]
        );
        return array_map('intval', array_column($rows, 'permission_id'));
    }

    public function syncUserPermissions(int $userId, array $permissionIds): void
    {
        $this->ensureUserPermissionsTable();
        $this->db->beginTransaction();
        try {
            $this->db->execute("DELETE FROM user_permissions WHERE user_id = ?", [$userId]);
            foreach ($permissionIds as $permId) {
                $pId = (int)$permId;
                if ($pId > 0) {
                    $this->db->execute(
                        "INSERT IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)",
                        [$userId, $pId]
                    );
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILIDADES PRIVADAS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Genera un nombre de dispositivo legible a partir del User-Agent.
     */
    private function guessDeviceName(string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Dispositivo desconocido';
        }
        if (stripos($userAgent, 'iPhone') !== false) {
            return 'iPhone';
        }
        if (stripos($userAgent, 'iPad') !== false) {
            return 'iPad';
        }
        if (stripos($userAgent, 'Android') !== false) {
            return stripos($userAgent, 'Mobile') !== false ? 'Android (móvil)' : 'Android (tablet)';
        }
        if (stripos($userAgent, 'Windows') !== false) {
            return 'Windows PC';
        }
        if (stripos($userAgent, 'Mac') !== false) {
            return 'Mac';
        }
        if (stripos($userAgent, 'Linux') !== false) {
            return 'Linux';
        }
        return 'Navegador web';
    }
}
