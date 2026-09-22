<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;

class PatientController
{
    public function __construct()
    {
        Auth::requireRole(['patient', 'admin']);
    }

    private function getPatientId(): int
    {
        $db     = \App\Helpers\Database::getInstance();
        $userId = Session::get('user_id');

        // Primary: look up by user_id (always available, works even if email is NULL)
        if ($userId) {
            $patient = $db->fetch(
                "SELECT p.id FROM patients p WHERE p.user_id = ?",
                [(int)$userId]
            );
            if ($patient) {
                return (int)$patient['id'];
            }
        }

        // Fallback: look up by email (backwards compat)
        $userEmail = Session::get('user_email');
        if ($userEmail) {
            $patient = $db->fetch(
                "SELECT p.id FROM patients p JOIN users u ON p.user_id = u.id WHERE u.email = ?",
                [$userEmail]
            );
            if ($patient) {
                return (int)$patient['id'];
            }
        }

        return 0;
    }

    public function dashboard()
    {
        $patientId = $this->getPatientId();
        $db = \App\Helpers\Database::getInstance();

        // Todas las citas del paciente (registro histórico)
        $allAppointments = [];
        if ($patientId) {
            $allAppointments = $db->fetchAll(
                "SELECT a.*, d.name as doctor_name, s.name as specialty_name
                 FROM appointments a
                 JOIN doctors d ON a.doctor_id = d.id
                 JOIN specialties s ON d.specialty_id = s.id
                 WHERE a.patient_id = ? AND a.status IN ('pending', 'confirmed', 'in_progress', 'completed')
                 ORDER BY a.appointment_date DESC",
                [$patientId]
            );
        }

        View::render('patient.dashboard', [
            'title' => 'Portal del Paciente',
            'patientId' => $patientId,
            'allAppointments' => $allAppointments
        ], 'admin');
    }

    public function records()
    {
        $patientId = $this->getPatientId();
        $db = \App\Helpers\Database::getInstance();

        $history = null;
        $notes = [];
        $orders = [];

        if ($patientId) {
            $history = $db->fetch("SELECT * FROM medical_history WHERE patient_id = ?", [$patientId]);
            
            $notes = $db->fetchAll(
                "SELECT mn.*, d.name as doctor_name 
                 FROM medical_notes mn 
                 JOIN doctors d ON mn.doctor_id = d.id 
                 WHERE mn.patient_id = ? 
                 ORDER BY mn.created_at DESC", 
                [$patientId]
            );

            if ($history) {
                $history['family_history'] = \App\Helpers\Crypto::decrypt($history['family_history'] ?? null);
                $history['past_medical_history'] = \App\Helpers\Crypto::decrypt($history['past_medical_history'] ?? null);
                $history['allergies'] = \App\Helpers\Crypto::decrypt($history['allergies'] ?? null);
                $history['surgeries'] = \App\Helpers\Crypto::decrypt($history['surgeries'] ?? null);
                $history['chronic_diseases'] = \App\Helpers\Crypto::decrypt($history['chronic_diseases'] ?? null);
                $history['medications'] = \App\Helpers\Crypto::decrypt($history['medications'] ?? null);
            }

            foreach ($notes as &$note) {
                $note['chief_complaint'] = \App\Helpers\Crypto::decrypt($note['chief_complaint']);
                $note['diagnosis'] = \App\Helpers\Crypto::decrypt($note['diagnosis']);
                $note['treatment'] = \App\Helpers\Crypto::decrypt($note['treatment']);
                $note['clinical_notes'] = \App\Helpers\Crypto::decrypt($note['clinical_notes']);
            }
            unset($note);

            $orders = $db->fetchAll(
                "SELECT o.*, a.appointment_date
                 FROM orders o
                 JOIN appointments a ON o.appointment_id = a.id
                 WHERE o.patient_id = ?
                 ORDER BY o.created_at DESC",
                [$patientId]
            );
        }

        View::render('patient.records', [
            'title' => 'Mi Historial Clínico',
            'patientId' => $patientId,
            'history' => $history,
            'notes' => $notes,
            'orders' => $orders
        ], 'admin');
    }

    public function reschedule(string $id)
    {
        $patientId = $this->getPatientId();
        if (!$patientId) {
            Session::flash('error', 'No tiene un perfil de paciente.');
            View::redirect('/patient/dashboard');
        }

        $appointmentModel = new \App\Models\Appointment();
        $appointment = $appointmentModel->getByIdWithDetails((int)$id);

        if (!$appointment || $appointment['patient_id'] !== $patientId) {
            Session::flash('error', 'Cita no encontrada o no tiene permisos.');
            View::redirect('/patient/dashboard');
        }

        if (!in_array($appointment['status'], ['pending', 'confirmed'])) {
            Session::flash('error', 'Esta cita no puede ser reagendada porque ya fue completada o cancelada.');
            View::redirect('/patient/dashboard');
        }

        $settingsModel = new \App\Models\SystemSetting();
        $maxReschedules = (int)$settingsModel->get('max_reschedules', '1');
        
        if ($maxReschedules > 0 && ($appointment['reschedule_count'] ?? 0) >= $maxReschedules) {
            Session::flash('error', 'Esta cita ya ha alcanzado el límite máximo de reagendamientos permitidos.');
            View::redirect('/patient/dashboard');
        }
        
        $hoursBefore = (int)$settingsModel->get('reschedule_hours_before', '24');
        if ($hoursBefore > 0) {
            $appointmentTime = strtotime($appointment['appointment_date']);
            $currentTime = time();
            $hoursDifference = ($appointmentTime - $currentTime) / 3600;
            
            if ($hoursDifference < $hoursBefore) {
                Session::flash('error', "Las citas solo pueden ser reagendadas con al menos {$hoursBefore} horas de anticipación.");
                View::redirect('/patient/dashboard');
            }
        }

        View::render('patient.reschedule', [
            'title' => 'Reagendar Cita',
            'appointment' => $appointment,
            'csrfToken' => Session::generateCsrf()
        ], 'admin');
    }

    public function updateSchedule(string $id)
    {
        $patientId = $this->getPatientId();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/patient/dashboard');
        }

        $appointmentModel = new \App\Models\Appointment();
        $appointment = $appointmentModel->getByIdWithDetails((int)$id);

        if (!$appointment || $appointment['patient_id'] !== $patientId) {
            Session::flash('error', 'Cita no encontrada o no tiene permisos.');
            View::redirect('/patient/dashboard');
        }

        $availabilityId = (int)($_POST['availability_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        if (!$availabilityId || !$doctorId) {
            Session::flash('error', 'Debe seleccionar un médico y un horario válido.');
            View::redirect("/patient/appointments/reschedule/{$id}");
        }

        $availabilityModel = new \App\Models\Availability();
        $newSlot = $availabilityModel->getSlotById($availabilityId);
        
        $isAvailable = $newSlot && $newSlot['status'] === 'available';
        $sessionId = session_id();
        
        $isLockedByMe = $isAvailable && 
                        $newSlot['locked_until'] !== null && 
                        strtotime($newSlot['locked_until']) > time() && 
                        $newSlot['locked_by_session'] === $sessionId;
                        
        $isFree = $isAvailable && 
                  ($newSlot['locked_until'] === null || strtotime($newSlot['locked_until']) < time());
                  
        if (!$isAvailable || (!$isFree && !$isLockedByMe)) {
            Session::flash('error', 'El horario seleccionado ya no está disponible.');
            View::redirect("/patient/appointments/reschedule/{$id}");
        }

        $db = \App\Helpers\Database::getInstance();
        $db->beginTransaction();

        try {
            // 1. Liberar turno anterior
            $oldSlot = $availabilityModel->findOneWhere("notes = ?", ["Cita #{$id}"]);
            if ($oldSlot) {
                $availabilityModel->markAsAvailable((int)$oldSlot['id']);
            }

            // 2. Ocupar nuevo turno
            $db->execute(
                "UPDATE doctor_availability SET status = 'booked', notes = ?, locked_until = NULL, locked_by_session = NULL WHERE id = ?",
                ["Cita #{$id}", $availabilityId]
            );

            // 3. Actualizar cita con nuevo médico y fecha
            $newDate = $newSlot['available_date'] . ' ' . $newSlot['start_time'];
            $db->execute(
                "UPDATE appointments SET appointment_date = ?, doctor_id = ?, reschedule_count = reschedule_count + 1, reminder_sent = 0 WHERE id = ?",
                [$newDate, $doctorId, (int)$id]
            );

            $db->commit();
            
            // Enviar correo de confirmación de reagendamiento
            // Re-consultar la cita para obtener datos actualizados (nuevo doctor, nueva fecha)
            $updatedAppointment = $appointmentModel->getByIdWithDetails((int)$id);
            $emailSent = false;
            $doctorEmailSent = false;
            
            if ($updatedAppointment) {
                // Notificar al paciente
                if (!empty($updatedAppointment['patient_email'])) {
                    $htmlBody = \App\Helpers\Mailer::buildRescheduleEmail($updatedAppointment);
                    $subject = "Reagendamiento de Cita Médica #" . str_pad($id, 5, '0', STR_PAD_LEFT);
                    
                    try {
                        $emailSent = \App\Helpers\Mailer::queue($updatedAppointment['patient_email'], $subject, $htmlBody);
                    } catch (\Exception $e) {
                        error_log("Error enviando correo de reagendamiento al paciente: " . $e->getMessage());
                    }
                }
                
                // Notificar al doctor por Email
                if (!empty($updatedAppointment['doctor_email'])) {
                    $doctorSubject = "Cita Reagendada en su Agenda #" . str_pad($id, 5, '0', STR_PAD_LEFT);
                    $doctorHtmlBody = \App\Helpers\Mailer::buildDoctorRescheduleEmail($updatedAppointment);
                    
                    try {
                        $doctorEmailSent = \App\Helpers\Mailer::queue($updatedAppointment['doctor_email'], $doctorSubject, $doctorHtmlBody);
                    } catch (\Exception $e) {
                        error_log("Error enviando correo de reagendamiento al doctor: " . $e->getMessage());
                    }
                }

                // Notificar al doctor por FCM (Push)
                $docTokens = $db->fetchAll(
                    "SELECT DISTINCT fcm_token FROM api_tokens 
                     WHERE user_id = (SELECT user_id FROM doctors WHERE id = ?) AND fcm_token IS NOT NULL",
                    [$doctorId]
                );
                
                $fcmTitle = "🔄 Cita Reagendada";
                $fcmBody = "El paciente {$updatedAppointment['patient_name']} reagendó su cita para el {$newDate}.";
                
                foreach ($docTokens as $t) {
                    \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $fcmBody, ['type' => 'reschedule', 'appointment_id' => $id]);
                }
            }

            if ($emailSent && $doctorEmailSent) {
                Session::flash('success', 'Tu cita ha sido reagendada exitosamente. Correo enviado a ti y al doctor ✅');
            } elseif ($emailSent) {
                Session::flash('success', 'Tu cita ha sido reagendada exitosamente y se te ha enviado un correo de confirmación ✅');
            } else {
                Session::flash('success', 'Tu cita ha sido reagendada exitosamente.');
            }
            View::redirect('/patient/dashboard');
        } catch (\Exception $e) {
            $db->rollback();
            Session::flash('error', 'Error al reagendar la cita: ' . $e->getMessage());
            View::redirect("/patient/appointments/reschedule/{$id}");
        }
    }

    public function attachment(int $id)
    {
        $patientId = $this->getPatientId();
        $db = \App\Helpers\Database::getInstance();
        $appointment = $db->fetch("SELECT * FROM appointments WHERE id = ? AND patient_id = ?", [$id, $patientId]);

        if (!$appointment || empty($appointment['attachment_url'])) {
            http_response_code(404);
            echo '<h1>404 - Documento no encontrado</h1>';
            exit;
        }

        $appConfig = require CONFIG_PATH . '/app.php';
        $filename = str_replace('uploads/', '', $appointment['attachment_url']);
        $filePath = rtrim($appConfig['upload_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo '<h1>404 - Archivo físico no encontrado en el servidor</h1>';
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        readfile($filePath);
        exit;
    }
}
