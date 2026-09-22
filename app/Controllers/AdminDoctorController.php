<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Database;
use App\Helpers\Validator;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;

class AdminDoctorController
{
    private Doctor $doctorModel;
    private Specialty $specialtyModel;
    private User $userModel;

    public function __construct()
    {
        Auth::require();
        $this->doctorModel = new Doctor();
        $this->specialtyModel = new Specialty();
        $this->userModel = new User();
    }

    public function index()
    {
        Auth::requirePermission('doctors_read');
        $search = trim($_GET['search'] ?? '');
        $specialtyId = trim($_GET['specialty_id'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $paginationData = $this->doctorModel->paginateWithDetails($page, $perPage, $search, $specialtyId);
        $specialties = $this->specialtyModel->findAll('name ASC');
        
        View::render('admin.doctors.index', [
            'title' => 'Directorio Médico',
            'doctors' => $paginationData['data'],
            'specialties' => $specialties,
            'search' => $search,
            'specialty_id' => $specialtyId,
            'currentPage' => $paginationData['current_page'],
            'totalPages' => $paginationData['total_pages'],
            'totalRecords' => $paginationData['total_records'],
            'perPage' => $paginationData['per_page']
        ], 'admin');
    }

    public function create()
    {
        Auth::requirePermission('doctors_create');
        $specialties = $this->specialtyModel->findAll('name ASC');
        $db = Database::getInstance();
        $catalogTypes = $db->fetchAll("SELECT * FROM catalog_types ORDER BY name ASC");
        View::render('admin.doctors.create', [
            'title'        => 'Nuevo Médico',
            'specialties'  => $specialties,
            'catalogTypes' => $catalogTypes
        ], 'admin');
    }

    public function store()
    {
        Auth::requirePermission('doctors_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/doctors');
        }

        try {
            $name = Validator::capitalizeWords(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Session::flash('error', 'El nombre del médico es obligatorio.');
                View::redirect('/admin/doctors/create');
            }

            if (!Validator::isValidName($name)) {
                Session::flash('error', 'El nombre solo debe contener letras y números.');
                View::redirect('/admin/doctors/create');
            }

            $idNumber = preg_replace('/[^0-9]/', '', trim($_POST['id_number'] ?? ''));
            if (!empty($idNumber) && !Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'La cédula ingresada no es válida.');
                View::redirect('/admin/doctors/create');
            }

            if (!empty($idNumber)) {
                // Check if id_number already exists
                $existingDoctorId = $this->doctorModel->findOneWhere('id_number = ?', [$idNumber]);
                if ($existingDoctorId) {
                    Session::flash('error', 'Ya existe un médico con esa identificación.');
                    View::redirect('/admin/doctors/create');
                }
            }

            $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
            $address = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($address) && !Validator::isValidAddress($address)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/admin/doctors/create');
            }

            $email = trim($_POST['email'] ?? '');
            $generateCredentials = !empty($_POST['generate_credentials']);
            $userId = null;
            $tempPassword = null;
            $generatedUsername = null;
            
            if (!empty($email)) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'El formato del correo electrónico no es válido.');
                    View::redirect('/admin/doctors/create');
                }

                // Check if email already exists in users
                $existingUserEmail = $this->userModel->findOneWhere('email = ?', [$email]);
                if ($existingUserEmail) {
                    Session::flash('error', 'El correo electrónico ya está registrado en el sistema.');
                    View::redirect('/admin/doctors/create');
                }
                
                // Create user in unified users table
                $userData = $this->userModel->createWithTempPassword($email, 'doctor', $name);
                $userId = $userData['user_id'];
                $tempPassword = $userData['temp_password'];
                $generatedUsername = $userData['username'];
            } else {
                // Sin correo: generar usuario y contraseña temporal obligatoria (user_id no puede ser nulo)
                $userData = $this->userModel->createWithTempPassword(null, 'doctor', $name);
                $userId = $userData['user_id'];
                $tempPassword = $userData['temp_password'];
                $generatedUsername = $userData['username'];
            }

            // Create the Doctor record (No email/password column in doctors table)
            $doctorId = $this->doctorModel->create([
                'user_id'      => $userId,
                'name'         => $name,
                'id_number'    => $idNumber ?: null,
                'phone'        => $phone ?: null,
                'address'      => $address ?: null,
                'specialty_id' => $_POST['specialty_id'],
                'status'       => 'active',
                'show_fee'     => isset($_POST['show_fee']) ? 1 : 0
            ]);
            
            if ($email && $userId) {
                // Generate Password Reset Token (válido por 24 horas)
                $passwordResetModel = new \App\Models\PasswordReset();
                $token = $passwordResetModel->createTokenForType($userId, 'doctor', 24);
                $appUrl = \App\Helpers\Env::getAppUrl();
                $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);

                // Send Email
                try {
                    \App\Helpers\Mailer::queue(
                        $email,
                        'Cuenta Creada - Credenciales de Acceso Médico',
                        \App\Helpers\Mailer::buildUserCreationEmail([
                            'name' => $name,
                            'email' => $email,
                            'username' => $generatedUsername,
                            'tempPassword' => $tempPassword,
                            'resetLink' => $resetLink
                        ])
                    );
                } catch (\Throwable $mailErr) {
                    error_log('[AdminDoctorController] Error enviando correo: ' . $mailErr->getMessage());
                }
            }

            // Create Profile
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO doctor_profiles (doctor_id, consultation_fee, medical_license) VALUES (?, ?, ?)",
                [$doctorId, $_POST['consultation_fee'] ?? 0, $_POST['medical_license'] ?? null]
            );

            // Preparar mensaje de éxito
            if ($generatedUsername && $tempPassword && empty($email)) {
                // Sin correo: mostrar credenciales al admin
                Session::flash('success', 
                    "Médico registrado exitosamente.\n" .
                    "⚠️ CREDENCIALES GENERADAS (anótelas, no se mostrarán de nuevo):\n" .
                    "👤 Usuario: {$generatedUsername}\n" .
                    "🔑 Contraseña temporal: {$tempPassword}"
                );
            } elseif ($generatedUsername && !empty($email)) {
                Session::flash('success', 
                    "Médico registrado exitosamente. Se envió un correo a {$email} con las credenciales de acceso.\n" .
                    "👤 Usuario alternativo: {$generatedUsername}"
                );
            } else {
                Session::flash('success', 'Médico registrado exitosamente.');
            }

            View::redirect('/admin/doctors');
        } catch (\Throwable $e) {
            error_log("Error in AdminDoctorController::store: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            Session::flash('error', 'Error al registrar el médico: ' . $e->getMessage());
            View::redirect('/admin/doctors/create');
        }
    }

    public function edit(string $id)
    {
        Auth::requirePermission('doctors_update');
        $realId = \App\Helpers\HashId::decode($id) ?? (int)$id;
        $doctor = $this->doctorModel->getWithProfile($realId);
        if (!$doctor) {
            Session::flash('error', 'Médico no encontrado.');
            View::redirect('/admin/doctors');
        }

        $specialties = $this->specialtyModel->findAll('name ASC');
        $db = Database::getInstance();
        $catalogTypes = $db->fetchAll("SELECT * FROM catalog_types ORDER BY name ASC");
        View::render('admin.doctors.edit', [
            'title'        => 'Editar Médico',
            'doctor'       => $doctor,
            'specialties'  => $specialties,
            'catalogTypes' => $catalogTypes
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('doctors_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/doctors');
        }

        $realId = \App\Helpers\HashId::decode($id) ?? (int)$id;

        try {
            $doctor = $this->doctorModel->findById($realId);
            if (!$doctor) {
                Session::flash('error', 'Médico no encontrado.');
                View::redirect('/admin/doctors');
            }

            $name = Validator::capitalizeWords(trim($_POST['name'] ?? ''));
            if (empty($name)) {
                Session::flash('error', 'El nombre del médico es obligatorio.');
                View::redirect('/admin/doctors/edit/' . $id);
            }

            if (!Validator::isValidName($name)) {
                Session::flash('error', 'El nombre solo debe contener letras y números.');
                View::redirect('/admin/doctors/edit/' . $id);
            }

            $idNumber = preg_replace('/[^0-9]/', '', trim($_POST['id_number'] ?? ''));
            if (!empty($idNumber) && !Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'La cédula ingresada no es válida.');
                View::redirect('/admin/doctors/edit/' . $id);
            }

            if (!empty($idNumber)) {
                $existingDocId = $this->doctorModel->findOneWhere('id_number = ? AND id != ?', [$idNumber, $realId]);
                if ($existingDocId) {
                    Session::flash('error', 'Ya existe otro médico con esa identificación.');
                    View::redirect('/admin/doctors/edit/' . $id);
                }
            }

            $phone = preg_replace('/[^0-9]/', '', trim($_POST['phone'] ?? ''));
            $address = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($address) && !Validator::isValidAddress($address)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/admin/doctors/edit/' . $id);
            }

            $doctorData = [
                'name' => $name,
                'id_number' => $idNumber ?: null,
                'phone' => $phone ?: null,
                'address' => $address ?: null,
                'specialty_id' => $_POST['specialty_id'],
                'show_fee' => isset($_POST['show_fee']) ? 1 : 0
            ];

            // Update user email and password if provided
            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'El formato del correo electrónico no es válido.');
                    View::redirect('/admin/doctors/edit/' . $id);
                }
                
                // Check uniqueness in users
                $existingUserEmail = $this->userModel->findOneWhere('email = ? AND id != ?', [$email, (int)($doctor['user_id'] ?? 0)]);
                if ($existingUserEmail) {
                    Session::flash('error', 'El correo electrónico ya está registrado por otro usuario.');
                    View::redirect('/admin/doctors/edit/' . $id);
                }

                if (!empty($doctor['user_id'])) {
                    // Update linked user
                    $userUpdates = [
                        'email' => $email,
                        'nombre_usuario' => $name
                    ];
                    if (!empty($_POST['password'])) {
                        if (!Validator::isStrongPassword($_POST['password'])) {
                            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.');
                            View::redirect('/admin/doctors/edit/' . $id);
                        }
                        $userUpdates['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    }
                    $this->userModel->update((int)$doctor['user_id'], $userUpdates);
                } else {
                    // Legacy doctor without user_id: create account now
                    $newPass = !empty($_POST['password']) ? $_POST['password'] : bin2hex(random_bytes(8));
                    $userId = $this->userModel->createWithRole($email, $newPass, 'doctor', $name);
                    $doctorData['user_id'] = $userId;
                }
            }

            // Update doctor record (no password column)
            $this->doctorModel->update($realId, $doctorData);

            // Update profile
            $db = Database::getInstance();
            $existingProfile = $db->fetch("SELECT doctor_id FROM doctor_profiles WHERE doctor_id = ?", [(int)$id]);
            if ($existingProfile) {
                $db->execute(
                    "UPDATE doctor_profiles SET consultation_fee = ?, medical_license = ? WHERE doctor_id = ?",
                    [$_POST['consultation_fee'] ?? 0, $_POST['medical_license'] ?? null, (int)$id]
                );
            } else {
                $db->execute(
                    "INSERT INTO doctor_profiles (doctor_id, consultation_fee, medical_license) VALUES (?, ?, ?)",
                    [(int)$id, $_POST['consultation_fee'] ?? 0, $_POST['medical_license'] ?? null]
                );
            }

            Session::flash('success', 'Médico actualizado exitosamente.');
            View::redirect('/admin/doctors');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar el médico: ' . $e->getMessage());
            View::redirect('/admin/doctors/edit/' . $id);
        }
    }

    public function delete(string $id)
    {
        Auth::requirePermission('doctors_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/doctors');
        }

        $realId = \App\Helpers\HashId::decode($id) ?? (int)$id;

        try {
            $doctor = $this->doctorModel->findById($realId);
            if (!$doctor) {
                Session::flash('error', 'Médico no encontrado.');
                View::redirect('/admin/doctors');
            }

            $db = \App\Helpers\Database::getInstance();
            $userId = !empty($doctor['user_id']) ? (int)$doctor['user_id'] : null;

            $db->beginTransaction();

            try {
                // Desactivar validación FK temporalmente para limpieza segura
                $db->execute("SET FOREIGN_KEY_CHECKS = 0");

                // Helper seguro para ejecutar eliminaciones de tablas dependientes u opcionales
                $safeExecute = function(string $sql, array $params = []) use ($db) {
                    try {
                        $db->execute($sql, $params);
                    } catch (\Throwable $ex) {
                        error_log("[AdminDoctorController::delete] Limpieza auxiliar omitida (" . substr($sql, 0, 50) . "...): " . $ex->getMessage());
                    }
                };

                // ── 1. Obtener IDs de citas del médico (para eliminar dependientes)
                $apptIds = $db->fetchAll(
                    "SELECT id FROM appointments WHERE doctor_id = ?",
                    [$realId]
                );
                $apptIdList = array_column($apptIds, 'id');

                // ── 2. Eliminar datos dependientes de las citas del médico
                if (!empty($apptIdList)) {
                    $placeholders = implode(',', array_fill(0, count($apptIdList), '?'));
                    $safeExecute("DELETE FROM medical_notes WHERE appointment_id IN ($placeholders)", $apptIdList);
                    $safeExecute("DELETE FROM prescriptions WHERE appointment_id IN ($placeholders)", $apptIdList);
                    $safeExecute("DELETE FROM treatments    WHERE appointment_id IN ($placeholders)", $apptIdList);
                    $safeExecute("DELETE FROM email_logs    WHERE appointment_id IN ($placeholders)", $apptIdList);
                    $safeExecute("DELETE FROM doctor_availability WHERE appointment_id IN ($placeholders)", $apptIdList);

                    // order_items antes de orders
                    try {
                        $orderIds = $db->fetchAll("SELECT id FROM orders WHERE appointment_id IN ($placeholders)", $apptIdList);
                        if (!empty($orderIds)) {
                            $oPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
                            $oIdList = array_column($orderIds, 'id');
                            $safeExecute("DELETE FROM order_items WHERE order_id IN ($oPlaceholders)", $oIdList);
                        }
                    } catch (\Throwable $e) {
                        error_log("[AdminDoctorController::delete] Error buscando order_items: " . $e->getMessage());
                    }
                    $safeExecute("DELETE FROM orders        WHERE appointment_id IN ($placeholders)", $apptIdList);
                    $safeExecute("DELETE FROM invoices      WHERE appointment_id IN ($placeholders)", $apptIdList);
                }

                // ── 3. Eliminar datos del médico directamente referenciados
                $safeExecute("DELETE FROM medical_notes       WHERE doctor_id = ?", [$realId]);
                $safeExecute("DELETE FROM prescriptions       WHERE doctor_id = ?", [$realId]);
                $safeExecute("DELETE FROM treatments          WHERE doctor_id = ?", [$realId]);
                $safeExecute("DELETE FROM lab_orders          WHERE doctor_id = ?", [$realId]);
                if ($userId) {
                    $safeExecute("DELETE FROM lab_orders      WHERE doctor_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM doctor_availability WHERE doctor_id = ?", [$realId]);
                $safeExecute("DELETE FROM doctor_schedules    WHERE doctor_id = ?", [$realId]);
                if ($userId) {
                    $safeExecute("DELETE FROM doctor_schedules WHERE doctor_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM doctor_fees         WHERE doctor_id = ?", [$realId]);
                if ($userId) {
                    $safeExecute("DELETE FROM doctor_fees     WHERE doctor_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM doctor_holidays     WHERE doctor_id = ?", [$realId]);
                if ($userId) {
                    $safeExecute("DELETE FROM doctor_holidays WHERE doctor_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM doctor_profiles     WHERE doctor_id = ?", [$realId]);

                // ── 4. Eliminar citas del médico
                $safeExecute("DELETE FROM appointments WHERE doctor_id = ?", [$realId]);

                // ── 5. Eliminar el registro del médico
                $db->execute("DELETE FROM doctors WHERE id = ?", [$realId]);

                // ── 6. Eliminar usuario del sistema asociado
                if ($userId) {
                    $safeExecute("DELETE FROM two_factor_codes    WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM api_tokens          WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM sesiones_usuario    WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM password_resets     WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM user_roles          WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM user_permissions    WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM notifications       WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM audit_logs          WHERE user_id = ?", [$userId]);
                    $safeExecute("DELETE FROM auditoria_seguridad WHERE id_usuario = ?", [$userId]);
                    $safeExecute("DELETE FROM auditoria_clinica   WHERE id_usuario_acceso = ?", [$userId]);
                    $db->execute("DELETE FROM users               WHERE id      = ?", [$userId]);
                }

                // Restaurar validación FK
                $db->execute("SET FOREIGN_KEY_CHECKS = 1");

                $db->commit();

                // Auditoría (fuera de la transacción)
                if (class_exists('\App\Helpers\Audit')) {
                    \App\Helpers\Audit::log('delete', 'doctors', $realId, $doctor, null);
                }

                Session::flash('success', 'Médico y todos sus registros asociados han sido eliminados correctamente.');

            } catch (\Throwable $e) {
                // Restaurar FK y revertir
                try { $db->execute("SET FOREIGN_KEY_CHECKS = 1"); } catch (\Throwable $ignored) {}
                $db->rollback();
                throw $e;
            }

        } catch (\Throwable $e) {
            error_log("[AdminDoctorController::delete] Error: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());

            $err = $e->getMessage();
            if (str_contains($err, '1054') || str_contains($err, '42S22') || str_contains($err, 'appointment_id')) {
                Session::flash('error', 'No se pudo eliminar el médico debido a una inconsistencia de datos (columna no encontrada). El incidente ha sido registrado para revisión técnica.');
            } elseif (str_contains($err, '1451') || str_contains($err, '23000') || str_contains($err, 'foreign key constraint')) {
                Session::flash('error', 'No es posible eliminar al médico porque cuenta con registros históricos protegidos en el sistema. Le sugerimos desactivarlo en su lugar.');
            } elseif (str_contains($err, '1146') || str_contains($err, '42S02')) {
                Session::flash('error', 'No se pudo eliminar el médico debido a una inconsistencia en la estructura de tablas. Por favor contacte al administrador.');
            } else {
                Session::flash('error', 'No se pudo eliminar el médico. Por favor intente nuevamente o contacte al administrador del sistema.');
            }
        }

        View::redirect('/admin/doctors');
    }


    public function toggleStatus(string $id)
    {
        Auth::requirePermission('doctors_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/doctors');
        }

        $realId = \App\Helpers\HashId::decode($id) ?? (int)$id;

        try {
            $doctor = $this->doctorModel->findById($realId);
            if ($doctor) {
                $newStatus = $doctor['status'] === 'active' ? 'inactive' : 'active';
                $this->doctorModel->update($realId, ['status' => $newStatus]);

                // Actualizar también el estado del usuario asociado si existe
                if (!empty($doctor['user_id'])) {
                    $db = \App\Helpers\Database::getInstance();
                    $db->execute("UPDATE users SET status = ? WHERE id = ?", [$newStatus, (int)$doctor['user_id']]);
                }

                $label = $newStatus === 'active' ? 'activado' : 'desactivado';
                Session::flash('success', "Médico {$label} correctamente.");
            } else {
                Session::flash('error', 'Médico no encontrado.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al cambiar el estado del médico.');
        }

        View::redirect('/admin/doctors');
    }
}
