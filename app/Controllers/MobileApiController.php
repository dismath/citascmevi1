<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Database;
use App\Helpers\JwtAuth;
use App\Helpers\ApiMiddleware;
use App\Helpers\RateLimiter;
use App\Models\User;
use App\Models\Appointment;

class MobileApiController
{
    private User $userModel;
    private Appointment $appointmentModel;
    private Database $db;

    public function __construct()
    {
        $this->userModel = new User();
        $this->appointmentModel = new Appointment();
        $this->db = Database::getInstance();
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login()
    {
        ApiMiddleware::handleCors();
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => 'Método no permitido'], 405);
        }

        // Limit to 5 login attempts per minute per IP to prevent brute force
        RateLimiter::check('api_mobile_login', 5, 60);

        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $deviceName = $input['device_name'] ?? 'Mobile App';
        $platform = $input['platform'] ?? null;
        $fcmToken = $input['fcm_token'] ?? null;

        if (empty($email) || empty($password)) {
            View::json(['success' => false, 'message' => 'Credenciales incompletas'], 400);
        }

        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            $roles = explode(',', $user['roles'] ?? '');
            $primaryRole = trim($roles[0]) ?: 'user';

            // Generate Tokens
            $accessToken = JwtAuth::generateAccessToken((int)$user['id'], $primaryRole);
            $refreshToken = JwtAuth::generateRefreshToken();
            $tokenHash = hash('sha256', $refreshToken);

            // Save refresh token in DB
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

            // Desvincular este token de FCM de cualquier sesión anterior (de cualquier usuario)
            // Esto evita que notificaciones de un usuario lleguen a otro si comparten dispositivo
            if ($fcmToken) {
                $this->db->execute(
                    "UPDATE api_tokens SET fcm_token = NULL WHERE fcm_token = ?",
                    [$fcmToken]
                );
            }

            $this->db->execute(
                "INSERT INTO api_tokens (user_id, token_hash, device_name, platform, fcm_token, expires_at) VALUES (?, ?, ?, ?, ?, ?)",
                [$user['id'], $tokenHash, $deviceName, $platform, $fcmToken, $expiresAt]
            );

            // Fetch specific data
            $userData = ['id' => $user['id'], 'email' => $user['email'], 'role' => $primaryRole];
            if ($primaryRole === 'doctor') {
                $docRow = $this->db->fetch("SELECT id, name FROM doctors WHERE user_id = ?", [(int)$user['id']]);
                if ($docRow) {
                    $userData['doctor_id'] = $docRow['id'];
                    $userData['name'] = $docRow['name'];
                }
            } elseif ($primaryRole === 'patient') {
                $patRow = $this->db->fetch("SELECT id, name FROM patients WHERE user_id = ?", [(int)$user['id']]);
                if ($patRow) {
                    $userData['patient_id'] = $patRow['id'];
                    $userData['name'] = $patRow['name'];
                }
            }

            // Clear rate limit on success
            RateLimiter::clear('api_mobile_login');

            View::json([
                'success' => true,
                'data' => [
                    'user' => $userData,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken
                ]
            ]);
        }

        View::json(['success' => false, 'message' => 'Credenciales incorrectas'], 401);
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh()
    {
        ApiMiddleware::handleCors();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::json(['success' => false, 'message' => 'Método no permitido'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? '';
        
        if (empty($refreshToken)) {
            View::json(['success' => false, 'message' => 'Refresh token requerido'], 400);
        }

        $tokenHash = hash('sha256', $refreshToken);
        $record = $this->db->fetch("SELECT * FROM api_tokens WHERE token_hash = ? AND expires_at > NOW()", [$tokenHash]);

        if (!$record) {
            View::json(['success' => false, 'message' => 'Token inválido o expirado'], 401);
        }

        // Generate new access token
        $user = $this->userModel->findById((int)$record['user_id']);
        if (!$user) {
            View::json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        $roles = explode(',', $user['roles'] ?? '');
        $primaryRole = trim($roles[0]) ?: 'user';

        // Rotación estricta de tokens (genera nuevo access_token y nuevo refresh_token)
        $newAccessToken = JwtAuth::generateAccessToken((int)$user['id'], $primaryRole);
        $newRefreshToken = JwtAuth::generateRefreshToken();
        $newTokenHash = hash('sha256', $newRefreshToken);
        $newExpiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 días

        // Reemplazar hash del token anterior por el nuevo para invalidar el usado
        $this->db->execute(
            "UPDATE api_tokens SET token_hash = ?, expires_at = ?, last_used_at = NOW() WHERE id = ?",
            [$newTokenHash, $newExpiresAt, $record['id']]
        );

        View::json([
            'success' => true,
            'data' => [
                'access_token'  => $newAccessToken,
                'refresh_token' => $newRefreshToken
            ]
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     * Revoca y elimina el token de la sesión móvil activa
     */
    public function logout()
    {
        $payload = ApiMiddleware::requireAuth();
        $userId = (int)$payload['user_id'];

        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? '';

        if (!empty($refreshToken)) {
            $tokenHash = hash('sha256', $refreshToken);
            $this->db->execute(
                "DELETE FROM api_tokens WHERE user_id = ? AND token_hash = ?",
                [$userId, $tokenHash]
            );
        } else {
            // Revocar el token más reciente del usuario
            $this->db->execute(
                "DELETE FROM api_tokens WHERE user_id = ? ORDER BY last_used_at DESC LIMIT 1",
                [$userId]
            );
        }

        \App\Models\SecurityAudit::log(
            $userId,
            \App\Models\SecurityAudit::EVENTO_LOGOUT,
            \App\Models\SecurityAudit::RESULTADO_EXITOSO,
            ['canal' => 'api_mobile']
        );

        View::json([
            'success' => true,
            'message' => 'Sesión cerrada y token revocado exitosamente'
        ]);
    }

    /**
     * GET /api/v1/doctor/appointments
     */
    public function doctorAppointments()
    {
        $payload = ApiMiddleware::requireRole(['doctor', 'admin']);
        $userId = $payload['user_id'];

        $docRow = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
        if (!$docRow) {
            View::json(['success' => false, 'message' => 'Médico no encontrado'], 404);
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
        $offset = ($page - 1) * $limit;

        $appointments = $this->db->fetchAll(
            "SELECT a.id, a.appointment_date, a.status, a.reschedule_count, p.name as patient_name
             FROM appointments a
             LEFT JOIN patients p ON a.patient_id = p.id
             WHERE a.doctor_id = ? AND a.appointment_date >= CURDATE()
             AND a.status != 'pending'
             ORDER BY a.appointment_date ASC
             LIMIT {$limit} OFFSET {$offset}",
            [$docRow['id']]
        );

        View::json([
            'success' => true,
            'data' => $appointments,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * GET /api/v1/patient/appointments
     */
    public function patientAppointments()
    {
        $payload = ApiMiddleware::requireRole(['patient', 'admin']);
        $userId = $payload['user_id'];

        $patRow = $this->db->fetch("SELECT id FROM patients WHERE user_id = ?", [$userId]);
        if (!$patRow) {
            View::json(['success' => false, 'message' => 'Paciente no encontrado'], 404);
        }

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
        $offset = ($page - 1) * $limit;

        $appointments = $this->db->fetchAll(
            "SELECT a.id, a.appointment_date, a.status, a.reschedule_count, d.name as doctor_name
             FROM appointments a
             LEFT JOIN doctors d ON a.doctor_id = d.id
             WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
             AND a.status != 'pending'
             ORDER BY a.appointment_date ASC
             LIMIT {$limit} OFFSET {$offset}",
            [$patRow['id']]
        );

        View::json([
            'success' => true,
            'data' => $appointments,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * GET /api/v1/appointments/upcoming
     * Retorna citas próximas que necesitan notificación y todas las citas vigentes
     */
    public function getUpcoming()
    {
        $payload = ApiMiddleware::requireAuth();
        $userId = $payload['user_id'];
        $role = $payload['role'];

        // Determinar el ID del paciente o doctor
        $condition = "";
        $params = [];

        if ($role === 'patient') {
            $patRow = $this->db->fetch("SELECT id FROM patients WHERE user_id = ?", [$userId]);
            if (!$patRow) {
                View::json(['success' => true, 'data' => [], 'message' => 'Sin citas']);
                return;
            }
            $condition = "a.patient_id = ?";
            $params[] = $patRow['id'];
        } elseif ($role === 'doctor') {
            $docRow = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
            if (!$docRow) {
                View::json(['success' => true, 'data' => [], 'message' => 'Sin citas']);
                return;
            }
            $condition = "a.doctor_id = ?";
            $params[] = $docRow['id'];
        } else {
            View::json(['success' => true, 'data' => [], 'message' => 'Rol no soportado']);
            return;
        }

        // Obtener citas que necesitan notificación (próximas 2 días, no notificadas)
        $sql = "SELECT a.id, a.appointment_date, a.status, p.name as patient_name, d.name as doctor_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN doctors d ON a.doctor_id = d.id
                WHERE {$condition}
                AND a.appointment_date >= NOW()
                AND a.appointment_date <= DATE_ADD(NOW(), INTERVAL 2 DAY)
                AND a.status IN ('confirmed')
                AND a.reminder_sent = 0
                ORDER BY a.appointment_date ASC";

        $newReminders = $this->db->fetchAll($sql, $params);

        // Marcar como enviados
        foreach ($newReminders as $appt) {
            $this->appointmentModel->markReminderSent($appt['id']);
        }

        View::json([
            'success' => true,
            'data' => $newReminders,
            'message' => count($newReminders) . ' recordatorios nuevos'
        ]);
    }

    // =========================================================================
    // Doctor Profile API
    // =========================================================================

    /**
     * GET /api/v1/doctor/profile
     */
    public function doctorProfile()
    {
        $payload = ApiMiddleware::requireRole(['doctor', 'admin']);
        $userId = $payload['user_id'];

        $doctor = $this->db->fetch(
            "SELECT d.id, d.name, d.phone, d.status, u.email
             FROM doctors d
             JOIN users u ON d.user_id = u.id
             WHERE d.user_id = ?",
            [$userId]
        );

        if (!$doctor) {
            View::json(['success' => false, 'message' => 'Perfil de médico no encontrado'], 404);
        }

        View::json(['success' => true, 'data' => $doctor]);
    }

    /**
     * PUT /api/v1/doctor/profile
     */
    public function updateDoctorProfile()
    {
        $payload = ApiMiddleware::requireRole(['doctor', 'admin']);
        $userId = $payload['user_id'];

        $input = json_decode(file_get_contents('php://input'), true);
        $name = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (empty($name) || empty($email)) {
            View::json(['success' => false, 'message' => 'El nombre y el correo son obligatorios'], 400);
        }

        $doctor = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
        if (!$doctor) {
            View::json(['success' => false, 'message' => 'Médico no encontrado'], 404);
        }

        try {
            $this->db->beginTransaction();

            $this->db->execute(
                "UPDATE doctors SET name = ?, phone = ? WHERE id = ?",
                [$name, $phone, $doctor['id']]
            );

            $this->db->execute(
                "UPDATE users SET email = ? WHERE id = ?",
                [$email, $userId]
            );

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $this->db->execute(
                    "UPDATE users SET password = ? WHERE id = ?",
                    [$hashedPassword, $userId]
                );
            }

            $this->db->commit();

            View::json(['success' => true, 'message' => 'Perfil actualizado exitosamente']);
        } catch (\Exception $e) {
            $this->db->rollback();
            View::json(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Doctor Schedule API
    // =========================================================================

    /**
     * GET /api/v1/doctor/schedule?year=2026&month=8
     */
    public function doctorSchedule()
    {
        $payload = ApiMiddleware::requireRole(['doctor', 'admin']);
        $userId = $payload['user_id'];

        $docRow = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
        if (!$docRow) {
            View::json(['success' => false, 'message' => 'Médico no encontrado'], 404);
        }

        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
        $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        // JOIN with appointments to filter out slots booked by pending appointments
        $slots = $this->db->fetchAll(
            "SELECT da.id, da.available_date, da.start_time, da.end_time, da.status
             FROM doctor_availability da
             LEFT JOIN appointments a ON da.notes = CONCAT('Cita #', a.id)
             WHERE da.doctor_id = ? AND da.available_date BETWEEN ? AND ?
             AND da.available_date >= CURDATE()
             AND (da.status = 'available' OR (da.status = 'booked' AND a.status != 'pending'))
             ORDER BY da.available_date ASC, da.start_time ASC",
            [$docRow['id'], $startDate, $endDate]
        );

        // Build summary per date
        $summary = [];
        foreach ($slots as $slot) {
            $d = $slot['available_date'];
            if (!isset($summary[$d])) {
                $summary[$d] = ['available' => 0, 'booked' => 0, 'total' => 0];
            }
            $summary[$d][$slot['status']] = ($summary[$d][$slot['status']] ?? 0) + 1;
            $summary[$d]['total']++;
        }

        View::json([
            'success' => true,
            'data' => [
                'slots' => $slots,
                'summary' => $summary,
                'year' => $year,
                'month' => $month
            ]
        ]);
    }

    /**
     * POST /api/v1/doctor/schedule/generate
     */
    public function generateSchedule()
    {
        $payload = ApiMiddleware::requireRole(['doctor', 'admin']);
        $userId = $payload['user_id'];

        $docRow = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
        if (!$docRow) {
            View::json(['success' => false, 'message' => 'Médico no encontrado'], 404);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $startDate = $input['start_date'] ?? '';
        $endDate = $input['end_date'] ?? '';
        $startTime = $input['start_time'] ?? '';
        $endTime = $input['end_time'] ?? '';
        $interval = (int)($input['interval'] ?? 30);

        if (!$startDate || !$endDate || !$startTime || !$endTime) {
            View::json(['success' => false, 'message' => 'Todos los campos son obligatorios'], 400);
        }

        try {
            $availability = new \App\Models\Availability();
            $count = $availability->generateSlots(
                $docRow['id'], $startDate, $endDate, $startTime, $endTime, $interval
            );
            View::json(['success' => true, 'message' => "Se generaron {$count} turnos", 'count' => $count]);
        } catch (\Exception $e) {
            View::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/v1/doctor/schedule/{id}
     */
    public function deleteScheduleSlot(string $id)
    {
        $payload = ApiMiddleware::requireRole(['doctor', 'admin']);
        $userId = $payload['user_id'];

        $docRow = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
        if (!$docRow) {
            View::json(['success' => false, 'message' => 'Médico no encontrado'], 404);
        }

        $slot = $this->db->fetch("SELECT * FROM doctor_availability WHERE id = ?", [(int)$id]);
        if (!$slot || $slot['doctor_id'] != $docRow['id']) {
            View::json(['success' => false, 'message' => 'Turno no encontrado'], 404);
        }

        if ($slot['status'] !== 'available') {
            View::json(['success' => false, 'message' => 'Solo se pueden eliminar turnos disponibles'], 400);
        }

        $this->db->execute("DELETE FROM doctor_availability WHERE id = ?", [(int)$id]);
        View::json(['success' => true, 'message' => 'Turno eliminado']);
    }
}
