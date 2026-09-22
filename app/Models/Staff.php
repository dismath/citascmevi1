<?php
namespace App\Models;

class Staff extends BaseModel
{
    protected string $table = 'staff';

    /**
     * Obtener todo el personal con datos de su cuenta de usuario
     */
    public function getAllWithUser(string $search = '', string $department = '', string $status = ''): array
    {
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(s.name LIKE ? OR s.id_number LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR s.position LIKE ?)";
            $term = '%' . $search . '%';
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        if (!empty($department)) {
            $conditions[] = "s.department = ?";
            $params[] = $department;
        }

        if (!empty($status)) {
            $conditions[] = "s.status = ?";
            $params[] = $status;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        return $this->db->fetchAll(
            "SELECT s.*, u.email as user_email, u.status as user_status,
                    (SELECT GROUP_CONCAT(r.name) 
                     FROM user_roles ur 
                     JOIN roles r ON ur.role_id = r.id 
                     WHERE ur.user_id = s.user_id) as roles
             FROM staff s
             LEFT JOIN users u ON s.user_id = u.id
             {$where}
             ORDER BY s.name ASC",
            $params
        );
    }

    /**
     * Obtener personal paginado con filtros
     */
    public function paginateWithUser(int $page = 1, int $perPage = 10, string $search = '', string $department = '', string $status = ''): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(s.name LIKE ? OR s.id_number LIKE ? OR s.email LIKE ? OR s.phone LIKE ? OR s.position LIKE ?)";
            $term = '%' . $search . '%';
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }

        if (!empty($department)) {
            $conditions[] = "s.department = ?";
            $params[] = $department;
        }

        if (!empty($status)) {
            $conditions[] = "s.status = ?";
            $params[] = $status;
        }

        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(*) as total FROM staff s LEFT JOIN users u ON s.user_id = u.id {$where}";
        $totalRow = $this->db->fetch($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $sql = "SELECT s.*, u.email as user_email, u.username, u.nombre_usuario, u.status as user_status,
                    (SELECT GROUP_CONCAT(r.name) 
                     FROM user_roles ur 
                     JOIN roles r ON ur.role_id = r.id 
                     WHERE ur.user_id = s.user_id) as roles
             FROM staff s
             LEFT JOIN users u ON s.user_id = u.id
             {$where}
             ORDER BY s.name ASC
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

    /**
     * Obtener un colaborador con su información de usuario vinculada
     */
    public function getByIdWithUser(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT s.*, u.email as user_email, u.username, u.nombre_usuario, u.status as user_status,
                    (SELECT r.name 
                     FROM user_roles ur 
                     JOIN roles r ON ur.role_id = r.id 
                     WHERE ur.user_id = s.user_id 
                     LIMIT 1) as primary_role,
                    (SELECT r.id 
                     FROM user_roles ur 
                     JOIN roles r ON ur.role_id = r.id 
                     WHERE ur.user_id = s.user_id 
                     LIMIT 1) as role_id
             FROM staff s
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.id = ?",
            [$id]
        );
    }

    /**
     * Alternar estado activo / inactivo
     */
    public function toggleStatus(int $id): bool
    {
        $staff = $this->findById($id);
        if (!$staff) {
            return false;
        }

        $newStatus = ($staff['status'] === 'active') ? 'inactive' : 'active';
        $this->update($id, ['status' => $newStatus]);

        // Si tiene usuario vinculado, sincronizar estado
        if (!empty($staff['user_id'])) {
            $this->db->execute(
                "UPDATE users SET status = ? WHERE id = ?",
                [$newStatus, $staff['user_id']]
            );
        }

        return true;
    }

    /**
     * Departamentos predefinidos para la clínica
     */
    public static function getDepartments(): array
    {
        return [
            'Recepción'            => 'Recepción',
            'Administración'       => 'Administración',
            'Caja / Facturación'   => 'Caja / Facturación',
            'Enfermería'           => 'Enfermería',
            'Recursos Humanos'     => 'Recursos Humanos',
            'Sistemas / TI'        => 'Sistemas / TI',
            'Atención al Cliente'  => 'Atención al Cliente',
            'Servicios Generales'  => 'Servicios Generales'
        ];
    }

    /**
     * Cargos habituales para personal de clínica
     */
    public static function getPositions(): array
    {
        return [
            'Recepcionista',
            'Asistente Dental / Médico',
            'Cajero / Facturador',
            'Administrador de Sede',
            'Enfermero / Enfermera',
            'Secretaria Médica',
            'Coordinador de Turnos',
            'Supervisor Administrativo'
        ];
    }
}
