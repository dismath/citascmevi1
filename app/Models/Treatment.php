<?php
namespace App\Models;

class Treatment extends BaseModel
{
    protected string $table = 'treatments';

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO {$this->table} 
             (appointment_id, patient_id, doctor_id, medical_note_id, treatment_type, title, description, frequency, duration, status, start_date, end_date, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['appointment_id'] ?? null,
                (int)$data['patient_id'],
                (int)$data['doctor_id'],
                $data['medical_note_id'] ?? null,
                $data['treatment_type'] ?? 'Tratamiento Médico',
                $data['title'],
                $data['description'],
                $data['frequency'] ?? null,
                $data['duration'] ?? null,
                $data['status'] ?? 'active',
                $data['start_date'] ?? date('Y-m-d'),
                $data['end_date'] ?? null
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function getByPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, d.name as doctor_name, a.appointment_date
             FROM {$this->table} t
             LEFT JOIN doctors d ON t.doctor_id = d.id
             LEFT JOIN appointments a ON t.appointment_id = a.id
             WHERE t.patient_id = ?
             ORDER BY t.created_at DESC",
            [$patientId]
        );
    }

    public function getByAppointment(int $appointmentId): array
    {
        return $this->db->fetchAll(
            "SELECT t.*, d.name as doctor_name
             FROM {$this->table} t
             LEFT JOIN doctors d ON t.doctor_id = d.id
             WHERE t.appointment_id = ?
             ORDER BY t.created_at DESC",
            [$appointmentId]
        );
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET status = ? WHERE id = ?",
            [$status, $id]
        );
    }
}
