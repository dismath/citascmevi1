<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;

class StaffProfileController
{
    public function __construct()
    {
        Auth::require();
    }

    private function getStaffInfo(): ?array
    {
        $db = \App\Helpers\Database::getInstance();
        $userId    = Session::get('user_id');
        $userEmail = Session::get('user_email');

        // Try to get staff record linked to this user
        $staff = $db->fetch(
            "SELECT s.*, u.email, u.id as user_id, u.username, u.nombre_usuario,
                    COALESCE(u.`2fa_enabled`, 0) as two_factor_enabled
             FROM staff s
             JOIN users u ON s.user_id = u.id
             WHERE u.id = ? OR u.email = ?
             LIMIT 1",
            [$userId, $userEmail]
        );

        // If no staff record exists, get user info only
        if (!$staff) {
            $user = $db->fetch(
                "SELECT u.id as user_id, u.email, u.username, u.nombre_usuario,
                        COALESCE(u.`2fa_enabled`, 0) as two_factor_enabled,
                        NULL as id, NULL as phone, NULL as address,
                        NULL as position, NULL as department
                 FROM users u
                 WHERE u.id = ? OR u.email = ?
                 LIMIT 1",
                [$userId, $userEmail]
            );
            if ($user) {
                $user['name'] = $user['nombre_usuario'] ?? $user['email'];
            }
            return $user ?: null;
        }

        return $staff;
    }

    public function edit()
    {
        $staff = $this->getStaffInfo();
        if (!$staff) {
            Session::flash('error', 'No se encontró la información del usuario.');
            View::redirect('/admin/dashboard');
        }

        View::render('admin.profile.edit', [
            'title'     => 'Mi Perfil',
            'staff'     => $staff,
            'csrfToken' => Session::generateCsrf()
        ], 'admin');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/admin/profile');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/admin/profile');
        }

        $staff = $this->getStaffInfo();
        if (!$staff) {
            View::redirect('/admin/dashboard');
        }

        $db     = \App\Helpers\Database::getInstance();
        $userId = $staff['user_id'];

        // Data
        $name             = trim($_POST['name'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $address          = trim($_POST['address'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirmPassword  = $_POST['confirm_password'] ?? '';
        $twoFactorEnabled = !empty($_POST['two_factor_enabled']) ? 1 : 0;

        // Validation
        if (empty($name) || empty($email)) {
            Session::flash('error', 'El nombre y el correo son obligatorios.');
            View::redirect('/admin/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El formato del correo electrónico no es válido.');
            View::redirect('/admin/profile');
        }

        // Email uniqueness
        $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $userId]);
        if ($existing) {
            Session::flash('error', 'El correo electrónico ya está registrado por otro usuario.');
            View::redirect('/admin/profile');
        }

        if (!empty($password) && $password !== $confirmPassword) {
            Session::flash('error', 'Las contraseñas no coinciden.');
            View::redirect('/admin/profile');
        }

        if (!empty($password) && strlen($password) < 8) {
            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
            View::redirect('/admin/profile');
        }

        try {
            $db->beginTransaction();

            // Update user record (email, name, 2FA)
            $db->execute(
                "UPDATE users SET email = ?, nombre_usuario = ?, `2fa_enabled` = ? WHERE id = ?",
                [$email, $name, $twoFactorEnabled, $userId]
            );

            // Update staff record if it exists
            if (!empty($staff['id'])) {
                $db->execute(
                    "UPDATE staff SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?",
                    [$name, $email, $phone, $address, $staff['id']]
                );
            }

            // Update password if provided
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->execute(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [$hashedPassword, $userId]
                );
            }

            $db->commit();

            // Update session if email changed
            if ($email !== $staff['email']) {
                Session::set('user_email', $email);
            }
            if ($name !== ($staff['nombre_usuario'] ?? '')) {
                Session::set('user_name', $name);
            }

            Session::flash('success', 'Perfil actualizado exitosamente.');
        } catch (\Exception $e) {
            $db->rollback();
            Session::flash('error', 'Error al actualizar el perfil: ' . $e->getMessage());
        }

        View::redirect('/admin/profile');
    }
}
