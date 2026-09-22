<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Menu;
use App\Helpers\Session;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;

class AdminController
{
    private Appointment $appointmentModel;
    private Doctor $doctorModel;
    private Patient $patientModel;

    public function __construct()
    {
        Auth::require();
        $this->appointmentModel = new Appointment();
        $this->doctorModel = new Doctor();
        $this->patientModel = new Patient();
    }

    public function dashboard()
    {
        $role = Session::get('user_role');
        if ($role === 'doctor') {
            View::redirect('/doctor/dashboard');
            return;
        }
        if ($role === 'patient') {
            View::redirect('/patient/dashboard');
            return;
        }

        // Si el usuario no tiene permiso para el dashboard y no es admin, redirigir al primer módulo permitido
        if (!Auth::hasRole('admin') && !Auth::hasPermission('dashboard_read')) {
            $firstUrl = Menu::getFirstAccessibleUrl();
            if ($firstUrl && !str_ends_with($firstUrl, '/admin/dashboard')) {
                View::redirect($firstUrl);
                return;
            }
        }
        // Caché de 5 minutos (300 segundos) para estadísticas pesadas
        $stats = \App\Helpers\Cache::remember('admin_dashboard_stats', 300, function() {
            return [
                'today' => $this->appointmentModel->getTodayCount(),
                'pending' => $this->appointmentModel->getPendingCount(),
                'doctors' => $this->doctorModel->count(),
                'patients' => $this->patientModel->count(),
            ];
        });

        // Caché de 5 minutos para estadísticas semanales
        $weeklyStats = \App\Helpers\Cache::remember('admin_dashboard_weekly', 300, function() {
            return $this->appointmentModel->getWeeklyStats();
        });
        
        $recentAppointments = $this->appointmentModel->getAllWithDetails('', [], 'a.created_at DESC', 5);

        // Prepare data for chart
        $chartLabels = [];
        $chartDataConfirmed = [];
        $chartDataCancelled = [];
        
        foreach ($weeklyStats as $stat) {
            $chartLabels[] = date('d M', strtotime($stat['date']));
            $chartDataConfirmed[] = $stat['confirmed'];
            $chartDataCancelled[] = $stat['cancelled'];
        }

        View::render('admin.dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recentAppointments' => $recentAppointments,
            'chart' => [
                'labels' => json_encode($chartLabels),
                'confirmed' => json_encode($chartDataConfirmed),
                'cancelled' => json_encode($chartDataCancelled)
            ]
        ], 'admin');
    }
}
