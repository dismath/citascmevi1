<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Validator;
use App\Models\Patient;
use App\Models\User;

class AdminPatientController
{
    private Patient $patientModel;
    private User $userModel;

    public function __construct()
    {
        Auth::require();
        $this->patientModel = new Patient();
        $this->userModel = new User();
    }

    public function index()
    {
        Auth::requirePermission('patients_read');
        $search = trim($_GET['search'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $paginationData = $this->patientModel->paginateWithDetails($page, $perPage, $search);
        
        View::render('admin.patients.index', [
            'title' => 'Directorio de Pacientes',
            'patients' => $paginationData['data'],
            'search' => $search,
            'currentPage' => $paginationData['current_page'],
            'totalPages' => $paginationData['total_pages'],
            'totalRecords' => $paginationData['total_records'],
            'perPage' => $paginationData['per_page']
        ], 'admin');
    }

    public function create()
    {
        Auth::requirePermission('patients_create');
        View::render('admin.patients.create', [
            'title' => 'Nuevo Paciente'
        ], 'admin');
    }

    public function store()
    {
        Auth::requirePermission('patients_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/patients');
        }

        try {
            $idNumber = trim($_POST['id_number']);
            $documentType = $_POST['document_type'] ?? 'cedula';

            // Solo validar cédula/RUC si el tipo de documento lo requiere
            if (in_array($documentType, ['cedula', 'ruc']) && !Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'El número de Cédula o RUC ingresado no es válido.');
                View::redirect('/admin/patients/create');
            }

            // Validar teléfono: solo números
            $phone = trim($_POST['phone'] ?? '');
            if (!empty($phone) && !Validator::isValidPhone($phone)) {
                Session::flash('error', 'El teléfono debe contener solo números.');
                View::redirect('/admin/patients/create');
            }

            // Validar nombre: solo texto (números permitidos si es RUC)
            $name = Validator::capitalizeWords(trim($_POST['name']));
            if (!Validator::isValidPatientName($name, true)) {
                Session::flash('error', 'El nombre contiene caracteres no permitidos.');
                View::redirect('/admin/patients/create');
            }

            // Validar dirección: solo texto y números
            $address = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($address) && !Validator::isValidAddress($address)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/admin/patients/create');
            }

            // Check if patient already exists
            $existing = $this->patientModel->getByIdNumber($idNumber);
            if ($existing) {
                Session::flash('error', 'Ya existe un paciente con esa identificación.');
                View::redirect('/admin/patients/create');
            }

            $docCodeMap = [
                'ruc' => '04',
                'cedula' => '05',
                'pasaporte' => '06',
                'consumidor_final' => '07',
                'id_extranjera' => '08'
            ];
            $docCode = $docCodeMap[$documentType] ?? '05';

            $email = null;
            $userId = null;
            $tempPassword = null;
            $generateCredentials = !empty($_POST['generate_credentials']);
            $generatedUsername = null;

            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'El formato del correo electrónico no es válido.');
                    View::redirect('/admin/patients/create');
                }

                // Check if email already exists in users
                $existingUser = $this->userModel->findOneWhere('email = ?', [$email]);
                if ($existingUser) {
                    Session::flash('error', 'El correo electrónico ya está en uso en el sistema.');
                    View::redirect('/admin/patients/create');
                }

                // Create user in unified users table
                $userData = $this->userModel->createWithTempPassword($email, 'patient', $name);
                $userId = $userData['user_id'];
                $tempPassword = $userData['temp_password'];
                $generatedUsername = $userData['username'];
            } elseif ($generateCredentials) {
                // Sin correo: generar usuario y contraseña temporal
                $userData = $this->userModel->createWithTempPassword(null, 'patient', $name);
                $userId = $userData['user_id'];
                $tempPassword = $userData['temp_password'];
                $generatedUsername = $userData['username'];
            }

            // Create patient record without email/password columns in patients table
            $patientId = $this->patientModel->create([
                'user_id'            => $userId,
                'name'               => $name,
                'id_number'          => $idNumber,
                'document_type_code' => $docCode,
                'phone'              => $_POST['phone'] ?? null,
                'address'            => $_POST['address'] ?? null,
                'date_of_birth'      => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
                'gender'             => $_POST['gender'] ?? null,
                'status'             => 'active'
            ]);

            if ($email && $userId) {
                // Generate Password Reset Token (válido por 24 horas)
                $passwordResetModel = new \App\Models\PasswordReset();
                $token = $passwordResetModel->createTokenForType($userId, 'patient', 24);
                $appUrl = \App\Helpers\Env::getAppUrl();
                $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);

                // Send Email
                try {
                    \App\Helpers\Mailer::queue(
                        $email,
                        'Cuenta Creada - Credenciales de Acceso Paciente',
                        \App\Helpers\Mailer::buildUserCreationEmail([
                            'name' => $name,
                            'email' => $email,
                            'username' => $generatedUsername,
                            'tempPassword' => $tempPassword,
                            'resetLink' => $resetLink
                        ])
                    );
                } catch (\Throwable $mailErr) {
                    error_log('[AdminPatientController] Error enviando correo: ' . $mailErr->getMessage());
                }
            }

            // Preparar mensaje de éxito
            if ($generatedUsername && $tempPassword && empty($email)) {
                Session::flash('success', 
                    "Paciente registrado exitosamente.\n" .
                    "⚠️ CREDENCIALES GENERADAS (anótelas, no se mostrarán de nuevo):\n" .
                    "👤 Usuario: {$generatedUsername}\n" .
                    "🔑 Contraseña temporal: {$tempPassword}"
                );
            } elseif ($generatedUsername && !empty($email)) {
                Session::flash('success', 
                    "Paciente registrado exitosamente. Se envió un correo a {$email} con las credenciales.\n" .
                    "👤 Usuario alternativo: {$generatedUsername}"
                );
            } else {
                Session::flash('success', 'Paciente registrado exitosamente.');
            }

            View::redirect('/admin/patients');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al registrar el paciente: ' . $e->getMessage());
            View::redirect('/admin/patients/create');
        }
    }

    public function edit(string $id)
    {
        Auth::requirePermission('patients_update');
        $realId = \App\Helpers\HashId::decode($id) ?? (int)$id;
        $patient = $this->patientModel->getByIdWithUser($realId);
        if (!$patient) {
            Session::flash('error', 'Paciente no encontrado.');
            View::redirect('/admin/patients');
        }

        View::render('admin.patients.edit', [
            'title' => 'Editar Paciente',
            'patient' => $patient
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('patients_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/patients');
        }

        $realId = \App\Helpers\HashId::decode($id) ?? (int)$id;

        try {
            $patient = $this->patientModel->findById($realId);
            if (!$patient) {
                Session::flash('error', 'Paciente no encontrado.');
                View::redirect('/admin/patients');
            }

            $idNumber = trim($_POST['id_number']);
            $documentType = $_POST['document_type'] ?? 'cedula';

            // Solo validar cédula/RUC si el tipo de documento lo requiere
            if (in_array($documentType, ['cedula', 'ruc']) && !Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'El número de Cédula o RUC ingresado no es válido.');
                View::redirect('/admin/patients/edit/' . $id);
            }

            // Validar teléfono: solo números
            $phone = trim($_POST['phone'] ?? '');
            if (!empty($phone) && !Validator::isValidPhone($phone)) {
                Session::flash('error', 'El teléfono debe contener solo números.');
                View::redirect('/admin/patients/edit/' . $id);
            }

            // Validar nombre: solo texto (números permitidos si es RUC)
            $name = Validator::capitalizeWords(trim($_POST['name']));
            if (!Validator::isValidPatientName($name, true)) {
                Session::flash('error', 'El nombre contiene caracteres no permitidos.');
                View::redirect('/admin/patients/edit/' . $id);
            }

            // Validar dirección: solo texto y números
            $address = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($address) && !Validator::isValidAddress($address)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/admin/patients/edit/' . $id);
            }

            // Check uniqueness of id_number if changed
            if ($idNumber !== $patient['id_number']) {
                $existing = $this->patientModel->getByIdNumber($idNumber);
                if ($existing && (int)$existing['id'] !== $realId) {
                    Session::flash('error', 'Ya existe otro paciente con esa identificación.');
                    View::redirect('/admin/patients/edit/' . $id);
                }
            }

            $docCodeMap = [
                'ruc' => '04',
                'cedula' => '05',
                'pasaporte' => '06',
                'consumidor_final' => '07',
                'id_extranjera' => '08'
            ];
            $docCode = $docCodeMap[$documentType] ?? '05';

            $patientData = [
                'name' => $name,
                'id_number' => $idNumber,
                'document_type_code' => $docCode,
                'phone' => $_POST['phone'] ?? null,
                'address' => $_POST['address'] ?? null,
                'date_of_birth' => !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
                'gender' => !empty($_POST['gender']) ? $_POST['gender'] : null
            ];

            // Handle Account
            if (!empty($_POST['email'])) {
                $email = trim($_POST['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Session::flash('error', 'El formato del correo electrónico no es válido.');
                    View::redirect('/admin/patients/edit/' . $id);
                }

                // Check uniqueness in users
                $existingUser = $this->userModel->findOneWhere('email = ? AND id != ?', [$email, (int)($patient['user_id'] ?? 0)]);
                if ($existingUser) {
                    Session::flash('error', 'El correo electrónico ya está en uso por otro usuario.');
                    View::redirect('/admin/patients/edit/' . $id);
                }

                if (!empty($patient['user_id'])) {
                    // Update existing user account
                    $userUpdates = [
                        'email' => $email,
                        'nombre_usuario' => $name
                    ];
                    if (!empty($_POST['password'])) {
                        if (!Validator::isStrongPassword($_POST['password'])) {
                            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.');
                            View::redirect('/admin/patients/edit/' . $id);
                        }
                        $userUpdates['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
                    }
                    $this->userModel->update((int)$patient['user_id'], $userUpdates);
                } else {
                    // Create new user account for patient
                    $newPassword = !empty($_POST['password']) ? $_POST['password'] : bin2hex(random_bytes(8));
                    $userId = $this->userModel->createWithRole($email, $newPassword, 'patient', $name);
                    $patientData['user_id'] = $userId;
                }
            }

            // Update patient record (no password column)
            $this->patientModel->update($realId, $patientData);

            Session::flash('success', 'Paciente actualizado exitosamente.');
            View::redirect('/admin/patients');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar el paciente: ' . $e->getMessage());
            View::redirect('/admin/patients/edit/' . $id);
        }
    }

    public function delete(string $id)
    {
        Auth::requirePermission('patients_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/patients');
        }

        $patientId = (int)$id;

        try {
            $patient = $this->patientModel->findById($patientId);
            if (!$patient) {
                Session::flash('error', 'Paciente no encontrado.');
                View::redirect('/admin/patients');
            }

            $db = \App\Helpers\Database::getInstance();
            $userId = !empty($patient['user_id']) ? (int)$patient['user_id'] : null;

            $db->beginTransaction();

            try {
                // Desactivar validación FK temporalmente para limpieza segura
                $db->execute("SET FOREIGN_KEY_CHECKS = 0");

                // Helper seguro para ejecutar eliminaciones de tablas dependientes u opcionales
                $safeExecute = function(string $sql, array $params = []) use ($db) {
                    try {
                        $db->execute($sql, $params);
                    } catch (\Throwable $ex) {
                        error_log("[AdminPatientController::delete] Limpieza auxiliar omitida (" . substr($sql, 0, 50) . "...): " . $ex->getMessage());
                    }
                };

                // ── 1. Obtener IDs de citas del paciente (para eliminar dependientes)
                $apptIds = $db->fetchAll(
                    "SELECT id FROM appointments WHERE patient_id = ?",
                    [$patientId]
                );
                $apptIdList = array_column($apptIds, 'id');

                // ── 2. Eliminar datos dependientes de las citas
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
                        error_log("[AdminPatientController::delete] Error buscando order_items: " . $e->getMessage());
                    }
                    $safeExecute("DELETE FROM orders        WHERE appointment_id IN ($placeholders)", $apptIdList);
                    $safeExecute("DELETE FROM invoices      WHERE appointment_id IN ($placeholders)", $apptIdList);
                }

                // ── 3. Eliminar datos del paciente directamente referenciados
                $safeExecute("DELETE FROM medical_notes     WHERE patient_id = ?", [$patientId]);
                $safeExecute("DELETE FROM prescriptions     WHERE patient_id = ?", [$patientId]);
                $safeExecute("DELETE FROM treatments        WHERE patient_id = ?", [$patientId]);
                $safeExecute("DELETE FROM lab_orders        WHERE patient_id = ?", [$patientId]);
                if ($userId) {
                    $safeExecute("DELETE FROM lab_orders    WHERE patient_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM medical_history   WHERE patient_id = ?", [$patientId]);
                $safeExecute("DELETE FROM patient_documents WHERE patient_id = ?", [$patientId]);
                if ($userId) {
                    $safeExecute("DELETE FROM patient_documents WHERE patient_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM patient_profiles  WHERE patient_id = ?", [$patientId]);
                $safeExecute("DELETE FROM invoices          WHERE patient_id = ?", [$patientId]);
                if ($userId) {
                    $safeExecute("DELETE FROM invoices      WHERE patient_id = ?", [$userId]);
                }
                $safeExecute("DELETE FROM auditoria_clinica WHERE id_paciente_afectado = ?", [$patientId]);

                // order_items antes de orders directas del paciente
                try {
                    $directOrders = $db->fetchAll("SELECT id FROM orders WHERE patient_id = ?", [$patientId]);
                    if (!empty($directOrders)) {
                        $dPlaceholders = implode(',', array_fill(0, count($directOrders), '?'));
                        $dIdList = array_column($directOrders, 'id');
                        $safeExecute("DELETE FROM order_items WHERE order_id IN ($dPlaceholders)", $dIdList);
                    }
                } catch (\Throwable $e) {
                    error_log("[AdminPatientController::delete] Error directOrders: " . $e->getMessage());
                }
                $safeExecute("DELETE FROM orders            WHERE patient_id = ?", [$patientId]);

                // ── 4. Eliminar citas del paciente
                $safeExecute("DELETE FROM appointments WHERE patient_id = ?", [$patientId]);

                // ── 5. Eliminar el registro del paciente
                $db->execute("DELETE FROM patients WHERE id = ?", [$patientId]);

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
                    \App\Helpers\Audit::log('delete', 'patients', $patientId, $patient, null);
                }

                Session::flash('success', 'Paciente y todos sus registros asociados han sido eliminados correctamente.');

            } catch (\Throwable $e) {
                // Restaurar FK y revertir
                try { $db->execute("SET FOREIGN_KEY_CHECKS = 1"); } catch (\Throwable $ignored) {}
                $db->rollback();
                throw $e;
            }

        } catch (\Throwable $e) {
            error_log("[AdminPatientController::delete] Error: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());

            $err = $e->getMessage();
            if (str_contains($err, '1054') || str_contains($err, '42S22') || str_contains($err, 'appointment_id')) {
                Session::flash('error', 'No se pudo eliminar el paciente debido a una inconsistencia de datos (columna no encontrada). El incidente ha sido registrado para revisión técnica.');
            } elseif (str_contains($err, '1451') || str_contains($err, '23000') || str_contains($err, 'foreign key constraint')) {
                Session::flash('error', 'No es posible eliminar al paciente porque cuenta con historiales clínicos, recetas o registros activos en el sistema. Le sugerimos desactivarlo en su lugar.');
            } elseif (str_contains($err, '1146') || str_contains($err, '42S02')) {
                Session::flash('error', 'No se pudo eliminar el paciente debido a una inconsistencia en la estructura de tablas. Por favor contacte al administrador.');
            } else {
                Session::flash('error', 'No se pudo eliminar el paciente. Por favor intente nuevamente o contacte al administrador del sistema.');
            }
        }

        View::redirect('/admin/patients');
    }
}

