<?php
namespace App\Models;

class Patient extends BaseModel
{
    protected string $table = 'patients';

    public function getAllWithDetails(): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, p.status as user_status,
                    (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as total_appointments
             FROM patients p
             LEFT JOIN users u ON p.user_id = u.id
             ORDER BY p.name ASC"
        );
    }

    /**
     * Search patients with a single search term: name, id_number, or email
     */
    public function searchWithDetails(string $search): array
    {
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];

        $where = "WHERE p.name LIKE ? OR p.id_number LIKE ? OR u.email LIKE ? OR u.username LIKE ?";

        return $this->db->fetchAll(
            "SELECT p.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, p.status as user_status,
                    (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as total_appointments
             FROM patients p
             LEFT JOIN users u ON p.user_id = u.id
             {$where}
             ORDER BY p.name ASC",
            $params
        );
    }

    public function paginateWithDetails(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = '';
        $params = [];

        if (!empty($search)) {
            $searchTerm = '%' . $search . '%';
            $where = "WHERE p.name LIKE ? OR p.id_number LIKE ? OR u.email LIKE ? OR u.username LIKE ?";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }

        $countSql = "SELECT COUNT(*) as total FROM patients p LEFT JOIN users u ON p.user_id = u.id {$where}";
        $totalRow = $this->db->fetch($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $sql = "SELECT p.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, p.status as user_status,
                    (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) as total_appointments
             FROM patients p
             LEFT JOIN users u ON p.user_id = u.id
             {$where}
             ORDER BY p.name ASC
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

    public function getByIdWithUser(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT p.*, u.email as email, u.username, u.nombre_usuario, u.id as user_id, p.status as user_status
             FROM patients p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function getByIdNumber(string $idNumber): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM patients WHERE id_number = ?",
            [$idNumber]
        );
    }

    public function findByEmail(string $email): array|false
    {
        return $this->db->fetch(
            "SELECT p.*, u.email as email, 'patient' as roles 
             FROM patients p 
             JOIN users u ON p.user_id = u.id 
             WHERE u.email = ? AND p.status = 'active'",
            [$email]
        );
    }

    public function findOrCreate(array $data): int
    {
        $existing = $this->getByIdNumber($data['id_number']);
        if ($existing) {
            return $existing['id'];
        }
        return $this->create($data);
    }
}
