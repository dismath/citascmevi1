<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;
use App\Models\Availability;
use App\Models\Specialty;

class ApiController
{
    private Availability $availabilityModel;
    private Specialty $specialtyModel;

    public function __construct()
    {
        $this->availabilityModel = new Availability();
        $this->specialtyModel = new Specialty();
        // Iniciar sesión para poder verificar autenticación en métodos que lo requieran
        Session::start();
        header('Content-Type: application/json');
    }

    /** Existing: slots by doctor + date */
    public function availabilityByDate(string $doctorId, string $date)
    {
        \App\Helpers\Session::releaseLock();
        try {
            $slots = $this->availabilityModel->getAvailableSlots((int)$doctorId, $date);
            View::json(['success' => true, 'data' => $slots]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** NEW: available dates (with slot counts) for a specialty — used by calendar */
    public function specialtyDates(string $specialtyId)
    {
        \App\Helpers\Session::releaseLock();
        try {
            $dates = $this->availabilityModel->getAvailableDatesBySpecialty((int)$specialtyId);
            View::json(['success' => true, 'data' => $dates]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** NEW: doctors + slots for a specialty on a given date — used by modal */
    public function specialtySlotsByDate(string $specialtyId, string $date)
    {
        \App\Helpers\Session::releaseLock();
        try {
            $doctors = $this->availabilityModel->getDoctorsWithSlotsByDate((int)$specialtyId, $date);
            View::json(['success' => true, 'data' => $doctors]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** NEW: get patient by id_number */
    public function patientByIdNumber(string $idNumber)
    {
        \App\Helpers\Session::releaseLock();
        try {
            $patientModel = new \App\Models\Patient();
            $patient = $patientModel->getByIdNumber($idNumber);
            if ($patient) {
                if (!empty($patient['user_id'])) {
                    $userModel = new \App\Models\User();
                    $user = $userModel->findById((int)$patient['user_id']);
                    if ($user) {
                        $patient['email'] = $user['email'];
                    }
                }
                View::json(['success' => true, 'data' => $patient]);
            } else {
                View::json(['success' => false, 'message' => 'Paciente no encontrado']);
            }
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function specialties()
    {
        \App\Helpers\Session::releaseLock();
        try {
            $specialties = $this->specialtyModel->getAllActive();
            View::json(['success' => true, 'data' => $specialties]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function doctorsBySpecialty(string $specialtyId)
    {
        \App\Helpers\Session::releaseLock();
        try {
            $doctorModel = new \App\Models\Doctor();
            $doctors = $doctorModel->getBySpecialty((int)$specialtyId);
            View::json(['success' => true, 'data' => $doctors]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /** Búsqueda rápida de pacientes para autocompletado en modal administrativo */
    public function searchPatients()
    {
        \App\Helpers\Session::releaseLock();
        try {
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) {
                View::json(['success' => true, 'data' => []]);
                return;
            }
            $patientModel = new \App\Models\Patient();
            $results = $patientModel->searchWithDetails($q);
            $results = array_slice($results, 0, 15);
            View::json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function availability(string $doctorId) { View::json(['success' => true, 'data' => []]); }
    public function doctorFee(string $doctorId) { View::json(['success' => true, 'data' => []]); }
    public function catalogs(string $typeCode) { View::json(['success' => true, 'data' => []]); }
    public function bankInfo() { View::json(['success' => true, 'data' => []]); }
    public function uploadPayment() { View::json(['success' => true]); }

    /**
     * Admin: monthly summary for schedule calendar
     *
     * FIX-SEC-02: Requiere sesión activa — dato interno de administración.
     */
    public function scheduleMonthSummary(string $doctorId)
    {
        // Solo usuarios autenticados pueden ver el resumen del calendario de horarios
        Auth::require();
        Session::releaseLock();
        try {
            $year  = (int)($_GET['year']  ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('n'));
            $summary = $this->availabilityModel->getMonthSummary((int)$doctorId, $year, $month);
            View::json(['success' => true, 'data' => $summary]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => 'Error al obtener el resumen.'], 500);
        }
    }

    /**
     * Admin: all slots for a doctor on a specific date
     *
     * FIX-SEC-02: Requiere sesión activa — dato interno de administración.
     */
    public function scheduleDaySlots(string $doctorId, string $date)
    {
        // Solo usuarios autenticados pueden ver slots detallados del médico
        Auth::require();
        Session::releaseLock();
        try {
            $slots = $this->availabilityModel->getByDoctorAndDateRange((int)$doctorId, $date, $date);
            View::json(['success' => true, 'data' => $slots]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => 'Error al obtener los slots.'], 500);
        }
    }
}
