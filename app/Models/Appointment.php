<?php
namespace App\Models;

class Appointment extends BaseModel
{
    protected string $table = 'appointments';

    public function getAllWithDetails(string $filter = '', array $params = []): array
    {
        $where = $filter ? "WHERE {$filter}" : '';
        return $this->db->fetchAll(
            "SELECT a.*, p.name as patient_name, p.id_number as patient_id_number,
                    p.phone as patient_phone, p.address as patient_address, u_patient.email as patient_email,
                    d.name as doctor_name, s.name as specialty_name, s.catalog_type_code as specialty_type_code,
                    (SELECT COUNT(id) FROM orders o WHERE o.appointment_id = a.id) as orders_count
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u_patient ON p.user_id = u_patient.id
             JOIN doctors d ON a.doctor_id = d.id
             JOIN specialties s ON d.specialty_id = s.id
             {$where}
             ORDER BY a.appointment_date DESC",
            $params
        );
    }

    public function paginateWithDetails(int $page = 1, int $perPage = 25, string $filter = '', array $params = []): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = $filter ? "WHERE {$filter}" : '';
        
        $sql = "SELECT a.*, p.name as patient_name, p.id_number as patient_id_number,
                    p.phone as patient_phone, p.address as patient_address, u_patient.email as patient_email,
                    d.name as doctor_name, s.name as specialty_name, s.catalog_type_code as specialty_type_code,
                    (SELECT COUNT(id) FROM orders o WHERE o.appointment_id = a.id) as orders_count
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u_patient ON p.user_id = u_patient.id
             JOIN doctors d ON a.doctor_id = d.id
             JOIN specialties s ON d.specialty_id = s.id
             {$where}
             ORDER BY a.appointment_date DESC 
             LIMIT {$perPage} OFFSET {$offset}";
             
        $data = $this->db->fetchAll($sql, $params);
        
        $countSql = "SELECT COUNT(a.id) as total
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u_patient ON p.user_id = u_patient.id
             JOIN doctors d ON a.doctor_id = d.id
             JOIN specialties s ON d.specialty_id = s.id
             {$where}";
             
        $totalResult = $this->db->fetch($countSql, $params);
        $total = (int) ($totalResult['total'] ?? 0);
        $totalPages = ceil($total / $perPage);
        
        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $total,
            'total_pages' => $totalPages
        ];
    }

    public function getByIdWithDetails(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT a.*, p.name as patient_name, p.id_number as patient_id_number,
                    p.phone as patient_phone, p.address as patient_address,
                    d.name as doctor_name, d.phone as doctor_phone,
                    s.id as specialty_id, s.name as specialty_name, s.icon as specialty_icon,
                    s.catalog_type_code,
                    dp.consultation_fee, dp.medical_license,
                    u_patient.email as patient_email,
                    u_doctor.email as doctor_email
             FROM appointments a
             JOIN patients p ON a.patient_id = p.id
             LEFT JOIN users u_patient ON p.user_id = u_patient.id
             JOIN doctors d ON a.doctor_id = d.id
             LEFT JOIN users u_doctor ON d.user_id = u_doctor.id
             JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN doctor_profiles dp ON d.id = dp.doctor_id
             WHERE a.id = ?",
            [$id]
        );
    }

    public function getTodayCount(): int
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM appointments WHERE DATE(appointment_date) = CURDATE()"
        );
        return (int) $result['total'];
    }

    public function getPendingCount(): int
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM appointments WHERE status = 'pending'"
        );
        return (int) $result['total'];
    }

    public function getWeeklyStats(): array
    {
        return $this->db->fetchAll(
            "SELECT DATE(appointment_date) as date, COUNT(*) as total,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
             FROM appointments
             WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY DATE(appointment_date)
             ORDER BY date ASC"
        );
    }

    public function getByTokenWithDetails(string $token)
    {
        $sql = "SELECT a.*, 
                       p.name as patient_name, p.id_number as patient_id_number, p.phone, u.email as patient_email,
                       d.name as doctor_name,
                       s.name as specialty_name
                FROM {$this->table} a
                JOIN patients p ON a.patient_id = p.id
                LEFT JOIN users u ON p.user_id = u.id
                JOIN doctors d ON a.doctor_id = d.id
                JOIN specialties s ON d.specialty_id = s.id
                WHERE a.confirmation_token = ?";
        
        return $this->db->fetch($sql, [$token]);
    }

    public function createFull(array $appointmentData, array $patientData, int $availabilityId): array
    {
        $this->db->beginTransaction();
        try {
            // 1. BLOQUEO PESIMISTA: Evita doble agendamiento en alta concurrencia
            $slot = $this->db->fetch(
                "SELECT id, status FROM doctor_availability WHERE id = ? FOR UPDATE",
                [$availabilityId]
            );

            if (!$slot || $slot['status'] !== 'available') {
                throw new \Exception("Lo sentimos, este turno acaba de ser tomado por otro paciente. Por favor elija otro horario.");
            }

            // Check if patient exists by id_number
            $patient = $this->db->fetch(
                "SELECT id, user_id FROM patients WHERE id_number = ?",
                [$patientData['id_number']]
            );

            $userCreated  = false;
            $username     = null;
            $tempPassword = null;
            $userId       = null;

            if ($patient) {
                $patientId = (int) $patient['id'];
                $userId    = !empty($patient['user_id']) ? (int) $patient['user_id'] : null;

                // Si el paciente existente no tiene usuario vinculado pero proporcionó email
                if (!$userId && !empty($patientData['email'])) {
                    $existingUser = $this->db->fetch("SELECT id, username FROM users WHERE email = ?", [$patientData['email']]);
                    if ($existingUser) {
                        $userId   = (int) $existingUser['id'];
                        $username = !empty($existingUser['username']) ? $existingUser['username'] : $patientData['email'];
                    } else {
                        // Generar nuevo usuario para el paciente existente
                        $tempPassword = bin2hex(random_bytes(4));
                        $baseName     = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $patientData['name']));
                        if (empty($baseName)) $baseName = 'paciente';

                        $username = $baseName . '_' . rand(1000, 9999);
                        while ($this->db->fetch("SELECT id FROM users WHERE username = ?", [$username])) {
                            $username = $baseName . '_' . rand(1000, 9999);
                        }

                        $this->db->execute(
                            "INSERT INTO users (email, username, nombre_usuario, password, status) VALUES (?, ?, ?, ?, 'active')",
                            [$patientData['email'], $username, $patientData['name'], password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12])]
                        );
                        $userId      = (int) $this->db->lastInsertId();
                        $userCreated = true;

                        $roleRow = $this->db->fetch("SELECT id FROM roles WHERE name = 'patient'");
                        if ($roleRow) {
                            $this->db->execute("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleRow['id']]);
                        }
                    }

                    $this->db->execute("UPDATE patients SET user_id = ? WHERE id = ?", [$userId, $patientId]);
                } elseif ($userId) {
                    // Obtener nombre de usuario del paciente existente
                    $userRow = $this->db->fetch("SELECT username, email FROM users WHERE id = ?", [$userId]);
                    if ($userRow) {
                        $username = !empty($userRow['username']) ? $userRow['username'] : $userRow['email'];
                    }
                }

                // Update existing patient data
                $this->db->execute(
                    "UPDATE patients SET name = ?, phone = ?, address = ?, document_type_code = ? WHERE id = ?",
                    [$patientData['name'], $patientData['phone'] ?? '', $patientData['address'] ?? '', $patientData['document_type_code'] ?? '05', $patientId]
                );
            } else {
                if (!empty($patientData['email'])) {
                    // Check if user already exists
                    $existingUser = $this->db->fetch("SELECT id, username FROM users WHERE email = ?", [$patientData['email']]);
                    if ($existingUser) {
                        $userId   = (int) $existingUser['id'];
                        $username = !empty($existingUser['username']) ? $existingUser['username'] : $patientData['email'];
                    } else {
                        // Generate a temporary user
                        $tempPassword = bin2hex(random_bytes(4));
                        $baseName     = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $patientData['name']));
                        if (empty($baseName)) $baseName = 'paciente';
                        
                        $username = $baseName . '_' . rand(1000, 9999);
                        while ($this->db->fetch("SELECT id FROM users WHERE username = ?", [$username])) {
                            $username = $baseName . '_' . rand(1000, 9999);
                        }
                        
                        $this->db->execute(
                            "INSERT INTO users (email, username, nombre_usuario, password, status) VALUES (?, ?, ?, ?, 'active')",
                            [$patientData['email'], $username, $patientData['name'], password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12])]
                        );
                        $userId      = (int) $this->db->lastInsertId();
                        $userCreated = true;
                        
                        $roleRow = $this->db->fetch("SELECT id FROM roles WHERE name = 'patient'");
                        if ($roleRow) {
                            $this->db->execute("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleRow['id']]);
                        }
                    }
                }
                
                // Create new patient
                $this->db->execute(
                    "INSERT INTO patients (user_id, name, id_number, phone, address, date_of_birth, gender, document_type_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId,
                        $patientData['name'],
                        $patientData['id_number'],
                        $patientData['phone'] ?? '',
                        $patientData['address'] ?? '',
                        $patientData['date_of_birth'] ?? null,
                        $patientData['gender'] ?? null,
                        $patientData['document_type_code'] ?? '05',
                    ]
                );
                $patientId = (int) $this->db->lastInsertId();
            }

            // Create appointment
            $appointmentData['patient_id'] = $patientId;
            $this->db->execute(
                "INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, notes, attachment_url, confirmation_token, token_expires_at) 
                 VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)",
                [
                    $patientId,
                    $appointmentData['doctor_id'],
                    $appointmentData['appointment_date'],
                    $appointmentData['notes'] ?? '',
                    $appointmentData['attachment_url'] ?? null,
                    $appointmentData['confirmation_token'] ?? null,
                    $appointmentData['token_expires_at'] ?? null,
                ]
            );
            $appointmentId = (int) $this->db->lastInsertId();

            // Mark availability slot as booked
            $this->db->execute(
                "UPDATE doctor_availability SET status = 'booked', notes = ? WHERE id = ?",
                ["Cita #{$appointmentId}", $availabilityId]
            );

            $this->db->commit();

            if (empty($username)) {
                $username = !empty($patientData['email']) ? $patientData['email'] : ($patientData['id_number'] ?? 'paciente');
            }

            return [
                'appointment_id' => $appointmentId,
                'patient_id'     => $patientId,
                'user_id'        => $userId,
                'username'       => $username,
                'temp_password'  => $tempPassword,
                'user_created'   => $userCreated,
            ];

        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Obtener citas próximas para un usuario específico (para notificaciones)
     */
    public function getUpcomingAppointmentsForUser(int $userId, string $role): array
    {
        $params = [];
        $condition = "";

        if ($role === 'patient') {
            $patRow = $this->db->fetch("SELECT id FROM patients WHERE user_id = ?", [$userId]);
            if (!$patRow) return [];
            $condition = "a.patient_id = ?";
            $params[] = $patRow['id'];
        } elseif ($role === 'doctor') {
            $docRow = $this->db->fetch("SELECT id FROM doctors WHERE user_id = ?", [$userId]);
            if (!$docRow) return [];
            $condition = "a.doctor_id = ?";
            $params[] = $docRow['id'];
        } else {
            return [];
        }

        // Obtener citas en los próximos 2 días que estén pendientes o confirmadas, y que no se haya enviado recordatorio
        $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN doctors d ON a.doctor_id = d.id
                WHERE {$condition} 
                AND a.appointment_date >= NOW() 
                AND a.appointment_date <= DATE_ADD(NOW(), INTERVAL 2 DAY)
                AND a.status IN ('pending', 'confirmed')
                AND a.reminder_sent = 0
                ORDER BY a.appointment_date ASC";

        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Marcar recordatorio como enviado
     */
    public function markReminderSent(int $appointmentId): void
    {
        $this->db->execute("UPDATE appointments SET reminder_sent = 1 WHERE id = ?", [$appointmentId]);
    }
}
