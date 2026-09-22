<?php
namespace App\Helpers;

class Auth
{
    /**
     * Require authentication - redirect to login if not authenticated
     */
    public static function require(): void
    {
        if (!Session::isLoggedIn()) {
            Session::flash('error', 'Debe iniciar sesión para acceder.');
            View::redirect('/login');
        }
    }

    /**
     * Require admin role
     */
    public static function requireAdmin(): void
    {
        self::require();
        if (Session::userRole() !== 'admin') {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado</h1>';
            exit;
        }
    }

    /**
     * Require specific roles
     */
    public static function requireRole(array $roles): void
    {
        self::require();
        if (!in_array(Session::userRole(), $roles, true)) {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado</h1>';
            exit;
        }
    }

    /**
     * Check if current user has a specific role
     */
    public static function hasRole(string $role): bool
    {
        return Session::userRole() === $role;
    }

    /**
     * Verify password hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if user has specific permission (alias for hasPermission)
     */
    public static function can(string $permission): bool
    {
        return self::hasPermission($permission);
    }

    /**
     * Check if user has specific permission
     */
    public static function hasPermission(string $permission): bool
    {
        // Admin role has all permissions
        if (self::hasRole('admin')) {
            return true;
        }

        $perms = Session::get('user_permissions');
        if ($perms === null && Session::isLoggedIn()) {
            $perms = self::refreshPermissions();
        }

        return is_array($perms) && in_array($permission, $perms, true);
    }

    /**
     * Check if user has at least one of the given permissions
     */
    public static function hasAnyPermission(array $permissions): bool
    {
        if (self::hasRole('admin')) {
            return true;
        }

        foreach ($permissions as $perm) {
            if (self::hasPermission($perm)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Require a specific permission
     */
    public static function requirePermission(string $permission): void
    {
        self::require();
        if (!self::hasPermission($permission)) {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado</h1>';
            echo '<p>No dispone de los privilegios necesarios para realizar esta acción (Permiso requerido: ' . htmlspecialchars($permission) . ').</p>';
            exit;
        }
    }

    /**
     * Require at least one of the given permissions
     */
    public static function requireAnyPermission(array $permissions): void
    {
        self::require();
        if (!self::hasAnyPermission($permissions)) {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado</h1>';
            echo '<p>No dispone de los privilegios necesarios para acceder a esta sección.</p>';
            exit;
        }
    }

    /**
     * Recarga los permisos del usuario activo desde la base de datos a la sesión
     */
    public static function refreshPermissions(): array
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            return [];
        }

        try {
            $userModel = new \App\Models\User();
            $perms = $userModel->getPermissions((int)$userId);
            $permNames = !empty($perms) ? array_column($perms, 'name') : [];
            Session::set('user_permissions', $permNames);
            return $permNames;
        } catch (\Throwable $e) {
            error_log('[Auth::refreshPermissions] Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Hash a password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}

