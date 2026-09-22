<?php
namespace App\Models;

class Order extends BaseModel
{
    protected string $table = 'orders';

    public function getWithDetails(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT o.*, p.name as patient_name, p.id_number as patient_id_number, p.phone as patient_phone,
                    u.email as patient_email, a.appointment_date,
                    s.name as specialty_name, s.catalog_type_code
             FROM orders o
             JOIN patients p ON o.patient_id = p.id
             LEFT JOIN users u ON p.user_id = u.id
             JOIN appointments a ON o.appointment_id = a.id
             JOIN doctors d ON a.doctor_id = d.id
             JOIN specialties s ON d.specialty_id = s.id
             WHERE o.id = ?",
            [$id]
        );
    }

    public function getAllWithDetails(): array
    {
        return $this->db->fetchAll(
            "SELECT o.*, p.name as patient_name, a.appointment_date,
                    a.email_sent, a.status as app_status, a.id as appointment_id,
                    a.reschedule_count, a.attachment_url,
                    u.email as patient_email, s.catalog_type_code as specialty_type_code
             FROM orders o
             JOIN patients p ON o.patient_id = p.id
             JOIN appointments a ON o.appointment_id = a.id
             LEFT JOIN users u ON p.user_id = u.id
             LEFT JOIN doctors d ON a.doctor_id = d.id
             LEFT JOIN specialties s ON d.specialty_id = s.id
             ORDER BY o.created_at DESC"
        );
    }
}
