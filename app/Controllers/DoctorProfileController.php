<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;

class DoctorProfileController
{
    public function __construct()
    {
        Auth::requireRole(['doctor', 'admin']);
    }

    private function getDoctorInfo(): ?array
    {
        $db        = \App\Helpers\Database::getInstance();
        $userId    = Session::get('user_id');
        $userEmail = Session::get('user_email');

        // Primary: look up by user_id (always available, works even if email is NULL)
        if ($userId) {
            $doctor = $db->fetch(
                "SELECT d.*, u.email, u.id as user_id, u.username, u.nombre_usuario, COALESCE(u.`2fa_enabled`, 0) as two_factor_enabled
                 FROM doctors d
                 JOIN users u ON d.user_id = u.id
                 WHERE u.id = ?
                 LIMIT 1",
                [(int)$userId]
            );
            if ($doctor) {
                return $doctor;
            }
        }

        // Fallback: look up by email (backwards compat)
        if ($userEmail) {
            $doctor = $db->fetch(
                "SELECT d.*, u.email, u.id as user_id, u.username, u.nombre_usuario, COALESCE(u.`2fa_enabled`, 0) as two_factor_enabled
                 FROM doctors d 
                 JOIN users u ON d.user_id = u.id 
                 WHERE u.email = ?
                 LIMIT 1",
                [$userEmail]
            );
            if ($doctor) {
                return $doctor;
            }
        }

        return null;
    }

    public function edit()
    {
        $doctor = $this->getDoctorInfo();
        if (!$doctor) {
            Session::flash('error', 'No se encontró el perfil del médico.');
            View::redirect('/doctor/dashboard');
        }

        View::render('doctor.profile.edit', [
            'title' => 'Mi Perfil',
            'doctor' => $doctor,
            'csrfToken' => Session::generateCsrf()
        ], 'admin');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/doctor/profile');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/doctor/profile');
        }

        $doctor = $this->getDoctorInfo();
        if (!$doctor) {
            View::redirect('/doctor/dashboard');
        }

        $db = \App\Helpers\Database::getInstance();
        $userId = $doctor['user_id'];
        
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
            View::redirect('/doctor/profile');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El formato del correo electrónico no es válido.');
            View::redirect('/doctor/profile');
        }

        // Email uniqueness
        $existing = $db->fetch("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1", [$email, $userId]);
        if ($existing) {
            Session::flash('error', 'El correo electrónico ya está registrado por otro usuario.');
            View::redirect('/doctor/profile');
        }

        if (!empty($password) && $password !== $confirmPassword) {
            Session::flash('error', 'Las contraseñas no coinciden.');
            View::redirect('/doctor/profile');
        }

        if (!empty($password) && strlen($password) < 8) {
            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres.');
            View::redirect('/doctor/profile');
        }

        try {
            $db->beginTransaction();

            // Update doctor data
            $db->execute(
                "UPDATE doctors SET name = ?, phone = ?, address = ? WHERE id = ?",
                [$name, $phone, $address, $doctor['id']]
            );

            // Update user email, name and 2FA status
            $db->execute(
                "UPDATE users SET email = ?, nombre_usuario = ?, `2fa_enabled` = ? WHERE id = ?",
                [$email, $name, $twoFactorEnabled, $userId]
            );

            // Update password if provided
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->execute(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [$hashedPassword, $userId]
                );
            }

            $db->commit();
            
            // Update session if email or name changed
            if ($email !== $doctor['email']) {
                Session::set('user_email', $email);
            }
            if ($name !== ($doctor['nombre_usuario'] ?? '')) {
                Session::set('user_name', $name);
            }

            Session::flash('success', 'Perfil actualizado exitosamente.');
        } catch (\Exception $e) {
            $db->rollback();
            Session::flash('error', 'Error al actualizar el perfil: ' . $e->getMessage());
        }

        View::redirect('/doctor/profile');
    }
}
