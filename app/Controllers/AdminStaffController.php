<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Validator;
use App\Helpers\HashId;
use App\Helpers\Database;
use App\Models\Staff;
use App\Models\User;
use App\Models\PasswordReset;
use App\Helpers\Mailer;

class AdminStaffController
{
    private Staff $staffModel;
    private User $userModel;

    public function __construct()
    {
        Auth::require();
        $this->staffModel = new Staff();
        $this->userModel = new User();
    }

    public function index()
    {
        Auth::requirePermission('staff_read');
        $search = trim($_GET['search'] ?? '');
        $department = trim($_GET['department'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $paginationData = $this->staffModel->paginateWithUser($page, $perPage, $search, $department, $status);
        $departments = Staff::getDepartments();

        View::render('admin.staff.index', [
            'title'        => 'Personal Administrativo y Operativo',
            'staffList'    => $paginationData['data'],
            'departments'  => $departments,
            'search'       => $search,
            'department'   => $department,
            'status'       => $status,
            'currentPage'  => $paginationData['current_page'],
            'totalPages'   => $paginationData['total_pages'],
            'totalRecords' => $paginationData['total_records'],
            'perPage'      => $paginationData['per_page']
        ], 'admin');
    }

    /**
     * Obtiene los roles asignables para colaboradores del sistema,
     * omitiendo perfiles médicos y pacientes, e incluyendo conteo de permisos.
     */
    private function getStaffAssignableRoles(): array
    {
        $db = Database::getInstance();
        $roles = $db->fetchAll("
            SELECT r.*, 
                   COUNT(rp.permission_id) as permissions_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            WHERE LOWER(r.name) NOT IN ('doctor', 'medico', 'patient', 'paciente')
            GROUP BY r.id
            ORDER BY CASE WHEN r.name = 'admin' THEN 1 WHEN r.name = 'receptionist' THEN 2 ELSE 3 END, r.name ASC
        ");

        $totalPermsRow = $db->fetch("SELECT COUNT(*) as total FROM permissions");
        $totalPerms = (int)($totalPermsRow['total'] ?? 0);
        foreach ($roles as &$r) {
            if ($r['name'] === 'admin') {
                $r['permissions_count'] = $totalPerms;
            }
        }
        unset($r);

        return $roles;
    }

    public function create()
    {
        Auth::requirePermission('staff_create');
        $departments = Staff::getDepartments();
        $positions = Staff::getPositions();
        $roles = $this->getStaffAssignableRoles();

        View::render('admin.staff.create', [
            'title'       => 'Nuevo Colaborador',
            'departments' => $departments,
            'positions'   => $positions,
            'roles'       => $roles
        ], 'admin');
    }

    public function store()
    {
        Auth::requirePermission('staff_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/staff');
        }

        try {
            $name = Validator::capitalizeWords(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Session::flash('error', 'El nombre completo es obligatorio.');
                View::redirect('/admin/staff/create');
            }

            if (!Validator::isValidName($name)) {
                Session::flash('error', 'El nombre solo debe contener letras y números.');
                View::redirect('/admin/staff/create');
            }

            $idNumber = preg_replace('/[^0-9]/', '', trim($_POST['id_number'] ?? ''));
            if (empty($idNumber)) {
                Session::flash('error', 'El documento de identificación es obligatorio.');
                View::redirect('/admin/staff/create');
            }

            if (!Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'El número de Cédula o RUC ingresado no es válido.');
                View::redirect('/admin/staff/create');
            }

            // Unicidad de cédula
            $existingId = $this->staffModel->findOneWhere('id_number = ?', [$idNumber]);
            if ($existingId) {
                Session::flash('error', 'Ya existe un colaborador registrado con esa identificación.');
                View::redirect('/admin/staff/create');
            }

            $position = Validator::capitalizeFirst(trim($_POST['position'] ?? 'Recepcionista'));
            $department = trim($_POST['department'] ?? 'Recepción');
            $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
            $address = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($address) && !Validator::isValidAddress($address)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/admin/staff/create');
            }
            $hireDate = !empty($_POST['hire_date']) ? $_POST['hire_date'] : null;
            $notes = Validator::capitalizeFirst(trim($_POST['notes'] ?? ''));
            $email = trim($_POST['email'] ?? '');

            $createUser = !empty($_POST['create_user']);
            $userId = null;
            $tempPassword = null;

            if ($createUser || !empty($email)) {
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'Debe proporcionar un correo electrónico válido para crear acceso al sistema.');
                    View::redirect('/admin/staff/create');
                }

                // Validar unicidad de correo en users y staff
                $existingUser = $this->userModel->findOneWhere('email = ?', [$email]);
                $existingStaffEmail = $this->staffModel->findOneWhere('email = ?', [$email]);
                if ($existingUser || $existingStaffEmail) {
                    Session::flash('error', 'El correo electrónico ya está registrado por otro usuario en el sistema.');
                    View::redirect('/admin/staff/create');
                }

                $roleInput = trim($_POST['role'] ?? 'receptionist');
                $db = Database::getInstance();
                $targetRole = $db->fetch(
                    "SELECT id, name FROM roles WHERE (name = ? OR id = ?) AND LOWER(name) NOT IN ('doctor', 'medico', 'patient', 'paciente')",
                    [$roleInput, $roleInput]
                );

                if (!$targetRole) {
                    Session::flash('error', 'El rol seleccionado no es válido o no está permitido para colaboradores.');
                    View::redirect('/admin/staff/create');
                }

                $userData = $this->userModel->createWithTempPassword($email, $targetRole['name'], $name);
                $userId = $userData['user_id'];
                $tempPassword = $userData['temp_password'];
            }

            // Crear registro en staff
            $staffId = $this->staffModel->create([
                'user_id'    => $userId,
                'name'       => $name,
                'id_number'  => $idNumber,
                'email'      => !empty($email) ? $email : null,
                'phone'      => !empty($phone) ? $phone : null,
                'address'    => !empty($address) ? $address : null,
                'position'   => $position,
                'department' => $department,
                'hire_date'  => $hireDate,
                'status'     => 'active',
                'notes'      => !empty($notes) ? $notes : null
            ]);

            // Enviar credenciales si se generó usuario
            if ($userId && $tempPassword && $email) {
                $passwordResetModel = new PasswordReset();
                $token = $passwordResetModel->createTokenForType($userId, 'admin', 24);
                $appUrl = \App\Helpers\Env::getAppUrl();
                $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);

                Mailer::queue(
                    $email,
                    'Cuenta Creada - Credenciales de Acceso al Personal',
                    Mailer::buildUserCreationEmail([
                        'name'         => $name,
                        'email'        => $email,
                        'tempPassword' => $tempPassword,
                        'resetLink'    => $resetLink
                    ])
                );
            }

            Session::flash('success', 'Colaborador registrado exitosamente.');
            View::redirect('/admin/staff');
        } catch (\Throwable $e) {
            error_log("Error in AdminStaffController::store: " . $e->getMessage());
            Session::flash('error', 'Error al registrar colaborador: ' . $e->getMessage());
            View::redirect('/admin/staff/create');
        }
    }

    public function edit(string $id)
    {
        Auth::requirePermission('staff_update');
        $realId = HashId::decode($id) ?? (int)$id;
        $staff = $this->staffModel->getByIdWithUser($realId);
        if (!$staff) {
            Session::flash('error', 'Colaborador no encontrado.');
            View::redirect('/admin/staff');
        }

        $departments = Staff::getDepartments();
        $positions = Staff::getPositions();
        $roles = $this->getStaffAssignableRoles();

        View::render('admin.staff.edit', [
            'title'       => 'Editar Colaborador',
            'staff'       => $staff,
            'departments' => $departments,
            'positions'   => $positions,
            'roles'       => $roles
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('staff_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/staff');
        }

        $realId = HashId::decode($id) ?? (int)$id;

        try {
            $staff = $this->staffModel->findById($realId);
            if (!$staff) {
                Session::flash('error', 'Colaborador no encontrado.');
                View::redirect('/admin/staff');
            }

            $name = Validator::capitalizeWords(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Session::flash('error', 'El nombre es obligatorio.');
                View::redirect('/admin/staff/edit/' . $id);
            }

            if (!Validator::isValidName($name)) {
                Session::flash('error', 'El nombre solo debe contener letras y números.');
                View::redirect('/admin/staff/edit/' . $id);
            }

            $idNumber = preg_replace('/[^0-9]/', '', trim($_POST['id_number'] ?? ''));
            if (!empty($idNumber) && !Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'La cédula ingresada no es válida.');
                View::redirect('/admin/staff/edit/' . $id);
            }

            // Unicidad de cédula
            if (!empty($idNumber) && $idNumber !== $staff['id_number']) {
                $existing = $this->staffModel->findOneWhere('id_number = ? AND id != ?', [$idNumber, $realId]);
                if ($existing) {
                    Session::flash('error', 'Ya existe otro colaborador con esa cédula.');
                    View::redirect('/admin/staff/edit/' . $id);
                }
            }

            $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
            $address = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($address) && !Validator::isValidAddress($address)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/admin/staff/edit/' . $id);
            }

            $staffData = [
                'name'       => $name,
                'id_number'  => $idNumber,
                'phone'      => $phone ?: null,
                'address'    => $address ?: null,
                'position'   => Validator::capitalizeFirst(trim($_POST['position'] ?? 'Recepcionista')),
                'department' => trim($_POST['department'] ?? 'Recepción'),
                'hire_date'  => !empty($_POST['hire_date']) ? $_POST['hire_date'] : null,
                'notes'      => Validator::capitalizeFirst(trim($_POST['notes'] ?? '')) ?: null,
                'status'     => in_array($_POST['status'] ?? '', ['active', 'inactive', 'terminated']) ? $_POST['status'] : 'active'
            ];

            $email = trim($_POST['email'] ?? '');
            if (!empty($email)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'El formato del correo electrónico no es válido.');
                    View::redirect('/admin/staff/edit/' . $id);
                }

                // Check uniqueness
                $existingStaffEmail = $this->staffModel->findOneWhere('email = ? AND id != ?', [$email, $realId]);
                $existingUserEmail = $this->userModel->findOneWhere('email = ? AND id != ?', [$email, (int)($staff['user_id'] ?? 0)]);
                if ($existingStaffEmail || $existingUserEmail) {
                    Session::flash('error', 'El correo electrónico ya está en uso por otro usuario.');
                    View::redirect('/admin/staff/edit/' . $id);
                }

                $staffData['email'] = $email;

                if (!empty($staff['user_id'])) {
                    // Update linked user
                    $userUpdates = [
                        'email'          => $email,
                        'nombre_usuario' => $name,
                        'status'         => $staffData['status']
                    ];
                    if (!empty($_POST['password'])) {
                        if (!Validator::isStrongPassword($_POST['password'])) {
                            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.');
                            View::redirect('/admin/staff/edit/' . $id);
                        }
                        $userUpdates['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    }
                    $this->userModel->update((int)$staff['user_id'], $userUpdates);

                    // Update role if changed
                    if (!empty($_POST['role_id'])) {
                        $roleInput = trim($_POST['role_id']);
                        $db = Database::getInstance();
                        $targetRole = $db->fetch(
                            "SELECT id, name FROM roles WHERE (id = ? OR name = ?) AND LOWER(name) NOT IN ('doctor', 'medico', 'patient', 'paciente')",
                            [$roleInput, $roleInput]
                        );
                        if ($targetRole) {
                            $this->userModel->assignRole((int)$staff['user_id'], (int)$targetRole['id']);
                        }
                    }
                } else if (!empty($_POST['create_user'])) {
                    // Create user if previously didn't have one
                    $newPass = !empty($_POST['password']) ? $_POST['password'] : bin2hex(random_bytes(8));
                    $roleInput = trim($_POST['role'] ?? 'receptionist');
                    $db = Database::getInstance();
                    $targetRole = $db->fetch(
                        "SELECT id, name FROM roles WHERE (name = ? OR id = ?) AND LOWER(name) NOT IN ('doctor', 'medico', 'patient', 'paciente')",
                        [$roleInput, $roleInput]
                    );
                    $roleName = $targetRole ? $targetRole['name'] : 'receptionist';
                    $newUserId = $this->userModel->createWithRole($email, $newPass, $roleName, $name);
                    $staffData['user_id'] = $newUserId;
                }
            }

            $this->staffModel->update($realId, $staffData);

            Session::flash('success', 'Datos del colaborador actualizados correctamente.');
            View::redirect('/admin/staff');
        } catch (\Throwable $e) {
            error_log("Error in AdminStaffController::update: " . $e->getMessage());
            Session::flash('error', 'Error al actualizar: ' . $e->getMessage());
            View::redirect('/admin/staff/edit/' . $id);
        }
    }

    public function toggleStatus(string $id)
    {
        Auth::requirePermission('staff_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/staff');
        }

        $realId = HashId::decode($id) ?? (int)$id;
        $success = $this->staffModel->toggleStatus($realId);

        if ($success) {
            Session::flash('success', 'Estado del colaborador actualizado.');
        } else {
            Session::flash('error', 'No se pudo actualizar el estado.');
        }

        View::redirect('/admin/staff');
    }

    public function delete(string $id)
    {
        Auth::requirePermission('staff_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/staff');
        }

        $realId = HashId::decode($id) ?? (int)$id;
        $staff = $this->staffModel->findById($realId);

        if ($staff) {
            // Si tiene usuario vinculado, desactivar el usuario para evitar accesos
            if (!empty($staff['user_id'])) {
                $this->userModel->update((int)$staff['user_id'], ['status' => 'inactive', 'revoked_at' => date('Y-m-d H:i:s')]);
            }
            $this->staffModel->delete($realId);
            Session::flash('success', 'Colaborador eliminado correctamente.');
        } else {
            Session::flash('error', 'Colaborador no encontrado.');
        }

        View::redirect('/admin/staff');
    }
}
