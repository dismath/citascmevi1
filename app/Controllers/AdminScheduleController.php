<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Doctor;
use App\Models\Availability;

class AdminScheduleController
{
    private Doctor $doctorModel;
    private Availability $availabilityModel;

    public function __construct()
    {
        Auth::require();
        $this->doctorModel = new Doctor();
        $this->availabilityModel = new Availability();
    }

    public function index()
    {
        Auth::requirePermission('schedules_read');
        $doctors = $this->doctorModel->findAll('name ASC');
        
        $selectedDoctorId = (int)($_GET['doctor_id'] ?? 0);
        $selectedDate     = $_GET['date'] ?? date('Y-m-d');
        $slots            = [];

        if ($selectedDoctorId) {
            // Show slots for selected doctor in a date range (7 days from selected date)
            $endDate = date('Y-m-d', strtotime($selectedDate . ' +6 days'));
            $slots   = $this->availabilityModel->getByDoctorAndDateRange($selectedDoctorId, $selectedDate, $endDate);
        }

        View::render('admin.schedules.index', [
            'title'            => 'Gestor de Horarios',
            'doctors'          => $doctors,
            'slots'            => $slots,
            'selectedDoctorId' => $selectedDoctorId,
            'selectedDate'     => $selectedDate,
        ], 'admin');
    }

    public function generate()
    {
        Auth::requirePermission('schedules_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/schedules');
        }

        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $interval = (int)($_POST['interval'] ?? 30);

        if (!$doctorId || !$startDate || !$endDate || !$startTime || !$endTime) {
            Session::flash('error', 'Todos los campos son obligatorios.');
            View::redirect('/admin/schedules');
        }

        try {
            $count = $this->availabilityModel->generateSlots(
                $doctorId, $startDate, $endDate, $startTime, $endTime, $interval
            );
            Session::flash('success', "Se generaron {$count} nuevos turnos disponibles exitosamente (se omitieron fines de semana y turnos ya existentes).");
        } catch (\Exception $e) {
            Session::flash('error', 'Error al generar horarios: ' . $e->getMessage());
        }

        View::redirect('/admin/schedules');
    }

    public function block(string $id)
    {
        Auth::requirePermission('schedules_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/schedules');
        }

        try {
            $slot = $this->availabilityModel->findById((int)$id);
            if ($slot && $slot['status'] === 'available') {
                $this->availabilityModel->update((int)$id, ['status' => 'blocked']);
                Session::flash('success', 'Turno bloqueado correctamente.');
            } else {
                Session::flash('error', 'El turno no puede ser bloqueado porque no está disponible.');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al bloquear el turno.');
        }

        View::redirect('/admin/schedules' . (isset($_POST['doctor_id']) ? '?doctor_id=' . (int)$_POST['doctor_id'] . '&date=' . ($_POST['date'] ?? date('Y-m-d')) : ''));
    }

    public function unblock(string $id)
    {
        Auth::requirePermission('schedules_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/schedules');
        }

        try {
            $slot = $this->availabilityModel->findById((int)$id);
            if ($slot && $slot['status'] === 'blocked') {
                $this->availabilityModel->update((int)$id, ['status' => 'available']);
                Session::flash('success', 'Turno desbloqueado correctamente.');
            } else {
                Session::flash('error', 'El turno no se encuentra bloqueado.');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al desbloquear el turno.');
        }

        View::redirect('/admin/schedules' . (isset($_POST['doctor_id']) ? '?doctor_id=' . (int)$_POST['doctor_id'] . '&date=' . ($_POST['date'] ?? date('Y-m-d')) : ''));
    }

    public function deleteSlot(string $id)
    {
        Auth::requirePermission('schedules_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/schedules');
        }

        try {
            $slot = $this->availabilityModel->findById((int)$id);
            if ($slot && $slot['status'] === 'available') {
                $this->availabilityModel->delete((int)$id);
                Session::flash('success', 'Turno eliminado.');
            } else {
                Session::flash('error', 'Solo se pueden eliminar turnos disponibles (no reservados).');
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar el turno.');
        }

        View::redirect('/admin/schedules' . (isset($_POST['doctor_id']) ? '?doctor_id=' . (int)$_POST['doctor_id'] . '&date=' . ($_POST['date'] ?? date('Y-m-d')) : ''));
    }

    public function deleteAllSlots()
    {
        Auth::requirePermission('schedules_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida o sesión expirada.');
            View::redirect('/admin/schedules');
        }

        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');

        if (!$doctorId || !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Session::flash('error', 'Parámetros inválidos para eliminar turnos.');
            View::redirect('/admin/schedules');
        }

        try {
            $deleted = $this->availabilityModel->deleteSlotsByDate($doctorId, $date);
            if ($deleted > 0) {
                Session::flash('success', "Se eliminaron {$deleted} turnos de la fecha " . date('d/m/Y', strtotime($date)) . " para el médico seleccionado.");
            } else {
                Session::flash('warning', "No había turnos disponibles para eliminar en la fecha seleccionada.");
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar los turnos: ' . $e->getMessage());
        }

        View::redirect('/admin/schedules?doctor_id=' . $doctorId . '&date=' . $date);
    }
}
