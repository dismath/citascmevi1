<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Availability;

class DoctorScheduleController
{
    private Availability $availabilityModel;

    public function __construct()
    {
        Auth::requireRole(['doctor', 'admin']);
        $this->availabilityModel = new Availability();
    }

    private function getDoctorId(): int
    {
        $db    = \App\Helpers\Database::getInstance();
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
        $allDoctors = [];
        if (Auth::hasRole('admin')) {
            $docModel = new \App\Models\Doctor();
            $allDoctors = $docModel->findAll('name ASC');
            if (isset($_GET['doctor_id']) && (int)$_GET['doctor_id'] > 0) {
                $doctorId = (int)$_GET['doctor_id'];
            } elseif (!$doctorId && !empty($allDoctors)) {
                $doctorId = (int)$allDoctors[0]['id'];
            }
        }
        
        $year = $_GET['year'] ?? date('Y');
        $month = $_GET['month'] ?? date('m');
        
        // Ensure valid ranges
        $year = (int)$year;
        $month = (int)$month;
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }
        
        $summary = [];
        $monthSlots = [];
        if ($doctorId) {
            $summary = $this->availabilityModel->getMonthSummary($doctorId, $year, $month);
            
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $endDate = date('Y-m-t', strtotime($startDate));
            $monthSlots = $this->availabilityModel->getByDoctorAndDateRange($doctorId, $startDate, $endDate);
        }

        View::render('doctor.schedule.index', [
            'title' => 'Mi Horario',
            'doctorId' => $doctorId,
            'year' => $year,
            'month' => $month,
            'summary' => $summary,
            'monthSlots' => $monthSlots,
            'allDoctors' => $allDoctors
        ], 'admin');
    }

    public function generate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/doctor/schedule');
        }

        $doctorId = $this->getDoctorId();
        if (Auth::hasRole('admin') && !empty($_POST['doctor_id'])) {
            $doctorId = (int)$_POST['doctor_id'];
        }
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $interval = (int)($_POST['interval'] ?? 30);

        if (!$doctorId || !$startDate || !$endDate || !$startTime || !$endTime) {
            Session::flash('error', 'Todos los campos son obligatorios.');
            View::redirect('/doctor/schedule');
        }

        try {
            $count = $this->availabilityModel->generateSlots(
                $doctorId, $startDate, $endDate, $startTime, $endTime, $interval
            );
            Session::flash('success', "Se generaron {$count} nuevos turnos disponibles exitosamente.");
        } catch (\Exception $e) {
            Session::flash('error', 'Error al generar horarios: ' . $e->getMessage());
        }

        $redirectUrl = '/doctor/schedule';
        if (Auth::hasRole('admin') && !empty($_POST['doctor_id'])) {
            $redirectUrl .= '?doctor_id=' . (int)$_POST['doctor_id'];
        }
        View::redirect($redirectUrl);
    }

    public function deleteAllSlots()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida o sesión expirada.');
            View::redirect('/doctor/schedule');
        }

        $date = trim($_POST['date'] ?? '');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Session::flash('error', 'Fecha inválida.');
            View::redirect('/doctor/schedule');
        }

        $doctorId = 0;
        if (Auth::hasRole('admin') && !empty($_POST['doctor_id'])) {
            $doctorId = (int)$_POST['doctor_id'];
        } else {
            $doctorId = $this->getDoctorId();
        }

        if (!$doctorId) {
            Session::flash('error', 'No se pudo identificar el médico.');
            View::redirect('/doctor/schedule');
        }

        try {
            $deleted = $this->availabilityModel->deleteSlotsByDate($doctorId, $date);
            if ($deleted > 0) {
                Session::flash('success', "Se eliminaron {$deleted} turnos de la fecha " . date('d/m/Y', strtotime($date)) . ".");
            } else {
                Session::flash('warning', "No había turnos disponibles para eliminar en la fecha seleccionada.");
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar los turnos: ' . $e->getMessage());
        }

        $parts = explode('-', $date);
        $redirectUrl = '/doctor/schedule?year=' . $parts[0] . '&month=' . (int)$parts[1];
        if (Auth::hasRole('admin') && !empty($_POST['doctor_id'])) {
            $redirectUrl .= '&doctor_id=' . (int)$_POST['doctor_id'];
        }
        View::redirect($redirectUrl);
    }

    public function deleteSlot(string $id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/doctor/schedule');
        }

        try {
            $slot = $this->availabilityModel->findById((int)$id);
            $doctorId = $this->getDoctorId();
            if (Auth::hasRole('admin') && isset($_POST['doctor_id'])) {
                $doctorId = (int)$_POST['doctor_id'];
            }
            // Verify slot belongs to this doctor or user is admin
            if ($slot && ($slot['doctor_id'] == $doctorId || Auth::hasRole('admin'))) {
                if ($slot['status'] === 'available') {
                    $this->availabilityModel->delete((int)$id);
                    Session::flash('success', 'Turno eliminado.');
                } else {
                    Session::flash('error', 'Solo se pueden eliminar turnos disponibles (no reservados).');
                }
            } else {
                Session::flash('error', 'No tienes permiso para modificar este turno.');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar el turno.');
        }

        $redirectUrl = '/doctor/schedule' . (isset($_POST['date']) ? '?date=' . $_POST['date'] : '');
        if (Auth::hasRole('admin') && isset($_POST['doctor_id'])) {
            $redirectUrl .= (str_contains($redirectUrl, '?') ? '&' : '?') . 'doctor_id=' . (int)$_POST['doctor_id'];
        }
        View::redirect($redirectUrl);
    }
}
