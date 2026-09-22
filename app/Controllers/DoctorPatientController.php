<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Models\Appointment;
use App\Models\ClinicalAudit;

class DoctorPatientController
{
    public function __construct()
    {
        Auth::requireRole(['doctor', 'admin']);
    }

    private function getDoctorId(): int
    {
        $db     = \App\Helpers\Database::getInstance();
        $userId = Session::get('user_id');

        // Primary: look up by user_id (always available, works even if email is NULL)
        if ($userId) {
            $doctor = $db->fetch(
                "SELECT d.id FROM doctors d WHERE d.user_id = ?",
                [(int)$userId]
            );
            if ($doctor) {
                return (int)$doctor['id'];
            }
        }

        // Fallback: look up by email (for backwards compat)
        $userEmail = Session::get('user_email');
        if ($userEmail) {
            $doctor = $db->fetch(
                "SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.email = ?",
                [$userEmail]
            );
            if ($doctor) {
                return (int)$doctor['id'];
            }
        }

        return 0;
    }

    public function index()
    {
        $doctorId = $this->getDoctorId();
        
        $db = \App\Helpers\Database::getInstance();
        $appointments = $db->fetchAll(
            "SELECT a.*, p.name as patient_name, p.id_number, p.phone,
                    (SELECT COUNT(*) FROM medical_notes mn WHERE mn.appointment_id = a.id) as has_notes
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             WHERE a.doctor_id = ? AND a.status IN ('confirmed', 'in_progress', 'completed')
             ORDER BY a.appointment_date DESC",
            [$doctorId]
        );

        View::render('doctor.appointments.index', [
            'title' => 'Mis Citas',
            'appointments' => $appointments
        ], 'admin');
    }

    public function attendedPatients()
    {
        $doctorId = $this->getDoctorId();
        $db = \App\Helpers\Database::getInstance();
        
        $patients = $db->fetchAll(
            "SELECT DISTINCT p.id, p.name, p.id_number, p.phone,
                    (SELECT MAX(created_at) FROM medical_notes WHERE patient_id = p.id AND doctor_id = ?) as last_visit
             FROM patients p
             JOIN medical_notes mn ON mn.patient_id = p.id
             WHERE mn.doctor_id = ?
             ORDER BY last_visit DESC",
            [$doctorId, $doctorId]
        );

        View::render('doctor.patients.attended', [
            'title' => 'Mis Pacientes Atendidos',
            'patients' => $patients
        ], 'admin');
    }

    public function startAppointment(int $appointmentId)
    {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            if ($isAjax) {
                View::json(['success' => false, 'message' => 'Token de seguridad inválido.'], 400);
            }
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/doctor/appointments');
        }

        $doctorId = $this->getDoctorId();
        $db = \App\Helpers\Database::getInstance();
        
        $appointment = $db->fetch("SELECT * FROM appointments WHERE id = ? AND doctor_id = ?", [$appointmentId, $doctorId]);
        
        if ($appointment && in_array($appointment['status'], ['confirmed', 'agendada', 'in_progress'])) {
            if ($appointment['status'] !== 'in_progress') {
                $db->query(
                    "UPDATE appointments SET status = 'in_progress' WHERE id = ?",
                    [$appointmentId]
                );
            }

            if ($isAjax) {
                View::json([
                    'success' => true,
                    'message' => 'Atención médica iniciada.',
                    'appointment_id' => $appointmentId,
                    'patient_id' => (int)$appointment['patient_id']
                ]);
            }

            View::redirect('/doctor/patients/' . $appointment['patient_id'] . '/history?tab=atencion');
        } else {
            if ($isAjax) {
                View::json(['success' => false, 'message' => 'No se pudo acceder a la cita. Verifique el estado de la cita.'], 400);
            }
            Session::flash('error', 'No se pudo acceder a la cita. Verifique el estado de la cita.');
            View::redirect('/doctor/appointments');
        }
    }

    public function history(string $patientId)
    {
        $patientId = (int)$patientId;
        $doctorId = $this->getDoctorId();
        $db = \App\Helpers\Database::getInstance();

        // FIX-SEC-10: Prevenir IDOR - Verificar que el paciente tiene consultas con este doctor
        $hasAccess = \App\Helpers\Auth::hasRole('admin') || (bool)$db->fetch(
            "SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1",
            [$patientId, $doctorId]
        );

        if (!$hasAccess) {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado</h1><p>No tiene permisos para ver el historial de este paciente.</p>';
            exit;
        }

        $patient = $db->fetch("SELECT * FROM patients WHERE id = ?", [$patientId]);
        
        // Base medical history
        $history = $db->fetch("SELECT * FROM medical_history WHERE patient_id = ?", [$patientId]);
        
        // Previous notes
        $notes = $db->fetchAll(
            "SELECT mn.*, d.name as doctor_name, a.appointment_date, a.attachment_url 
             FROM medical_notes mn 
             JOIN doctors d ON mn.doctor_id = d.id 
             LEFT JOIN appointments a ON mn.appointment_id = a.id
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

        // Check for active appointment today
        $date = date('Y-m-d');
        $activeAppointment = $db->fetch(
            "SELECT a.*, (SELECT COUNT(*) FROM medical_notes mn WHERE mn.appointment_id = a.id) as has_notes
             FROM appointments a 
             WHERE a.patient_id = ? AND a.doctor_id = ? AND DATE(a.appointment_date) = ? AND a.status IN ('confirmed', 'agendada', 'in_progress') 
             ORDER BY a.appointment_date ASC LIMIT 1",
            [$patientId, $doctorId, $date]
        );

        // Si no hay cita hoy, buscar si hay alguna en progreso para este paciente y doctor
        if (!$activeAppointment) {
            $activeAppointment = $db->fetch(
                "SELECT a.*, (SELECT COUNT(*) FROM medical_notes mn WHERE mn.appointment_id = a.id) as has_notes
                 FROM appointments a 
                 WHERE a.patient_id = ? AND a.doctor_id = ? AND a.status = 'in_progress' 
                 ORDER BY a.appointment_date DESC LIMIT 1",
                [$patientId, $doctorId]
            );
        }

        // Pestaña por defecto: si viene por URL o si la cita está en progreso, activar 'atencion'
        $activeTab = $_GET['tab'] ?? (($activeAppointment && $activeAppointment['status'] === 'in_progress') ? 'atencion' : 'historial');

        // Cargar tratamientos y recetas históricas del paciente
        $treatmentModel    = new \App\Models\Treatment();
        $treatments        = $treatmentModel->getByPatient($patientId);

        $prescriptionModel = new \App\Models\Prescription();
        $prescriptions     = $prescriptionModel->getByPatient($patientId);

        View::render('doctor.appointments.history', [
            'title'             => 'Historial Clínico: ' . $patient['name'],
            'patient'           => $patient,
            'history'           => $history,
            'notes'             => $notes,
            'treatments'        => $treatments,
            'prescriptions'     => $prescriptions,
            'doctorId'          => $doctorId,
            'activeAppointment' => $activeAppointment,
            'activeTab'         => $activeTab,
            'csrfToken'         => Session::generateCsrf()
        ], 'admin');

        // ── Auditoría clínica — Registrar que el médico/admin accedió a la historia del paciente
        Audit::clinicalAccess(
            ClinicalAudit::RECURSO_HISTORIA_CLINICA,
            $patientId,
            $patientId,
            ClinicalAudit::ACCION_VER,
            ['paciente_nombre' => $patient['name'] ?? 'N/A']
        );
    }

    public function storeNote(int $patientId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=atencion');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=atencion');
        }

        $doctorId = $this->getDoctorId();
        $db = \App\Helpers\Database::getInstance();

        if ($doctorId <= 0) {
            Session::flash('error', 'No se encontró un perfil de médico asociado para registrar la evolución.');
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=atencion');
            return;
        }

        $patient = $db->fetch("SELECT id FROM patients WHERE id = ?", [$patientId]);
        if (!$patient) {
            Session::flash('error', 'El paciente especificado no existe.');
            View::redirect('/doctor/appointments');
            return;
        }
        
        $appointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        if ($appointmentId !== null) {
            $appt = $db->fetch("SELECT id FROM appointments WHERE id = ? AND patient_id = ?", [$appointmentId, $patientId]);
            if (!$appt) {
                $appointmentId = null;
            }
        }

        $chiefComplaint = trim($_POST['chief_complaint'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');

        if (empty($chiefComplaint) || empty($diagnosis)) {
            Session::flash('error', 'El motivo de consulta y el diagnóstico son obligatorios.');
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=atencion');
            return;
        }

        try {
            $db->execute(
                "INSERT INTO medical_notes (appointment_id, patient_id, doctor_id, chief_complaint, diagnosis, treatment, clinical_notes) 
                 VALUES (?, ?, ?, ?, ?, NULL, NULL)",
                [
                    $appointmentId,
                    $patientId,
                    $doctorId,
                    \App\Helpers\Crypto::encrypt($chiefComplaint),
                    \App\Helpers\Crypto::encrypt($diagnosis)
                ]
            );
            
            $noteId = (int)$db->lastInsertId();

            // El estado de la cita no debe quedar en progreso; debe conservar el estado confirmada.
            // Solo el personal administrativo puede cambiar el estado a completada.
            if ($appointmentId !== null) {
                $db->query(
                    "UPDATE appointments SET status = 'confirmed' WHERE id = ? AND status != 'completed' AND status != 'cancelled'",
                    [$appointmentId]
                );
            }
            // Asegurar que ninguna cita de este paciente con este médico quede en 'in_progress'
            $db->query(
                "UPDATE appointments SET status = 'confirmed' WHERE patient_id = ? AND doctor_id = ? AND status = 'in_progress'",
                [$patientId, $doctorId]
            );

            Session::flash('success', 'Evolución médica guardada correctamente.');


            // ── Auditoría clínica — Registrar creación de nota médica
            Audit::clinicalAccess(
                ClinicalAudit::RECURSO_NOTA_MEDICA,
                $noteId > 0 ? $noteId : null,
                $patientId,
                ClinicalAudit::ACCION_CREAR,
                [
                    'appointment_id' => $appointmentId,
                    'doctor_id'      => $doctorId,
                ]
            );
        } catch (\Exception $e) {
            Session::flash('error', 'Error al guardar la evolución: ' . $e->getMessage());
            error_log("Error guardando nota clínica: " . $e->getMessage());
        }

        View::redirect('/doctor/patients/' . $patientId . '/history?tab=historial');
    }

    public function storeTreatment(int $patientId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=tratamiento');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=tratamiento');
        }

        $doctorId      = $this->getDoctorId();
        $appointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
        $title         = trim($_POST['title'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $treatmentType = trim($_POST['treatment_type'] ?? 'Tratamiento Médico');
        $frequency     = trim($_POST['frequency'] ?? '');
        $duration      = trim($_POST['duration'] ?? '');
        $startDate     = !empty($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
        $endDate       = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

        if (empty($title) || empty($description)) {
            Session::flash('error', 'El nombre y la descripción del tratamiento son obligatorios.');
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=tratamiento');
        }

        try {
            $treatmentModel = new \App\Models\Treatment();
            $treatmentModel->create([
                'appointment_id' => $appointmentId,
                'patient_id'     => $patientId,
                'doctor_id'      => $doctorId,
                'treatment_type' => $treatmentType,
                'title'          => $title,
                'description'    => $description,
                'frequency'      => $frequency,
                'duration'       => $duration,
                'status'         => 'active',
                'start_date'     => $startDate,
                'end_date'       => $endDate
            ]);

            Session::flash('success', 'Tratamiento registrado exitosamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al registrar el tratamiento: ' . $e->getMessage());
        }

        View::redirect('/doctor/patients/' . $patientId . '/history?tab=tratamiento');
    }

    public function updateTreatmentStatus(int $treatmentId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/doctor/appointments');
        }

        $patientId = (int)($_POST['patient_id'] ?? 0);
        $status    = in_array($_POST['status'] ?? '', ['active', 'completed', 'cancelled']) ? $_POST['status'] : 'completed';

        $treatmentModel = new \App\Models\Treatment();
        $treatmentModel->updateStatus($treatmentId, $status);

        Session::flash('success', 'Estado del tratamiento actualizado.');
        View::redirect('/doctor/patients/' . $patientId . '/history?tab=tratamiento');
    }

    public function storePrescription(int $patientId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=receta');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/doctor/patients/' . $patientId . '/history?tab=receta');
        }

        $doctorId      = $this->getDoctorId();
        $appointmentId = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;

        $prescriptionModel = new \App\Models\Prescription();

        try {
            $medications = $_POST['medications'] ?? [];
            if (empty($medications) && !empty($_POST['medication_name'])) {
                $medications = [[
                    'medication_name' => trim($_POST['medication_name']),
                    'dosage'          => trim($_POST['dosage'] ?? ''),
                    'frequency'       => trim($_POST['frequency'] ?? ''),
                    'duration'        => trim($_POST['duration'] ?? ''),
                    'instructions'    => trim($_POST['instructions'] ?? '')
                ]];
            }

            if (empty($medications)) {
                Session::flash('error', 'Debe ingresar al menos un medicamento.');
                View::redirect('/doctor/patients/' . $patientId . '/history?tab=receta');
            }

            $count = 0;
            foreach ($medications as $med) {
                $name = trim($med['medication_name'] ?? '');
                if (empty($name)) continue;

                $prescriptionModel->create([
                    'appointment_id'  => $appointmentId,
                    'patient_id'      => $patientId,
                    'doctor_id'       => $doctorId,
                    'medication_name' => $name,
                    'dosage'          => trim($med['dosage'] ?? 'Según indicación'),
                    'frequency'       => trim($med['frequency'] ?? 'Según indicación'),
                    'duration'        => trim($med['duration'] ?? 'Según evolución'),
                    'instructions'    => trim($med['instructions'] ?? '')
                ]);
                $count++;
            }

            if ($count > 0) {
                Session::flash('success', "Receta médica guardada exitosamente ({$count} medicamento(s)).");
            } else {
                Session::flash('error', 'No se ingresó ningún medicamento válido.');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al registrar la receta: ' . $e->getMessage());
        }

        View::redirect('/doctor/patients/' . $patientId . '/history?tab=receta');
    }

    public function downloadPrescriptionPdf(int $prescriptionId)
    {
        $prescriptionModel = new \App\Models\Prescription();
        $prescription = $prescriptionModel->getByIdWithDetails($prescriptionId);

        if (!$prescription) {
            http_response_code(404);
            echo '<h1>404 - Receta no encontrada</h1>';
            exit;
        }

        $pdfGenerator = new \App\Helpers\PdfGenerator();
        $pdfContent   = $pdfGenerator->generatePrescriptionPdf($prescription);

        $prescriptionModel->markAsPrinted($prescriptionId);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="receta-' . $prescriptionId . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }

    public function appointmentAttachment(int $appointmentId)
    {
        $db = \App\Helpers\Database::getInstance();
        $doctorId = $this->getDoctorId();

        $appointment = $db->fetch("SELECT * FROM appointments WHERE id = ?", [$appointmentId]);
        if (!$appointment || empty($appointment['attachment_url'])) {
            http_response_code(404);
            echo '<h1>404 - Documento adjunto no encontrado</h1>';
            exit;
        }

        // Permisos: Admin o el médico asignado a la cita
        if (!\App\Helpers\Auth::hasRole('admin') && (int)$appointment['doctor_id'] !== $doctorId) {
            http_response_code(403);
            echo '<h1>403 - Acceso denegado a este documento</h1>';
            exit;
        }

        $appConfig = require CONFIG_PATH . '/app.php';
        $filename = str_replace('uploads/', '', $appointment['attachment_url']);
        $filePath = rtrim($appConfig['upload_dir'], '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo '<h1>404 - El archivo físico no se encuentra en el servidor</h1>';
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
        exit;
    }
}
