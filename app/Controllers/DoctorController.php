<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\Auth;
use App\Models\Appointment;

class DoctorController
{
    private Appointment $appointmentModel;

    public function __construct()
    {
        Auth::requireRole(['doctor', 'admin']);
        $this->appointmentModel = new Appointment();
    }

    public function dashboard()
    {
        $db     = \App\Helpers\Database::getInstance();
        $userId = Session::get('user_id');

        // Primary: look up by user_id (always available, works even if email is NULL)
        $doctor = null;
        if ($userId) {
            $doctor = $db->fetch(
                "SELECT d.id FROM doctors d WHERE d.user_id = ?",
                [(int)$userId]
            );
        }

        // Fallback: look up by email (backwards compat)
        if (!$doctor) {
            $userEmail = Session::get('user_email');
            if ($userEmail) {
                $doctor = $db->fetch(
                    "SELECT d.id FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.email = ?",
                    [$userEmail]
                );
            }
        }

        $doctorId = $doctor ? (int)$doctor['id'] : 0;

        // Today's appointments pending to attend
        $today = date('Y-m-d');
        $todayAppointments = [];
        $todayCount = 0;
        $upcomingCount = 0;

        if ($doctorId) {
            $todayAppointments = $db->fetchAll(
                "SELECT a.id, a.appointment_date, a.status, a.reschedule_count, p.name as patient_name, p.id_number,
                        (SELECT COUNT(*) FROM medical_notes mn WHERE mn.appointment_id = a.id) as has_notes
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 WHERE a.doctor_id = ? AND DATE(a.appointment_date) = ? AND a.status IN ('confirmed', 'in_progress')
                 ORDER BY a.appointment_date ASC",
                [$doctorId, $today]
            );
            $todayCount = count($todayAppointments);

            // Upcoming confirmed appointments (next 7 days, excluding today)
            $upcomingRow = $db->fetch(
                "SELECT COUNT(*) as cnt FROM appointments
                 WHERE doctor_id = ? AND DATE(appointment_date) > ? AND DATE(appointment_date) <= DATE_ADD(?, INTERVAL 7 DAY) AND status = 'confirmed'",
                [$doctorId, $today, $today]
            );
            $upcomingCount = $upcomingRow ? (int)$upcomingRow['cnt'] : 0;
        }

        View::render('doctor.dashboard', [
            'title' => 'Portal del Médico',
            'doctorId' => $doctorId,
            'todayAppointments' => $todayAppointments,
            'todayCount' => $todayCount,
            'upcomingCount' => $upcomingCount
        ], 'admin');
    }

    public function apiConfirmedAppointments(int $doctorId)
    {
        $db = \App\Helpers\Database::getInstance();
        
        // Fetch confirmed and in_progress appointments grouped by date (current and future only)
        $appointments = $db->fetchAll(
            "SELECT DATE(a.appointment_date) as app_date, COUNT(*) as total_appointments 
             FROM appointments a
             WHERE a.doctor_id = ? 
               AND a.status IN ('confirmed', 'in_progress')
               AND DATE(a.appointment_date) >= CURDATE()
             GROUP BY DATE(a.appointment_date)",
            [$doctorId]
        );

        // Fetch available slots grouped by date (current and future only)
        $availableSlots = $db->fetchAll(
            "SELECT available_date as app_date, COUNT(*) as available_count
             FROM doctor_availability
             WHERE doctor_id = ? 
               AND status = 'available'
               AND available_date >= CURDATE()
             GROUP BY available_date",
            [$doctorId]
        );

        $merged = [];
        foreach ($appointments as $app) {
            $merged[$app['app_date']] = ['reserved' => $app['total_appointments'], 'available' => 0];
        }
        foreach ($availableSlots as $av) {
            if (!isset($merged[$av['app_date']])) {
                $merged[$av['app_date']] = ['reserved' => 0, 'available' => 0];
            }
            $merged[$av['app_date']]['available'] = $av['available_count'];
        }

        $events = [];
        foreach ($merged as $date => $counts) {
            $res = $counts['reserved'];
            $disp = $counts['available'];
            
            // Fondo de la celda (Celeste) si hay reservadas
            if ($res > 0) {
                $events[] = [
                    'start' => $date,
                    'display' => 'background',
                    'color' => '#e0f2fe'
                ];
            }
            
            // Evento custom para los badges
            if ($res > 0 || $disp > 0) {
                $events[] = [
                    'start' => $date,
                    'allDay' => true,
                    'dispCount' => $disp,
                    'resCount' => $res,
                    'url' => '/doctor/appointments?date=' . $date,
                    'className' => 'custom-summary-event'
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($events);
        exit;
    }
}
