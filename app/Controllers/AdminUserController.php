<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Validator;
use App\Models\User;

class AdminUserController
{
    private User $userModel;

    public function __construct()
    {
        Auth::require();
        $this->userModel = new User();
    }

    public function index()
    {
        Auth::requirePermission('users_read');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $paginationData = $this->userModel->paginateWithRoles($page, $perPage);

        View::render('admin.users.index', [
            'title'        => 'Gestión de Usuarios',
            'users'        => $paginationData['data'],
            'currentPage'  => $paginationData['current_page'],
            'totalPages'   => $paginationData['total_pages'],
            'totalRecords' => $paginationData['total_records'],
            'perPage'      => $paginationData['per_page']
        ], 'admin');
    }

    public function create()
    {
        Auth::requirePermission('users_create');
        $roles = $this->userModel->getRoles();
        View::render('admin.users.create', [
            'title' => 'Nuevo Usuario',
            'roles' => $roles
        ], 'admin');
    }

    public function store()
    {
        Auth::requirePermission('users_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/users');
        }

        try {
            $email = trim($_POST['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Session::flash('error', 'El formato del correo electrónico no es válido.');
                View::redirect('/admin/users/create');
            }
            
            // Check if email exists
            $exists = $this->userModel->findOneWhere('email = ?', [$email]);
            if ($exists) {
                Session::flash('error', 'El correo electrónico ya está registrado.');
                View::redirect('/admin/users/create');
            }

            // Auto-generate temporary password and create user
            $roleName = 'receptionist'; // default role, will be updated if role_id is provided
            if (!empty($_POST['role_id'])) {
                $db = \App\Helpers\Database::getInstance();
                $roleRow = $db->fetch("SELECT name FROM roles WHERE id = ? AND LOWER(name) NOT IN ('doctor', 'medico', 'patient', 'paciente')", [(int)$_POST['role_id']]);
                if ($roleRow) {
                    $roleName = $roleRow['name'];
                }
            }

            $userData = $this->userModel->createWithTempPassword($email, $roleName);
            $userId = $userData['user_id'];
            $tempPassword = $userData['temp_password'];

            // Generate Password Reset Token (válido por 24 horas)
            $passwordResetModel = new \App\Models\PasswordReset();
            $token = $passwordResetModel->createToken($userId, 24);
            $appUrl = \App\Helpers\Env::getAppUrl();
            $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);

            // Send Email
            \App\Helpers\Mailer::queue(
                $email,
                'Cuenta Creada - Credenciales de Acceso',
                \App\Helpers\Mailer::buildUserCreationEmail([
                    'name' => 'Usuario del Sistema',
                    'email' => $email,
                    'tempPassword' => $tempPassword,
                    'resetLink' => $resetLink
                ])
            );

            Session::flash('success', 'Usuario creado exitosamente.');
            View::redirect('/admin/users');

        } catch (\Exception $e) {
            Session::flash('error', 'Error al crear usuario.');
            View::redirect('/admin/users/create');
        }
    }

    public function edit(string $id)
    {
        Auth::requirePermission('users_update');
        $user = $this->userModel->findById((int)$id);
        if (!$user) {
            Session::flash('error', 'Usuario no encontrado.');
            View::redirect('/admin/users');
        }

        $roles = $this->userModel->getRoles();
        $userRole = $this->userModel->getUserRole((int)$id);
        
        $currentRoleId = null;
        if ($userRole) {
            foreach ($roles as $role) {
                if ($role['name'] === $userRole) {
                    $currentRoleId = $role['id'];
                    break;
                }
            }
        }

        View::render('admin.users.edit', [
            'title' => 'Editar Usuario',
            'user' => $user,
            'roles' => $roles,
            'currentRoleId' => $currentRoleId,
            'currentRoleName' => $userRole
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('users_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/users');
        }

        try {
            $user = $this->userModel->findById((int)$id);
            if (!$user) {
                Session::flash('error', 'Usuario no encontrado.');
                View::redirect('/admin/users');
            }

            $email = trim($_POST['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Session::flash('error', 'El formato del correo electrónico no es válido.');
                View::redirect('/admin/users/edit/' . $id);
            }
            
            // Check if email exists for other user
            if ($email !== $user['email']) {
                $exists = $this->userModel->findOneWhere('email = ? AND id != ?', [$email, (int)$id]);
                if ($exists) {
                    Session::flash('error', 'El correo electrónico ya está registrado por otro usuario.');
                    View::redirect('/admin/users/edit/' . $id);
                }
            }

            $updateData = [
                'email' => $email
            ];

            if (!empty($_POST['password'])) {
                if (!Validator::isStrongPassword($_POST['password'])) {
                    Session::flash('error', 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.');
                    View::redirect('/admin/users/edit/' . $id);
                }
                $updateData['password'] = Auth::hashPassword($_POST['password']);
            }

            $this->userModel->update((int)$id, $updateData);

            if (!empty($_POST['role_id'])) {
                $db = \App\Helpers\Database::getInstance();
                $roleRow = $db->fetch("SELECT id FROM roles WHERE id = ? AND LOWER(name) NOT IN ('doctor', 'medico', 'patient', 'paciente')", [(int)$_POST['role_id']]);
                if ($roleRow) {
                    $this->userModel->assignRole((int)$id, (int)$roleRow['id']);
                }
            }

            Session::flash('success', 'Usuario actualizado exitosamente.');
            View::redirect('/admin/users');

        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar usuario.');
            View::redirect('/admin/users/edit/' . $id);
        }
    }

    public function delete(string $id)
    {
        Auth::requirePermission('users_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/users');
        }

        if ((int)$id === Session::userId()) {
            Session::flash('error', 'No puede eliminar su propio usuario.');
            View::redirect('/admin/users');
        }

        try {
            $user = $this->userModel->findById((int)$id);
            if (!$user) {
                Session::flash('error', 'Usuario no encontrado.');
                View::redirect('/admin/users');
            }

            $this->userModel->delete((int)$id);
            Session::flash('success', 'Usuario eliminado correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'No se puede eliminar el usuario porque tiene registros asociados.');
        }

        View::redirect('/admin/users');
    }

    public function toggleStatus(string $id)
    {
        Auth::requirePermission('users_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/users');
        }

        if ((int)$id === Session::userId()) {
            Session::flash('error', 'No puede desactivar su propio usuario.');
            View::redirect('/admin/users');
        }

        try {
            $user = $this->userModel->findById((int)$id);
            if ($user) {
                $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
                $this->userModel->update((int)$id, ['status' => $newStatus]);
                Session::flash('success', "Usuario " . ($newStatus === 'active' ? 'activado' : 'desactivado') . ".");
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar usuario.');
        }

        View::redirect('/admin/users');
    }
}
