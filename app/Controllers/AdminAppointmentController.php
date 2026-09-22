<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\Availability;

class AdminAppointmentController
{
    private Appointment $appointmentModel;

    public function __construct()
    {
        Auth::require();
        $this->appointmentModel = new Appointment();
    }

    public function index()
    {
        Auth::requirePermission('appointments_read');
        $status = $_GET['status'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $filter = '';
        $params = [];
        
        if ($status) {
            if ($status === 'rescheduled') {
                $filter = "a.reschedule_count > 0";
            } else {
                $filter = "a.status = ?";
                $params[] = $status;
            }
        }

        if ($startDate && $endDate) {
            $filter .= ($filter ? " AND " : "") . "DATE(a.appointment_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $appointments = $this->appointmentModel->getAllWithDetails($filter, $params);

        // Hide non-medical appointments that already have an order generated
        $appointments = array_filter($appointments, function($app) {
            return !($app['specialty_type_code'] !== 'ESPEC' && $app['orders_count'] > 0);
        });

        $specialtyModel = new \App\Models\Specialty();
        $specialties = $specialtyModel->getAllActive();

        View::render('admin.appointments.index', [
            'title' => 'Gestión de Citas',
            'appointments' => $appointments,
            'specialties' => $specialties,
            'currentStatus' => $status,
            'currentStartDate' => $startDate,
            'currentEndDate' => $endDate
        ], 'admin');
    }

    public function create()
    {
        Auth::requirePermission('appointments_create');
        View::redirect('/admin/appointments');
    }

    public function store()
    {
        Auth::requirePermission('appointments_create');
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
                  || !empty($_POST['is_ajax']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            if ($isAjax) {
                View::json(['success' => false, 'message' => 'Token de seguridad inválido o petición expirada.'], 400);
                return;
            }
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/admin/appointments');
            return;
        }

        try {
            $specialtyId    = (int)($_POST['specialty_id'] ?? 0);
            $doctorId       = (int)($_POST['doctor_id'] ?? 0);
            $availabilityId = (int)($_POST['availability_id'] ?? 0);

            if ($specialtyId <= 0 || $doctorId <= 0 || $availabilityId <= 0) {
                throw new \Exception('Debe seleccionar la especialidad, el médico y un horario disponible.');
            }

            // Datos del paciente
            $patientId        = (int)($_POST['patient_id'] ?? 0);
            $patientIdNumber  = trim($_POST['patient_id_number'] ?? '');
            $patientName      = trim($_POST['patient_name'] ?? '');
            $patientPhone     = trim($_POST['patient_phone'] ?? '');
            $patientEmail     = trim($_POST['patient_email'] ?? '');
            $patientAddress   = trim($_POST['patient_address'] ?? '');
            $documentTypeCode = trim($_POST['document_type_code'] ?? '05');

            if ($patientId > 0) {
                $patientModel = new \App\Models\Patient();
                $existing = $patientModel->findById($patientId);
                if ($existing) {
                    $patientData = [
                        'id_number'          => $existing['id_number'],
                        'name'               => !empty($patientName) ? $patientName : $existing['name'],
                        'phone'              => !empty($patientPhone) ? $patientPhone : ($existing['phone'] ?? ''),
                        'email'              => !empty($patientEmail) ? $patientEmail : '',
                        'address'            => !empty($patientAddress) ? $patientAddress : ($existing['address'] ?? ''),
                        'document_type_code' => $existing['document_type_code'] ?? '05',
                    ];
                } else {
                    throw new \Exception('El paciente seleccionado no existe.');
                }
            } else {
                if (empty($patientIdNumber) || empty($patientName)) {
                    throw new \Exception('Por favor ingrese la identificación y nombre del paciente.');
                }
                $patientData = [
                    'id_number'          => $patientIdNumber,
                    'name'               => $patientName,
                    'phone'              => $patientPhone,
                    'email'              => $patientEmail,
                    'address'            => $patientAddress,
                    'document_type_code' => $documentTypeCode,
                ];
            }

            // Subir comprobante de pago si existe
            $attachmentUrl = null;
            if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] === UPLOAD_ERR_OK) {
                $appConfig = require CONFIG_PATH . '/app.php';
                $uploadDir = $appConfig['upload_dir'];
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $attachmentUrl = $this->validateAndMoveUpload($_FILES['payment_receipt'], $uploadDir, $appConfig);
            }

            // Verificar turno disponible
            $availabilityModel = new Availability();
            $slot = $availabilityModel->getSlotById($availabilityId);
            if (!$slot || $slot['status'] !== 'available') {
                throw new \Exception('El horario seleccionado ya no está disponible. Por favor elija otro turno.');
            }

            $appointmentDate = $slot['available_date'] . ' ' . $slot['start_time'];
            $token = bin2hex(random_bytes(32));

            // Estado por defecto: "agendada" (requerimiento explícito)
            $status = trim($_POST['status'] ?? 'agendada');
            if (!in_array($status, ['agendada', 'confirmed', 'pending'])) {
                $status = 'agendada';
            }

            $appointmentData = [
                'doctor_id'          => $doctorId,
                'specialty_id'       => $specialtyId,
                'appointment_date'   => $appointmentDate,
                'attachment_url'     => $attachmentUrl,
                'notes'              => trim($_POST['notes'] ?? ''),
                'confirmation_token' => $token,
                'token_expires_at'   => date('Y-m-d H:i:s', strtotime('+24 hours')),
            ];

            // Crear cita completa con paciente y credenciales automáticas
            $bookingResult = $this->appointmentModel->createFull($appointmentData, $patientData, $availabilityId);
            $appointmentId = is_array($bookingResult) ? (int)$bookingResult['appointment_id'] : (int)$bookingResult;
            $userId        = is_array($bookingResult) ? ($bookingResult['user_id'] ?? null) : null;
            $username      = is_array($bookingResult) ? ($bookingResult['username'] ?? null) : null;
            $tempPassword  = is_array($bookingResult) ? ($bookingResult['temp_password'] ?? null) : null;
            $userCreated   = is_array($bookingResult) ? ($bookingResult['user_created'] ?? false) : false;

            // Actualizar el estado a 'agendada' (o el seleccionado)
            $this->appointmentModel->update($appointmentId, ['status' => $status]);

            // Enviar correo de notificación y credenciales al paciente si tiene correo
            if (!empty($patientData['email'])) {
                try {
                    $appConfig = require CONFIG_PATH . '/app.php';
                    $baseUrl   = rtrim($appConfig['base_url'] ?? '', '/');
                    if (empty($baseUrl)) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $baseUrl  = $protocol . $host;
                    }

                    $portalUrl  = $baseUrl . '/login';
                    $clinicInfo = \App\Helpers\Mailer::getClinicInfo();

                    // 1. Si el usuario fue creado, enviar CORREO CON LAS CREDENCIALES DE ACCESO A LA PLATAFORMA
                    if ($userCreated && !empty($tempPassword)) {
                        $resetLink = $portalUrl;
                        if ($userId) {
                            try {
                                $pwdResetModel = new \App\Models\PasswordReset();
                                $resetToken = $pwdResetModel->createTokenForType($userId, 'patient', 24);
                                $resetLink  = $baseUrl . '/reset-password?token=' . urlencode($resetToken);
                            } catch (\Throwable $tokEx) {
                                error_log("[AdminAppointmentController] Error generando token reset: " . $tokEx->getMessage());
                            }
                        }

                        $credSubject = "Credenciales de Acceso al Portal de Pacientes - {$clinicInfo['name']}";
                        $credHtml = \App\Helpers\Mailer::buildUserCreationEmail([
                            'name'         => $patientData['name'],
                            'email'        => $patientData['email'],
                            'username'     => $username ?: $patientData['email'],
                            'tempPassword' => $tempPassword,
                            'resetLink'    => $resetLink,
                        ]);

                        \App\Helpers\Mailer::queue($patientData['email'], $credSubject, $credHtml);
                    }

                    // 2. Enviar correo de notificación de la cita médica
                    $mailData = [
                        'patient_name'   => $patientData['name'],
                        'appointment_id' => $appointmentId,
                        'username'       => $username ?: $patientData['email'],
                        'password'       => !empty($tempPassword) ? $tempPassword : 'Tu contraseña habitual',
                        'portal_url'     => $portalUrl,
                        'clinic_name'    => $clinicInfo['name'],
                        'clinic_phone'   => $clinicInfo['phone'],
                        'clinic_email'   => $clinicInfo['email'],
                        'is_new_user'    => $userCreated,
                    ];

                    \App\Helpers\Mailer::sendPatientBookingNotification($patientData['email'], $mailData);
                } catch (\Throwable $mEx) {
                    error_log("Error enviando correo en admin store: " . $mEx->getMessage());
                }
            }

            // Notificar al doctor por FCM (Push) si tiene tokens
            try {
                $db = \App\Helpers\Database::getInstance();
                $docTokens = $db->fetchAll(
                    "SELECT DISTINCT fcm_token FROM api_tokens 
                     WHERE user_id = (SELECT user_id FROM doctors WHERE id = ?) AND fcm_token IS NOT NULL",
                    [$doctorId]
                );
                $fcmTitle = "🟢 Nueva Cita Agendada";
                $fcmBody  = "Cita agendada para el paciente {$patientData['name']} el día {$slot['available_date']} a las {$slot['start_time']}.";
                foreach ($docTokens as $t) {
                    \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $fcmBody, ['type' => 'new_appointment', 'appointment_id' => $appointmentId]);
                }
            } catch (\Throwable $fcmEx) {
                // Silently ignore push errors
            }

            $hashId    = \App\Helpers\HashId::encode($appointmentId);
            $appConfig = require CONFIG_PATH . '/app.php';
            $baseUrl   = rtrim($appConfig['base_url'] ?? '', '/');
            $printUrl  = $baseUrl . '/admin/appointments/print-ticket/' . $hashId;

            if ($isAjax) {
                View::json([
                    'success'        => true,
                    'message'        => 'Cita médica registrada exitosamente con estado "Agendada".',
                    'appointment_id' => $appointmentId,
                    'hash_id'        => $hashId,
                    'print_url'      => $printUrl,
                    'patient_name'   => $patientData['name'],
                    'doctor_id'      => $doctorId,
                    'date'           => date('d/m/Y H:i', strtotime($appointmentDate)),
                    'status'         => $status,
                ]);
                return;
            }

            Session::flash('success', 'Cita médica registrada con estado "Agendada".');
            View::redirect('/admin/appointments');

        } catch (\Exception $e) {
            if ($isAjax) {
                View::json(['success' => false, 'message' => $e->getMessage()], 400);
                return;
            }
            Session::flash('error', 'Error al agendar la cita: ' . $e->getMessage());
            View::redirect('/admin/appointments');
        }
    }

    /**
     * Imprime el recibo / ticket de agendamiento en formato térmico (7 x 15 cm adaptable a rollo).
     */
    public function printTicket(string $id)
    {
        Auth::requirePermission('appointments_read');
        $rawId = \App\Helpers\HashId::decode($id);
        $appointmentId = $rawId !== null ? $rawId : (int)$id;

        $appointment = $this->appointmentModel->getByIdWithDetails($appointmentId);
        if (!$appointment) {
            Session::flash('error', 'Cita no encontrada.');
            View::redirect('/admin/appointments');
            return;
        }

        $settingsModel = new \App\Models\SystemSetting();
        $clinicInfo    = \App\Helpers\Mailer::getClinicInfo();

        View::partial('admin.appointments.ticket', [
            'appointment' => $appointment,
            'clinicInfo'  => $clinicInfo,
            'settings'    => $settingsModel,
        ]);
    }

    /**
     * Valida y almacena un comprobante de pago subido.
     */
    private function validateAndMoveUpload(array $file, string $uploadDir, array $config): string
    {
        if ($file['size'] > ($config['max_upload_size'] ?? 5 * 1024 * 1024)) {
            throw new \Exception('El comprobante excede el tamaño máximo permitido (5 MB).');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = $config['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'pdf', 'gif'];
        if (!in_array($ext, $allowed, true)) {
            throw new \Exception('Tipo de archivo no permitido. Solo se aceptan: ' . implode(', ', $allowed));
        }

        $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception('No se pudo guardar el archivo del comprobante.');
        }

        return 'uploads/' . $newFilename;
    }

    /**
     * Sube un comprobante de pago para una cita existente.
     */
    public function uploadReceipt(string $id)
    {
        Auth::requirePermission('appointments_update');
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                  || !empty($_POST['is_ajax']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            if ($isAjax) {
                View::json(['success' => false, 'message' => 'Petición inválida.']);
                return;
            }
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/appointments');
        }

        try {
            $appointmentId = (int)\App\Helpers\HashId::decode($id) ?: (int)$id;
            $appointment = $this->appointmentModel->findById($appointmentId);

            if (!$appointment) {
                if ($isAjax) {
                    View::json(['success' => false, 'message' => 'Cita no encontrada.']);
                    return;
                }
                Session::flash('error', 'Cita no encontrada.');
                View::redirect('/admin/appointments');
            }

            if (!empty($appointment['attachment_url'])) {
                if ($isAjax) {
                    View::json(['success' => false, 'message' => 'Esta cita ya tiene un comprobante registrado.']);
                    return;
                }
                Session::flash('error', 'Esta cita ya tiene un comprobante registrado.');
                View::redirect('/admin/appointments');
            }

            if (!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
                if ($isAjax) {
                    View::json(['success' => false, 'message' => 'Debe seleccionar un archivo válido para el comprobante.']);
                    return;
                }
                Session::flash('error', 'Debe seleccionar un archivo válido para el comprobante.');
                View::redirect('/admin/appointments');
            }

            $appConfig = require CONFIG_PATH . '/app.php';
            $uploadDir = $appConfig['upload_dir'];
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $attachmentUrl = $this->validateAndMoveUpload($_FILES['payment_receipt'], $uploadDir, $appConfig);

            $this->appointmentModel->update($appointmentId, ['attachment_url' => $attachmentUrl]);
            
            if ($isAjax) {
                $fileUrl = rtrim($appConfig['base_url'], '/') . '/admin/appointments/attachment/' . \App\Helpers\HashId::encode($appointmentId);
                View::json([
                    'success' => true,
                    'message' => 'Comprobante de pago cargado exitosamente.',
                    'url' => $fileUrl
                ]);
                return;
            }

            Session::flash('success', 'Comprobante de pago cargado exitosamente.');
        } catch (\Exception $e) {
            if ($isAjax) {
                View::json(['success' => false, 'message' => 'Error al cargar el comprobante: ' . $e->getMessage()]);
                return;
            }
            Session::flash('error', 'Error al cargar el comprobante: ' . $e->getMessage());
        }

        View::redirect('/admin/appointments');
    }

    public function edit(string $id)
    {
        Auth::requirePermission('appointments_update');
        $appointment = $this->appointmentModel->getByIdWithDetails((int)$id);
        if (!$appointment) {
            Session::flash('error', 'Cita no encontrada.');
            View::redirect('/admin/appointments');
        }

        View::render('admin.appointments.edit', [
            'title' => 'Editar Cita',
            'appointment' => $appointment
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('appointments_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/appointments');
        }

        try {
            $appointment = $this->appointmentModel->findById((int)$id);
            if (!$appointment) {
                Session::flash('error', 'Cita no encontrada.');
                View::redirect('/admin/appointments');
            }

            $updateData = [
                'notes' => trim($_POST['notes'] ?? '')
            ];

            if (isset($_POST['status']) && in_array($_POST['status'], ['pending', 'scheduled', 'agendada', 'confirmed', 'completed', 'cancelled'])) {
                $updateData['status'] = $_POST['status'];
                
                // If cancelled, free up slot
                if ($_POST['status'] === 'cancelled' && $appointment['status'] !== 'cancelled') {
                    $availabilityModel = new Availability();
                    $slot = $availabilityModel->findOneWhere("notes = ?", ["Cita #{$id}"]);
                    if ($slot) {
                        $availabilityModel->markAsAvailable((int)$slot['id']);
                    }
                }
            }

            $this->appointmentModel->update((int)$id, $updateData);
            Session::flash('success', 'Cita actualizada correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar la cita.');
        }

        View::redirect('/admin/appointments');
    }

    public function changeStatus(string $id)
    {
        Auth::requirePermission('appointments_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/appointments');
        }

        $newStatus = $_POST['status'] ?? '';
        $allowedStatuses = ['pending', 'scheduled', 'agendada', 'confirmed', 'completed', 'cancelled'];
        
        if (!in_array($newStatus, $allowedStatuses)) {
            Session::flash('error', 'Estado no válido.');
            View::redirect('/admin/appointments');
        }

        $appointment = $this->appointmentModel->findById((int)$id);
        if (!$appointment) {
            Session::flash('error', 'Cita no encontrada.');
            View::redirect('/admin/appointments');
        }
        
        $currentStatus = strtolower(trim($appointment['status']));
        $statusOrder = ['pending' => 0, 'scheduled' => 1, 'agendada' => 1, 'confirmed' => 2, 'completed' => 3, 'cancelled' => 99];
        $currentOrder = $statusOrder[$currentStatus] ?? -1;
        $newOrder = $statusOrder[$newStatus] ?? -1;

        if ($currentOrder > 0 && $newOrder <= $currentOrder && $newStatus !== 'cancelled') {
            Session::flash('error', 'No se permite regresar a un estado anterior.');
            View::redirect('/admin/appointments');
        }
        if (in_array($currentStatus, ['completed', 'cancelled'])) {
            Session::flash('error', 'La cita ya fue completada o cancelada y no puede cambiar de estado.');
            View::redirect('/admin/appointments');
        }

        try {
            $this->appointmentModel->update((int)$id, ['status' => $newStatus]);
            
            // FASE 3: Registrar en auditoría
            \App\Helpers\Audit::log('status_change', 'appointments', (int)$id, ['status' => $appointment['status']], ['status' => $newStatus]);
            
            // If cancelled, free up the availability slot
            if ($newStatus === 'cancelled') {
                $availabilityModel = new Availability();
                // We need to find the slot associated with this appointment.
                // Assuming notes in doctor_availability contains the appointment ID
                $slot = $availabilityModel->findOneWhere("notes = ?", ["Cita #{$id}"]);
                if ($slot) {
                    $availabilityModel->markAsAvailable((int)$slot['id']);
                }
            }

            // Si se confirma, enviar correo de confirmación al paciente Y al doctor
            if ($newStatus === 'confirmed') {
                $appointmentDetails = $this->appointmentModel->getByIdWithDetails((int)$id);
                $emailSent = false;
                $doctorEmailSent = false;

                if ($appointmentDetails) {
                    $subject = "Confirmación de Cita Médica #" . str_pad($id, 5, '0', STR_PAD_LEFT);

                    // Correo al paciente
                    if (!empty($appointmentDetails['patient_email'])) {
                        $htmlBody = \App\Helpers\Mailer::buildConfirmationEmail($appointmentDetails);
                        try {
                            $emailSent = \App\Helpers\Mailer::queue($appointmentDetails['patient_email'], $subject, $htmlBody);
                            if ($emailSent) {
                                $this->appointmentModel->update((int)$id, ['email_sent' => 1]);
                            }
                        } catch (\Exception $e) {
                            error_log("Error enviando correo de confirmación al paciente: " . $e->getMessage());
                        }
                    }

                    // Correo al doctor
                    if (!empty($appointmentDetails['doctor_email'])) {
                        $doctorSubject = "Nueva Cita Confirmada en su Agenda #" . str_pad($id, 5, '0', STR_PAD_LEFT);
                        $doctorHtmlBody = \App\Helpers\Mailer::buildDoctorConfirmationEmail($appointmentDetails);
                        try {
                            $doctorEmailSent = \App\Helpers\Mailer::queue($appointmentDetails['doctor_email'], $doctorSubject, $doctorHtmlBody);
                        } catch (\Exception $e) {
                            error_log("Error enviando correo de confirmación al doctor: " . $e->getMessage());
                        }
                    }

                    // Notificaciones Push (FCM) al confirmar cita
                    $db = \App\Helpers\Database::getInstance();
                    $appointmentDate = date('d/m/Y H:i', strtotime($appointmentDetails['appointment_date']));
                    $fcmTitle = "✅ Cita Confirmada";

                    // Push al paciente
                    $patTokens = $db->fetchAll(
                        "SELECT DISTINCT fcm_token FROM api_tokens 
                         WHERE user_id = (SELECT user_id FROM patients WHERE id = ?) AND fcm_token IS NOT NULL",
                        [$appointmentDetails['patient_id']]
                    );
                    $patBody = "Su cita con el Dr. {$appointmentDetails['doctor_name']} para el {$appointmentDate} ha sido confirmada.";
                    foreach ($patTokens as $t) {
                        \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $patBody, ['type' => 'confirmed', 'appointment_id' => $id]);
                    }

                    // Push al doctor
                    $docTokens = $db->fetchAll(
                        "SELECT DISTINCT fcm_token FROM api_tokens 
                         WHERE user_id = (SELECT user_id FROM doctors WHERE id = ?) AND fcm_token IS NOT NULL",
                        [$appointmentDetails['doctor_id']]
                    );
                    $docBody = "La cita con el paciente {$appointmentDetails['patient_name']} para el {$appointmentDate} ha sido confirmada.";
                    foreach ($docTokens as $t) {
                        \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $docBody, ['type' => 'confirmed', 'appointment_id' => $id]);
                    }
                }
            }

            // Correo post-visita al completar la cita
            if ($newStatus === 'completed') {
                $appointmentDetails = $this->appointmentModel->getByIdWithDetails((int)$id);
                $visitEmailSent = false;
                $doctorVisitEmailSent = false;

                if ($appointmentDetails) {
                    $settingsModel = new \App\Models\SystemSetting();
                    $surveyUrl = $settingsModel->get('survey_url', '');

                    // Correo al PACIENTE (agradecimiento post-visita)
                    if (!empty($appointmentDetails['patient_email'])) {
                        try {
                            $subject = 'Gracias por tu visita - Esperamos haberte brindado una excelente atención';
                            $htmlBody = \App\Helpers\Mailer::buildCompletedVisitEmail($appointmentDetails, $surveyUrl);
                            $visitEmailSent = \App\Helpers\Mailer::queue($appointmentDetails['patient_email'], $subject, $htmlBody);
                        } catch (\Exception $e) {
                            error_log('Error enviando correo post-visita al paciente: ' . $e->getMessage());
                        }
                    }

                    // Correo al MÉDICO (resumen de atención completada)
                    if (!empty($appointmentDetails['doctor_email'])) {
                        try {
                            $docSubject = 'Atención Completada — Resumen de Consulta #' . str_pad($id, 5, '0', STR_PAD_LEFT);
                            $docBody = \App\Helpers\Mailer::buildDoctorCompletedVisitEmail($appointmentDetails);
                            $doctorVisitEmailSent = \App\Helpers\Mailer::queue($appointmentDetails['doctor_email'], $docSubject, $docBody);
                        } catch (\Exception $e) {
                            error_log('Error enviando correo de visita completada al médico: ' . $e->getMessage());
                        }
                    }
                }
            }
            
            // Correo de anulación de cita
            if ($newStatus === 'cancelled') {
                $appointmentDetails = $this->appointmentModel->getByIdWithDetails((int)$id);
                $cancelEmailSent = false;
                $doctorCancelEmailSent = false;

                if ($appointmentDetails) {
                    // Correo al PACIENTE (notificación de anulación)
                    if (!empty($appointmentDetails['patient_email'])) {
                        try {
                            $subject = 'Tu cita médica ha sido anulada';
                            $htmlBody = \App\Helpers\Mailer::buildCancelledVisitEmail($appointmentDetails);
                            $cancelEmailSent = \App\Helpers\Mailer::queue($appointmentDetails['patient_email'], $subject, $htmlBody);
                        } catch (\Exception $e) {
                            error_log('Error enviando correo de anulación al paciente: ' . $e->getMessage());
                        }
                    }

                    // Correo al MÉDICO (notificación de anulación)
                    if (!empty($appointmentDetails['doctor_email'])) {
                        try {
                            $docSubject = 'Cita Anulada en su Agenda — #' . str_pad($id, 5, '0', STR_PAD_LEFT);
                            $docBody = \App\Helpers\Mailer::buildDoctorCancelledVisitEmail($appointmentDetails);
                            $doctorCancelEmailSent = \App\Helpers\Mailer::queue($appointmentDetails['doctor_email'], $docSubject, $docBody);
                        } catch (\Exception $e) {
                            error_log('Error enviando correo de anulación al médico: ' . $e->getMessage());
                        }
                    }
                }
            }

            if ($newStatus === 'confirmed') {
                if ($emailSent && $doctorEmailSent) {
                    Session::flash('success', 'Cita confirmada. Correo enviado al paciente y al doctor ✅');
                } elseif ($emailSent) {
                    Session::flash('success', 'Cita confirmada y correo de confirmación enviado al paciente ✅');
                } elseif ($doctorEmailSent) {
                    Session::flash('warning', 'Cita confirmada. Correo enviado al doctor, pero no al paciente ⚠️');
                } else {
                    Session::flash('warning', 'Cita confirmada, pero no se pudieron enviar los correos de confirmación ⚠️');
                }
            } elseif ($newStatus === 'completed') {
                if (($visitEmailSent ?? false) && ($doctorVisitEmailSent ?? false)) {
                    Session::flash('success', 'Cita completada. Correos de agradecimiento enviados al paciente y al médico ✅');
                } elseif ($visitEmailSent ?? false) {
                    Session::flash('success', 'Cita completada. Correo enviado al paciente ✅ (no se pudo enviar al médico)');
                } elseif ($doctorVisitEmailSent ?? false) {
                    Session::flash('success', 'Cita completada. Correo enviado al médico ✅ (no se pudo enviar al paciente)');
                } else {
                    Session::flash('success', 'Cita marcada como completada.');
                }
            } elseif ($newStatus === 'cancelled') {
                if (($cancelEmailSent ?? false) && ($doctorCancelEmailSent ?? false)) {
                    Session::flash('success', 'Cita anulada. Correos de notificación enviados al paciente y al médico ✅');
                } elseif ($cancelEmailSent ?? false) {
                    Session::flash('success', 'Cita anulada. Correo enviado al paciente ✅ (no se pudo enviar al médico)');
                } elseif ($doctorCancelEmailSent ?? false) {
                    Session::flash('success', 'Cita anulada. Correo enviado al médico ✅ (no se pudo enviar al paciente)');
                } else {
                    Session::flash('success', 'Cita anulada.');
                }
            } else {
                Session::flash('success', 'Estado de la cita actualizado correctamente.');
            }

        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar el estado.');
        }

        // Use HTTP_REFERER only if it's a relative path to avoid double base_url concatenation.
        // View::redirect() already prepends base_url, so passing a full URL (HTTP_REFERER) would corrupt it.
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $appConfig = require CONFIG_PATH . '/app.php';
        $baseUrl = rtrim($appConfig['base_url'], '/');
        if (!empty($referer) && str_starts_with($referer, $baseUrl)) {
            // Extract only the path portion
            $redirectPath = substr($referer, strlen($baseUrl));
            View::redirect($redirectPath ?: '/admin/appointments');
        } else {
            View::redirect('/admin/appointments');
        }
    }

    public function delete(string $id)
    {
        Auth::requirePermission('appointments_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/appointments');
        }

        try {
            // Check if exists
            $appointment = $this->appointmentModel->findById((int)$id);
            if ($appointment) {
                // Free slot if exists
                $availabilityModel = new Availability();
                $slot = $availabilityModel->findOneWhere("notes = ?", ["Cita #{$id}"]);
                if ($slot) {
                    $availabilityModel->markAsAvailable((int)$slot['id']);
                }

                $this->appointmentModel->delete((int)$id);
                
                // FASE 3: Registrar en auditoría
                \App\Helpers\Audit::log('delete', 'appointments', (int)$id, $appointment, null);
                
                Session::flash('success', 'Cita eliminada correctamente.');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'No se puede eliminar la cita porque tiene registros asociados.');
        }

        View::redirect('/admin/appointments');
    }

    public function attachment(string $id)
    {
        Auth::requirePermission('appointments_read');
        $appointment = $this->appointmentModel->findById((int)$id);
        if (!$appointment || empty($appointment['attachment_url'])) {
            Session::flash('error', 'Cita o archivo no encontrado.');
            View::redirect('/admin/appointments');
        }

        $appConfig = require CONFIG_PATH . '/app.php';
        
        // Remove 'uploads/' prefix if present in the database field
        $filename = str_replace('uploads/', '', $appointment['attachment_url']);
        $filePath = $appConfig['upload_dir'] . $filename;

        if (!file_exists($filePath)) {
            Session::flash('error', 'El archivo físico no se encontró en el servidor.');
            View::redirect('/admin/appointments');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($filePath);
        exit;
    }
    public function reschedule(string $id)
    {
        Auth::requirePermission('appointments_update');
        $appointment = $this->appointmentModel->getByIdWithDetails((int)$id);
        if (!$appointment) {
            Session::flash('error', 'Cita no encontrada.');
            View::redirect('/admin/appointments');
        }

        $settingsModel = new \App\Models\SystemSetting();
        $maxReschedules = (int)$settingsModel->get('max_reschedules', '1');
        
        if ($maxReschedules > 0 && ($appointment['reschedule_count'] ?? 0) >= $maxReschedules) {
            Session::flash('error', 'Esta cita ya ha alcanzado el límite máximo de reagendamientos permitidos.');
            View::redirect('/admin/appointments');
        }
        
        $hoursBefore = (int)$settingsModel->get('reschedule_hours_before', '24');
        if ($hoursBefore > 0) {
            $appointmentTime = strtotime($appointment['appointment_date']);
            $currentTime = time();
            $hoursDifference = ($appointmentTime - $currentTime) / 3600;
            
            if ($hoursDifference < $hoursBefore) {
                Session::flash('error', "Las citas solo pueden ser reagendadas con al menos {$hoursBefore} horas de anticipación.");
                View::redirect('/admin/appointments');
            }
        }

        $returnUrl = $_GET['return'] ?? 'appointments';

        View::render('admin.appointments.reschedule', [
            'title' => 'Reagendar Cita',
            'appointment' => $appointment,
            'returnUrl' => $returnUrl,
            'csrfToken' => Session::generateCsrf()
        ], 'admin');
    }

    public function updateSchedule(string $id)
    {
        Auth::requirePermission('appointments_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/appointments');
        }

        try {
            $appointment = $this->appointmentModel->getByIdWithDetails((int)$id);
            if (!$appointment) {
                Session::flash('error', 'Cita no encontrada.');
                View::redirect('/admin/appointments');
            }

            $availabilityId = (int)($_POST['availability_id'] ?? 0);
            $doctorId = (int)($_POST['doctor_id'] ?? 0);
            
            if (!$availabilityId || !$doctorId) {
                Session::flash('error', 'Debe seleccionar un médico y un nuevo horario.');
                View::redirect("/admin/appointments/reschedule/{$id}");
            }

            $availabilityModel = new Availability();
            $newSlot = $availabilityModel->getSlotById($availabilityId);
            
            if (!$newSlot || $newSlot['status'] !== 'available') {
                Session::flash('error', 'El horario seleccionado ya no está disponible.');
                View::redirect("/admin/appointments/reschedule/{$id}");
            }

            $db = \App\Helpers\Database::getInstance();
            $db->beginTransaction();

            try {
                // 1. Free old slot
                $oldSlot = $availabilityModel->findOneWhere("notes = ?", ["Cita #{$id}"]);
                if ($oldSlot) {
                    $availabilityModel->markAsAvailable((int)$oldSlot['id']);
                }

                // 2. Book new slot
                $db->execute(
                    "UPDATE doctor_availability SET status = 'booked', notes = ? WHERE id = ?",
                    ["Cita #{$id}", $availabilityId]
                );

                // 3. Update appointment
                $newDate = $newSlot['available_date'] . ' ' . $newSlot['start_time'];
                $db->execute(
                    "UPDATE appointments SET appointment_date = ?, doctor_id = ?, reschedule_count = reschedule_count + 1, reminder_sent = 0 WHERE id = ?",
                    [$newDate, $doctorId, (int)$id]
                );

                $db->commit();
                
                // Re-consultar la cita para obtener datos actualizados (nuevo doctor, nueva fecha)
                $updatedAppointment = $this->appointmentModel->getByIdWithDetails((int)$id);
                $emailSent = false;
                $doctorEmailSent = false;

                if ($updatedAppointment) {
                    $subject = "Reagendamiento de Cita Médica #" . str_pad($id, 5, '0', STR_PAD_LEFT);

                    // Correo al paciente
                    if (!empty($updatedAppointment['patient_email'])) {
                        $htmlBody = \App\Helpers\Mailer::buildRescheduleEmail($updatedAppointment);
                        try {
                            $emailSent = \App\Helpers\Mailer::queue($updatedAppointment['patient_email'], $subject, $htmlBody);
                        } catch (\Exception $e) {
                            error_log("Error enviando correo de reagendamiento al paciente: " . $e->getMessage());
                        }
                    }

                    // Correo al doctor
                    if (!empty($updatedAppointment['doctor_email'])) {
                        $doctorSubject = "Cita Reagendada en su Agenda #" . str_pad($id, 5, '0', STR_PAD_LEFT);
                        $doctorHtmlBody = \App\Helpers\Mailer::buildDoctorRescheduleEmail($updatedAppointment);
                        try {
                            $doctorEmailSent = \App\Helpers\Mailer::queue($updatedAppointment['doctor_email'], $doctorSubject, $doctorHtmlBody);
                        } catch (\Exception $e) {
                            error_log("Error enviando correo de reagendamiento al doctor: " . $e->getMessage());
                        }
                    }

                    // Notificaciones Push (FCM)
                    $fcmTitle = "🔄 Cita Reagendada";
                    
                    // Notificar al paciente
                    $patTokens = $db->fetchAll(
                        "SELECT DISTINCT fcm_token FROM api_tokens 
                         WHERE user_id = (SELECT user_id FROM patients WHERE id = ?) AND fcm_token IS NOT NULL",
                        [$updatedAppointment['patient_id']]
                    );
                    $patBody = "Tu cita médica con el Dr. {$updatedAppointment['doctor_name']} ha sido reagendada para el {$newDate}.";
                    foreach ($patTokens as $t) {
                        \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $patBody, ['type' => 'reschedule', 'appointment_id' => $id]);
                    }

                    // Notificar al doctor
                    $docTokens = $db->fetchAll(
                        "SELECT DISTINCT fcm_token FROM api_tokens 
                         WHERE user_id = (SELECT user_id FROM doctors WHERE id = ?) AND fcm_token IS NOT NULL",
                        [$doctorId]
                    );
                    $docBody = "El paciente {$updatedAppointment['patient_name']} fue reagendado a su agenda para el {$newDate}.";
                    foreach ($docTokens as $t) {
                        \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $docBody, ['type' => 'reschedule', 'appointment_id' => $id]);
                    }
                }

                if ($emailSent && $doctorEmailSent) {
                    Session::flash('success', 'Cita reagendada exitosamente. Correo enviado al paciente y al doctor ✅');
                } elseif ($emailSent) {
                    Session::flash('success', 'Cita reagendada exitosamente y correo de notificación enviado al paciente ✅');
                } elseif ($doctorEmailSent) {
                    Session::flash('success', 'Cita reagendada exitosamente. Correo enviado al doctor ✅');
                } else {
                    Session::flash('success', 'Cita reagendada exitosamente.');
                }
                $redirectUrl = $_POST['return_url'] ?? 'appointments';
                View::redirect('/admin/' . $redirectUrl);
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al reagendar la cita: ' . $e->getMessage());
            View::redirect("/admin/appointments/reschedule/{$id}");
        }
    }

    public function exportExcel()
    {
        Auth::requirePermission('appointments_read');
        $status = $_GET['status'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $filter = '';
        $params = [];
        
        if ($status) {
            if ($status === 'rescheduled') {
                $filter = "a.reschedule_count > 0";
            } else {
                $filter = "a.status = ?";
                $params[] = $status;
            }
        }

        if ($startDate && $endDate) {
            $filter .= ($filter ? " AND " : "") . "DATE(a.appointment_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $appointments = $this->appointmentModel->getAllWithDetails($filter, $params);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=citas_reporte_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF))); // BOM for UTF-8
        fputcsv($output, ['ID', 'Fecha', 'Hora', 'Paciente', 'Identificación', 'Teléfono', 'Médico', 'Especialidad', 'Estado']);
        
        $statusLabels = [
            'pending' => 'Pendiente', 'scheduled' => 'Agendada', 'agendada' => 'Agendada',
            'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada'
        ];
        
        foreach ($appointments as $app) {
            $appStatus = $statusLabels[$app['status']] ?? $app['status'];
            if (($app['reschedule_count'] ?? 0) > 0) {
                $appStatus = 'Reagendada';
            }
            fputcsv($output, [
                $app['id'],
                date('Y-m-d', strtotime($app['appointment_date'])),
                date('H:i', strtotime($app['appointment_date'])),
                $app['patient_name'],
                $app['patient_id_number'],
                $app['patient_phone'],
                $app['doctor_name'],
                $app['specialty_name'],
                $appStatus
            ]);
        }
        fclose($output);
        exit;
    }

    public function exportPdf()
    {
        Auth::requirePermission('appointments_read');
        $status = $_GET['status'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $filter = '';
        $params = [];
        
        if ($status) {
            if ($status === 'rescheduled') {
                $filter = "a.reschedule_count > 0";
            } else {
                $filter = "a.status = ?";
                $params[] = $status;
            }
        }

        if ($startDate && $endDate) {
            $filter .= ($filter ? " AND " : "") . "DATE(a.appointment_date) BETWEEN ? AND ?";
            $params[] = $startDate;
            $params[] = $endDate;
        }

        $appointments = $this->appointmentModel->getAllWithDetails($filter, $params);
        
        $filters = [];
        if ($status) {
            $filters['status'] = $status;
        }
        if ($startDate && $endDate) {
            $filters['date_range'] = date('d/m/Y', strtotime($startDate)) . ' al ' . date('d/m/Y', strtotime($endDate));
        }

        $pdfGen = new \App\Helpers\PdfGenerator();
        $pdfContent = $pdfGen->generateAppointmentsReportPdf($appointments, $filters);

        $filename = 'reporte_citas_' . date('Y-m-d_His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdfContent;
        exit;
    }
}
