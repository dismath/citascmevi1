<?php
namespace App\Models;

class Prescription extends BaseModel
{
    protected string $table = 'prescriptions';

    public function create(array $data): int
    {
        $this->db->execute(
            "INSERT INTO {$this->table}
             (appointment_id, medical_note_id, patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, is_printed, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())",
            [
                $data['appointment_id'] ?? null,
                $data['medical_note_id'] ?? null,
                (int)$data['patient_id'],
                (int)$data['doctor_id'],
                $data['medication_name'],
                $data['dosage'],
                $data['frequency'],
                $data['duration'],
                $data['instructions'] ?? null
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function getByPatient(int $patientId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, d.name as doctor_name, d.id_number as doctor_id_number,
                    s.name as specialty_name, a.appointment_date
             FROM {$this->table} p
             LEFT JOIN doctors d ON p.doctor_id = d.id
             LEFT JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN appointments a ON p.appointment_id = a.id
             WHERE p.patient_id = ?
             ORDER BY p.created_at DESC",
            [$patientId]
        );
    }

    public function getByAppointment(int $appointmentId): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, d.name as doctor_name
             FROM {$this->table} p
             LEFT JOIN doctors d ON p.doctor_id = d.id
             WHERE p.appointment_id = ?
             ORDER BY p.created_at DESC",
            [$appointmentId]
        );
    }

    public function getByIdWithDetails(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT p.*, 
                    pat.name as patient_name, pat.id_number as patient_id_number, pat.phone as patient_phone, pat.date_of_birth,
                    d.name as doctor_name, d.id_number as doctor_id_number, d.phone as doctor_phone,
                    s.name as specialty_name,
                    a.appointment_date
             FROM {$this->table} p
             JOIN patients pat ON p.patient_id = pat.id
             JOIN doctors d ON p.doctor_id = d.id
             LEFT JOIN specialties s ON d.specialty_id = s.id
             LEFT JOIN appointments a ON p.appointment_id = a.id
             WHERE p.id = ?
             LIMIT 1",
            [$id]
        );
    }

    public function markAsPrinted(int $id): bool
    {
        return $this->db->execute(
            "UPDATE {$this->table} SET is_printed = 1, printed_at = NOW() WHERE id = ?",
            [$id]
        );
    }
}
