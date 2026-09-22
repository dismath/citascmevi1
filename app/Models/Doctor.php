<?php
namespace App\Models;

class Doctor extends BaseModel
{
    protected string $table = 'doctors';

    public function getBySpecialty(int $specialtyId): array
    {
        return $this->db->fetchAll(
            "SELECT d.*, s.name as specialty_name, dp.consultation_fee, dp.medical_license, dp.years_of_experience
             FROM doctors d
             JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN doctor_profiles dp ON d.id = dp.doctor_id
             WHERE d.specialty_id = ? AND d.status = 'active'
             ORDER BY d.name ASC",
            [$specialtyId]
        );
    }

    public function getWithProfile(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT d.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, s.name as specialty_name, s.icon as specialty_icon,
                    dp.consultation_fee, dp.medical_license, dp.years_of_experience
             FROM doctors d
             LEFT JOIN users u ON d.user_id = u.id
             JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN doctor_profiles dp ON d.id = dp.doctor_id
             WHERE d.id = ?",
            [$id]
        );
    }

    public function getAllWithDetails(): array
    {
        return $this->db->fetchAll(
            "SELECT d.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, s.name as specialty_name, d.status as user_status,
                    dp.consultation_fee, dp.medical_license
             FROM doctors d
             LEFT JOIN users u ON d.user_id = u.id
             JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN doctor_profiles dp ON d.id = dp.doctor_id
             ORDER BY d.name ASC"
        );
    }

    public function searchWithDetails(string $search, string $specialtyId = ''): array
    {
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(d.name LIKE ? OR d.id_number LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($specialtyId)) {
            $conditions[] = "d.specialty_id = ?";
            $params[] = (int)$specialtyId;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            "SELECT d.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, s.name as specialty_name, d.status as user_status,
                    dp.consultation_fee, dp.medical_license
             FROM doctors d
             LEFT JOIN users u ON d.user_id = u.id
             JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN doctor_profiles dp ON d.id = dp.doctor_id
             {$where}
             ORDER BY d.name ASC",
            $params
        );
    }

    public function paginateWithDetails(int $page = 1, int $perPage = 10, string $search = '', string $specialtyId = ''): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(d.name LIKE ? OR d.id_number LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($specialtyId)) {
            $conditions[] = "d.specialty_id = ?";
            $params[] = (int)$specialtyId;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(*) as total 
                     FROM doctors d 
                     LEFT JOIN users u ON d.user_id = u.id 
                     JOIN specialties s ON d.specialty_id = s.id 
                     {$where}";
        $totalRow = $this->db->fetch($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        
        $sql = "SELECT d.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, s.name as specialty_name, d.status as user_status,
                    dp.consultation_fee, dp.medical_license
             FROM doctors d
             LEFT JOIN users u ON d.user_id = u.id
             JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN doctor_profiles dp ON d.id = dp.doctor_id
             {$where}
             ORDER BY d.name ASC
             LIMIT {$perPage} OFFSET {$offset}";
             
        $data = $this->db->fetchAll($sql, $params);
        
        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $total,
            'total_pages' => $totalPages
        ];
    }

    public function getConsultationFee(int $doctorId): float
    {
        $result = $this->db->fetch(
            "SELECT dp.consultation_fee FROM doctor_profiles dp WHERE dp.doctor_id = ?",
            [$doctorId]
        );
        return (float) ($result['consultation_fee'] ?? 0);
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch(
            "SELECT d.*, u.email as email, 'doctor' as roles 
             FROM doctors d 
             JOIN users u ON d.user_id = u.id 
             WHERE u.email = ? AND d.status = 'active'",
            [$email]
        );
    }

    public function shouldShowFee(int $doctorId): bool
    {
        $doctor = $this->findById($doctorId);
        if (!$doctor) return false;
        
        // Check global setting
        $setting = $this->db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'show_fee'"
        );
        $globalShow = ($setting['setting_value'] ?? '1') === '1';
        
        return $globalShow && (isset($doctor['show_fee']) ? $doctor['show_fee'] == 1 : true);
    }
}
