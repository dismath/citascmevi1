<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Validator;
use App\Models\Specialty;
use App\Models\Doctor;
use App\Models\Availability;
use App\Models\SystemSetting;
use App\Models\Appointment;

class BookingController
{
    private Specialty $specialtyModel;
    private Doctor $doctorModel;
    private Availability $availabilityModel;
    private SystemSetting $settingsModel;
    private Appointment $appointmentModel;

    public function __construct()
    {
        $this->specialtyModel = new Specialty();
        $this->doctorModel = new Doctor();
        $this->availabilityModel = new Availability();
        $this->settingsModel = new SystemSetting();
        $this->appointmentModel = new Appointment();
        Session::start();
    }

    public function index()
    {
        // Reset wizard session data when starting over
        Session::remove('booking_data');
        
        $specialties = $this->specialtyModel->getAllGrouped();
        
        View::render('booking.step1', [
            'title' => 'Seleccione Especialidad o Servicio',
            'specialties' => $specialties
        ]);
    }

    public function step(string $stepNumber)
    {
        $bookingData = Session::get('booking_data', []);

        switch ($stepNumber) {
            case '2':
                if (empty($bookingData['specialty_id'])) {
                    View::redirect('/booking/step/1');
                }
                $specialty = $this->specialtyModel->findById((int)$bookingData['specialty_id']);

                View::render('booking.step2', [
                    'title'      => 'Seleccione Fecha',
                    'specialty'  => $specialty,
                ]);
                break;

            case '3':
                if (empty($bookingData['availability_id']) || empty($bookingData['doctor_id'])) {
                    View::redirect('/booking/step/2');
                }
                $slot    = $this->availabilityModel->getSlotById((int)$bookingData['availability_id']);
                $doctor  = $this->doctorModel->getWithProfile((int)$bookingData['doctor_id']);
                $specialty = $this->specialtyModel->findById((int)$bookingData['specialty_id']);

                View::render('booking.step3_confirm', [
                    'title'     => 'Confirmar Fecha y Médico',
                    'slot'      => $slot,
                    'doctor'    => $doctor,
                    'specialty' => $specialty,
                ]);
                break;


            case '4':
                if (empty($bookingData['availability_id'])) {
                    View::redirect('/booking/step/3');
                }
                $slot = $this->availabilityModel->getSlotById((int)$bookingData['availability_id']);
                $doctor = $this->doctorModel->findById((int)$bookingData['doctor_id']);
                $specialty = $this->specialtyModel->findById((int)$bookingData['specialty_id']);
                
                View::render('booking.step4', [
                    'title' => 'Datos del Paciente',
                    'slot' => $slot,
                    'doctor' => $doctor,
                    'specialty' => $specialty
                ]);
                break;

            case '5':
                if (empty($bookingData['patient'])) {
                    View::redirect('/booking/step/4');
                }
                $doctor = $this->doctorModel->getWithProfile((int)$bookingData['doctor_id']);
                $slot = $this->availabilityModel->getSlotById((int)$bookingData['availability_id']);
                $bankInfo = $this->settingsModel->getBankInfo();
                
                $specialty = $this->specialtyModel->findById((int)$bookingData['specialty_id']);
                $isEspecialidad = ($specialty && $specialty['catalog_type_code'] === 'ESPEC');

                // Si la especialidad es laboratorio o imagenología, el precio es 0 por defecto (se define el catálogo)
                // O si el médico no muestra precio, pasar 0
                $fee = $this->doctorModel->getConsultationFee((int)$bookingData['doctor_id']);
                $showFee = $this->doctorModel->shouldShowFee((int)$bookingData['doctor_id']);
                
                // Si no es Especialidad, forzamos ocultar el costo numérico
                if (!$isEspecialidad) {
                    $showFee = false;
                }
                
                View::render('booking.step5', [
                    'title' => 'Pago e Confirmación',
                    'doctor' => $doctor,
                    'slot' => $slot,
                    'patient' => $bookingData['patient'],
                    'bankInfo' => $bankInfo,
                    'fee' => $fee,
                    'showFee' => $showFee,
                    'isEspecialidad' => $isEspecialidad
                ]);
                break;

            default:
                View::redirect('/booking');
        }
    }

    public function process()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/booking');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido. Intente de nuevo.');
            View::redirect('/booking');
        }

        $action = $_POST['action'] ?? '';
        $bookingData = Session::get('booking_data', []);

        if ($action === 'set_specialty') {
            $bookingData['specialty_id'] = (int)$_POST['specialty_id'];
            Session::set('booking_data', $bookingData);
            View::redirect('/booking/step/2');
        } 
        elseif ($action === 'set_datetime_doctor') {
            // New calendar flow: doctor + slot selected at the same time from the modal
            $bookingData['doctor_id']      = (int)$_POST['doctor_id'];
            $bookingData['availability_id']= (int)$_POST['availability_id'];
            $bookingData['date']           = $_POST['date'] ?? '';
            Session::set('booking_data', $bookingData);
            View::redirect('/booking/step/3');
        }
        elseif ($action === 'set_doctor') {
            $bookingData['doctor_id'] = (int)$_POST['doctor_id'];
            Session::set('booking_data', $bookingData);
            View::redirect('/booking/step/3');
        }
        elseif ($action === 'set_datetime') {
            $bookingData['availability_id'] = (int)$_POST['availability_id'];
            $bookingData['date'] = $_POST['date'];
            Session::set('booking_data', $bookingData);
            View::redirect('/booking/step/4');
        }
        elseif ($action === 'set_patient') {
            $email = trim($_POST['email'] ?? '');
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Session::flash('error', 'El formato del correo electrónico no es válido.');
                View::redirect('/booking/step/4');
            }

            $idNumber = trim($_POST['id_number'] ?? '');
            $documentType = $_POST['document_type'] ?? 'cedula';

            // Solo validar cédula/RUC si el tipo de documento es cedula o ruc
            if (in_array($documentType, ['cedula', 'ruc']) && !empty($idNumber) && !Validator::isValidEcuadorianId($idNumber)) {
                Session::flash('error', 'El número de Cédula o RUC ingresado no es válido.');
                View::redirect('/booking/step/4');
            }

            // Validar teléfono: solo números
            $phoneVal = trim($_POST['phone'] ?? '');
            if (!empty($phoneVal) && !Validator::isValidPhone($phoneVal)) {
                Session::flash('error', 'El teléfono debe contener solo números.');
                View::redirect('/booking/step/4');
            }

            // Validar nombre: solo letras y números
            $nameVal = Validator::capitalizeWords(trim($_POST['name'] ?? ''));
            if (!empty($nameVal) && !Validator::isValidPatientName($nameVal, true)) {
                Session::flash('error', 'El nombre contiene caracteres no permitidos.');
                View::redirect('/booking/step/4');
            }

            // Validar dirección: solo texto y números
            $addressVal = Validator::capitalizeFirst(trim($_POST['address'] ?? ''));
            if (!empty($addressVal) && !Validator::isValidAddress($addressVal)) {
                Session::flash('error', 'La dirección contiene caracteres no permitidos.');
                View::redirect('/booking/step/4');
            }

            $docCodeMap = [
                'ruc' => '04',
                'cedula' => '05',
                'pasaporte' => '06',
                'consumidor_final' => '07',
                'id_extranjera' => '08'
            ];
            $docCode = $docCodeMap[$documentType] ?? '05';

            $bookingData['patient'] = [
                'name'      => $nameVal,
                'id_number' => $idNumber,
                'document_type_code' => $docCode,
                'phone'     => $phoneVal,
                'email'     => $email,
                'address'   => $addressVal
            ];
            Session::set('booking_data', $bookingData);
            View::redirect('/booking/step/5');
        }
        elseif ($action === 'finalize') {
            // ---------------------------------------------------------------
            // FIX-SEC-04: Validación completa del archivo subido (Imagen o PDF)
            // ---------------------------------------------------------------
            $attachmentUrl = null;
            if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadError = $_FILES['payment_receipt']['error'];
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño máximo permitido por el servidor.',
                        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño máximo permitido en el formulario.',
                        UPLOAD_ERR_PARTIAL    => 'El archivo se subió solo parcialmente. Intente nuevamente.',
                        UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: falta el directorio temporal de subidas.',
                        UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo en el disco.',
                        UPLOAD_ERR_EXTENSION  => 'Error del servidor: una extensión del sistema detuvo la subida.'
                    ];
                    $msg = $errorMessages[$uploadError] ?? 'Error desconocido al subir el archivo (código ' . $uploadError . ').';
                    Session::flash('error', $msg);
                    View::redirect('/booking/step/5');
                    return;
                }

                $appConfig = require CONFIG_PATH . '/app.php';
                $uploadDir = $appConfig['upload_dir'];

                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                try {
                    $attachmentUrl = $this->validateAndMoveUpload(
                        $_FILES['payment_receipt'],
                        $uploadDir,
                        $appConfig
                    );
                } catch (\Exception $uploadEx) {
                    Session::flash('error', $uploadEx->getMessage());
                    View::redirect('/booking/step/5');
                    return;
                }
            }

            try {
                $slot = $this->availabilityModel->getSlotById((int)$bookingData['availability_id']);
                
                $isAvailable = $slot && $slot['status'] === 'available';
                $sessionId = session_id();
                
                $isLockedByMe = $isAvailable && 
                                $slot['locked_until'] !== null && 
                                strtotime($slot['locked_until']) > time() && 
                                $slot['locked_by_session'] === $sessionId;
                                
                $isFree = $isAvailable && 
                          ($slot['locked_until'] === null || strtotime($slot['locked_until']) < time());
                          
                if (!$isAvailable || (!$isFree && !$isLockedByMe)) {
                    throw new \Exception("El horario seleccionado ya no está disponible.");
                }

                $token = bin2hex(random_bytes(32)); // 64 caracteres hex (256 bits entropía)

                $appointmentData = [
                    'doctor_id'        => $bookingData['doctor_id'],
                    'specialty_id'     => $bookingData['specialty_id'],
                    'appointment_date' => $slot['available_date'] . ' ' . $slot['start_time'],
                    'attachment_url'   => $attachmentUrl,
                    'notes'            => trim($_POST['notes'] ?? ''),
                    'confirmation_token' => $token,
                    'token_expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours'))
                ];

                $bookingResult = $this->appointmentModel->createFull(
                    $appointmentData,
                    $bookingData['patient'],
                    (int)$bookingData['availability_id']
                );

                $appointmentId = is_array($bookingResult) ? (int)$bookingResult['appointment_id'] : (int)$bookingResult;
                $userId        = is_array($bookingResult) ? ($bookingResult['user_id'] ?? null) : null;
                $username      = is_array($bookingResult) ? ($bookingResult['username'] ?? null) : null;
                $tempPassword  = is_array($bookingResult) ? ($bookingResult['temp_password'] ?? null) : null;
                $userCreated   = is_array($bookingResult) ? ($bookingResult['user_created'] ?? false) : false;

                // Enviar inmediatamente correo con credenciales de acceso y detalles de la cita al finalizar el registro
                $patientEmail = trim($bookingData['patient']['email'] ?? '');
                if (!empty($patientEmail)) {
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

                        // Obtener nombres para el resumen de cita en el correo
                        $doctorName    = '';
                        $specialtyName = '';
                        if (!empty($bookingData['doctor_id'])) {
                            try {
                                $doc = $this->doctorModel->findById((int)$bookingData['doctor_id']);
                                if ($doc) $doctorName = $doc['name'] ?? '';
                            } catch (\Throwable $t) {}
                        }
                        if (!empty($bookingData['specialty_id'])) {
                            try {
                                $spec = $this->specialtyModel->findById((int)$bookingData['specialty_id']);
                                if ($spec) $specialtyName = $spec['name'] ?? '';
                            } catch (\Throwable $t) {}
                        }

                        // Enviar UN SOLO correo al paciente: Bienvenida + Credenciales de Acceso + Resumen de Cita
                        $mailData = [
                            'patient_name'     => $bookingData['patient']['name'] ?? 'Paciente',
                            'appointment_id'   => $appointmentId,
                            'doctor_name'      => $doctorName,
                            'specialty_name'   => $specialtyName,
                            'appointment_date' => $slot['available_date'] ?? '',
                            'start_time'       => $slot['start_time'] ?? '',
                            'username'         => $username ?: $patientEmail,
                            'password'         => !empty($tempPassword) ? $tempPassword : '',
                            'portal_url'       => $portalUrl,
                            'clinic_name'      => $clinicInfo['name'],
                            'clinic_phone'     => $clinicInfo['phone'],
                            'clinic_email'     => $clinicInfo['email'],
                            'is_new_user'      => $userCreated,
                        ];

                        \App\Helpers\Mailer::sendPatientBookingNotification($patientEmail, $mailData);
                    } catch (\Throwable $mailEx) {
                        error_log("[BookingController] Error enviando correo de registro al paciente: " . $mailEx->getMessage());
                    }
                }

                Session::remove('booking_data');

                // FIX-SEC-05: Guardar el token en sesión para autorizar la vista
                Session::set('last_confirmed_token', $token);

                // Notificar al doctor por FCM (Push)
                $db = \App\Helpers\Database::getInstance();
                $docTokens = $db->fetchAll(
                    "SELECT DISTINCT fcm_token FROM api_tokens 
                     WHERE user_id = (SELECT user_id FROM doctors WHERE id = ?) AND fcm_token IS NOT NULL",
                    [$appointmentData['doctor_id']]
                );
                
                $fcmTitle = "🟢 Nueva Cita Médica";
                $fcmBody = "El paciente {$bookingData['patient']['name']} ha agendado una nueva cita.";
                
                foreach ($docTokens as $t) {
                    \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $fcmBody, ['type' => 'new_appointment', 'appointment_id' => $appointmentId]);
                }

                View::redirect("/booking/confirmation/{$token}");

            } catch (\Exception $e) {
                Session::flash('error', 'Error al agendar la cita: ' . $e->getMessage());
                View::redirect('/booking/step/5');
            }
        }
    }

    /**
     * FIX-SEC-04: Valida y mueve un archivo subido de forma segura.
     * Verifica: tamaño, extensión (whitelist), tipo MIME real (finfo) y
     * genera un nombre de archivo aleatorio no predecible.
     *
     * @throws \Exception si el archivo no pasa alguna validación
     */
    private function validateAndMoveUpload(array $file, string $uploadDir, array $config): string
    {
        // 1. Verificar tamaño
        $maxSize = $config['max_upload_size'] ?? 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new \Exception('El archivo excede el tamaño máximo permitido (5 MB).');
        }

        // 2. Extraer extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // 3. Verificar tipo MIME REAL del contenido
        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file['tmp_name']);
        }

        if (empty($ext)) {
            $mimeToExt = [
                'image/jpeg'        => 'jpg',
                'image/pjpeg'       => 'jpg',
                'image/jpg'         => 'jpg',
                'image/png'         => 'png',
                'image/x-png'       => 'png',
                'image/webp'        => 'webp',
                'image/gif'         => 'gif',
                'application/pdf'   => 'pdf',
                'application/x-pdf' => 'pdf'
            ];
            $ext = $mimeToExt[$mimeType] ?? 'jpg';
        }

        $allowedExtensions = $config['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'pdf', 'gif', 'webp'];
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new \Exception('Tipo de archivo no permitido. Solo se aceptan: ' . implode(', ', $allowedExtensions) . '.');
        }

        $allowedMimes = $config['allowed_mimes'] ?? [
            'image/jpeg', 'image/pjpeg', 'image/jpg',
            'image/png', 'image/x-png',
            'image/gif', 'image/webp',
            'application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf'
        ];

        if (!in_array($mimeType, $allowedMimes, true) && !in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            throw new \Exception('El contenido del archivo no corresponde a una imagen o documento PDF válido.');
        }

        // 4. Asegurar directorio de destino
        $normalizedUploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($normalizedUploadDir)) {
            @mkdir($normalizedUploadDir, 0777, true);
        }

        // 5. Nombre único no predecible
        $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = $normalizedUploadDir . $newFilename;

        // 6. Almacenamiento seguro
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
        $saved = false;

        // Intentar compresión y optimización si es JPEG o PNG
        if ($isImage && in_array($mimeType, ['image/jpeg', 'image/pjpeg', 'image/png']) && function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng')) {
            $image = null;
            if ($mimeType === 'image/jpeg' || $mimeType === 'image/pjpeg') {
                $image = @imagecreatefromjpeg($file['tmp_name']);
            } elseif ($mimeType === 'image/png') {
                $image = @imagecreatefrompng($file['tmp_name']);
            }

            if ($image !== false && $image !== null) {
                $width  = imagesx($image);
                $height = imagesy($image);
                $maxWidth = 1600;

                if ($width > $maxWidth) {
                    $newWidth  = $maxWidth;
                    $newHeight = (int)floor($height * ($maxWidth / $width));
                    $tmpImage  = imagecreatetruecolor($newWidth, $newHeight);

                    if ($mimeType === 'image/png') {
                        imagealphablending($tmpImage, false);
                        imagesavealpha($tmpImage, true);
                    }
                    imagecopyresampled($tmpImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $tmpImage;
                }

                if ($mimeType === 'image/png') {
                    $saved = @imagepng($image, $destination, 7);
                } else {
                    $saved = @imagejpeg($image, $destination, 80);
                }
                imagedestroy($image);
            }
        }

        // Fallback robusto si no se procesó con GD o si falló
        if (!$saved || !file_exists($destination) || filesize($destination) === 0) {
            if (!@move_uploaded_file($file['tmp_name'], $destination)) {
                if (!@copy($file['tmp_name'], $destination)) {
                    throw new \Exception('No se pudo guardar el archivo en el servidor. Verifique los permisos.');
                }
            }
        }

        if (!file_exists($destination) || filesize($destination) === 0) {
            throw new \Exception('El archivo no pudo guardarse correctamente en el servidor.');
        }

        return 'uploads/' . $newFilename;
    }

    /**
     * Confirmación de cita mediante token criptográfico único.
     */
    public function confirmation(string $token)
    {
        $confirmedToken = Session::get('last_confirmed_token');

        // Bloquear acceso si el token no corresponde a la reserva de esta sesión
        // O si intentan enumerar tokens
        if ($confirmedToken === null || $token !== $confirmedToken) {
            View::redirect('/');
        }

        $appointment = $this->appointmentModel->getByTokenWithDetails($token);
        
        if (!$appointment) {
            View::redirect('/');
        }

        // Verificar caducidad o si ya fue confirmada
        if ($appointment['token_used'] == 1 || strtotime($appointment['token_expires_at']) < time()) {
            Session::flash('error', 'El enlace de confirmación ha expirado o ya no es válido.');
            View::redirect('/');
        }

        // Marcar el token como usado (solo se puede usar para llegar a esta vista una vez para confirmar la intención)
        // En un flujo real esto podría activarse con un botón "Confirmar Cita", pero aquí se marca al cargar.
        $this->appointmentModel->update((int)$appointment['id'], [
            'token_used' => 1
        ]);

        // Renovar en sesión para permitir impresión posterior si es necesario
        Session::set('allow_print_appointment_id', $appointment['id']);

        $settingsModel = new \App\Models\SystemSetting();
        $companyAddress = $settingsModel->get('company_address', 'Dirección no configurada');
        $companyPhone = $settingsModel->get('company_phone', 'Teléfono no configurado');

        View::render('booking.confirmation', [
            'title' => 'Cita Confirmada',
            'appointment' => $appointment,
            'companyAddress' => $companyAddress,
            'companyPhone' => $companyPhone
        ]);
    }

    public function printAppointment(string $token)
    {
        // En la impresión validamos con el ID que guardamos tras consumir el token
        $appointment = $this->appointmentModel->getByTokenWithDetails($token);
        
        if (!$appointment) {
            View::redirect('/');
        }

        $allowPrintId = Session::get('allow_print_appointment_id');
        if ($allowPrintId === null || (int)$appointment['id'] !== (int)$allowPrintId) {
            View::redirect('/');
        }

        $settingsModel = new \App\Models\SystemSetting();
        $companyAddress = $settingsModel->get('company_address', 'Dirección no configurada');
        $companyPhone = $settingsModel->get('company_phone', 'Teléfono no configurado');

        View::render('booking.confirmation', [
            'title'       => 'Confirmación de Cita',
            'appointment' => $appointment,
            'companyAddress' => $companyAddress,
            'companyPhone' => $companyPhone
        ]);
    }

    /**
     * Endpoint API para bloquear un turno temporalmente (Soft Booking)
     */
    public function lockSlot()
    {
        header('Content-Type: application/json');
        
        $slotId = (int)($_POST['availability_id'] ?? 0);
        if (!$slotId) {
            echo json_encode(['success' => false, 'message' => 'ID de turno no proporcionado']);
            exit;
        }

        $sessionId = session_id();
        $locked = $this->availabilityModel->lockSlot($slotId, $sessionId, 5); // 5 minutos
        
        if ($locked) {
            echo json_encode(['success' => true, 'message' => 'Turno bloqueado exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se pudo bloquear el turno (tal vez ya fue tomado)']);
        }
        exit;
    }
}
