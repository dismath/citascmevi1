<?php
namespace App\Models;

class Availability extends BaseModel
{
    protected string $table = 'doctor_availability';

    public function getAvailableDates(int $doctorId): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT available_date 
             FROM doctor_availability 
             WHERE doctor_id = ? AND status = 'available' 
               AND (locked_until IS NULL OR locked_until < NOW())
               AND available_date >= CURDATE()
               AND (available_date > CURDATE() OR start_time > CURTIME())
             ORDER BY available_date ASC",
            [$doctorId]
        );
    }

    public function getAvailableDatesBySpecialty(int $specialtyId): array
    {
        return $this->db->fetchAll(
            "SELECT da.available_date, COUNT(da.id) as slot_count
             FROM doctor_availability da
             JOIN doctors d ON da.doctor_id = d.id
             WHERE d.specialty_id = ? 
               AND da.status = 'available' 
               AND (da.locked_until IS NULL OR da.locked_until < NOW())
               AND da.available_date >= CURDATE()
               AND (da.available_date > CURDATE() OR da.start_time > CURTIME())
             GROUP BY da.available_date
             ORDER BY da.available_date ASC",
            [$specialtyId]
        );
    }

    public function getDoctorsWithSlotsByDate(int $specialtyId, string $date): array
    {
        $rows = $this->db->fetchAll(
            "SELECT d.id as doctor_id, d.name as doctor_name,
                    dp.consultation_fee, dp.medical_license,
                    d.show_fee as doc_show_fee,
                    ss.setting_value as global_show_fee,
                    da.id as slot_id, da.start_time, da.end_time, da.status as slot_status
             FROM doctors d
             JOIN doctor_availability da ON da.doctor_id = d.id
             LEFT JOIN doctor_profiles dp ON dp.doctor_id = d.id
             LEFT JOIN system_settings ss ON ss.setting_key = 'show_fee'
             WHERE d.specialty_id = ?
               AND da.available_date = ?
               AND d.status = 'active'
               AND (da.available_date > CURDATE() OR (da.available_date = CURDATE() AND da.start_time > CURTIME()))
             ORDER BY d.name ASC, da.start_time ASC",
            [$specialtyId, $date]
        );
        $doctors = [];
        foreach ($rows as $row) {
            $did = $row['doctor_id'];
            if (!isset($doctors[$did])) {
                $doctors[$did] = [
                    'id'               => $did,
                    'name'             => $row['doctor_name'],
                    'consultation_fee' => $row['consultation_fee'],
                    'medical_license'  => $row['medical_license'],
                    'show_fee'         => ((!isset($row['global_show_fee']) || $row['global_show_fee'] == '1') && $row['doc_show_fee'] == 1) ? '1' : '0',
                    'slots'            => [],
                ];
            }
            $doctors[$did]['slots'][] = [
                'id'         => $row['slot_id'],
                'start_time' => $row['start_time'],
                'end_time'   => $row['end_time'],
                'status'     => $row['slot_status'],
            ];
        }
        return array_values($doctors);
    }


    public function getAvailableSlots(int $doctorId, string $date): array
    {
        return $this->db->fetchAll(
            "SELECT id, start_time, end_time, status, locked_until, locked_by_session
             FROM doctor_availability
             WHERE doctor_id = ? AND available_date = ? AND status = 'available'
               AND (locked_until IS NULL OR locked_until < NOW())
               AND (available_date > CURDATE() OR start_time > CURTIME())
             ORDER BY start_time ASC",
            [$doctorId, $date]
        );
    }

    public function getSlotById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM doctor_availability WHERE id = ?",
            [$id]
        );
    }

    public function markAsBooked(int $id, int $appointmentId): void
    {
        $this->db->execute(
            "UPDATE doctor_availability SET status = 'booked', notes = ?, locked_until = NULL, locked_by_session = NULL WHERE id = ?",
            ["Cita #{$appointmentId}", $id]
        );
    }

    public function markAsAvailable(int $id): void
    {
        $this->db->execute(
            "UPDATE doctor_availability SET status = 'available', notes = NULL, locked_until = NULL, locked_by_session = NULL WHERE id = ?",
            [$id]
        );
    }

    public function lockSlot(int $id, string $sessionId, int $minutes = 5): bool
    {
        $count = $this->db->execute(
            "UPDATE doctor_availability 
             SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 locked_by_session = ?
             WHERE id = ? 
               AND status = 'available'
               AND (locked_until IS NULL OR locked_until < NOW())",
            [$minutes, $sessionId, $id]
        );
        return $count > 0;
    }

    public function generateSlots(int $doctorId, string $startDate, string $endDate, string $startTime, string $endTime, int $intervalMinutes): int
    {
        $count = 0;
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        
        $settings = new \App\Models\SystemSetting();
        $allowWeekends = $settings->get('allow_weekends', '0') === '1';

        while ($current <= $end) {
            // Skip weekends if not allowed by settings
            if (!$allowWeekends) {
                $dayOfWeek = (int) $current->format('w');
                if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                    $current->modify('+1 day');
                    continue;
                }
            }

            $slotStart = new \DateTime($current->format('Y-m-d') . ' ' . $startTime);
            $slotEnd = new \DateTime($current->format('Y-m-d') . ' ' . $endTime);

            while ($slotStart < $slotEnd) {
                $nextSlot = clone $slotStart;
                $nextSlot->modify("+{$intervalMinutes} minutes");

                if ($nextSlot > $slotEnd) break;

                // Check if slot already exists
                $exists = $this->db->fetch(
                    "SELECT id FROM doctor_availability 
                     WHERE doctor_id = ? AND available_date = ? AND start_time = ?",
                    [$doctorId, $current->format('Y-m-d'), $slotStart->format('H:i:s')]
                );

                if (!$exists) {
                    $this->db->execute(
                        "INSERT INTO doctor_availability (doctor_id, available_date, start_time, end_time, status)
                         VALUES (?, ?, ?, ?, 'available')",
                        [
                            $doctorId,
                            $current->format('Y-m-d'),
                            $slotStart->format('H:i:s'),
                            $nextSlot->format('H:i:s'),
                        ]
                    );
                    $count++;
                }

                $slotStart = $nextSlot;
            }

            $current->modify('+1 day');
        }

        return $count;
    }

    public function getByDoctorAndDateRange(int $doctorId, string $startDate, string $endDate): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM doctor_availability 
             WHERE doctor_id = ? AND available_date BETWEEN ? AND ?
             AND available_date >= CURDATE()
             ORDER BY available_date ASC, start_time ASC",
            [$doctorId, $startDate, $endDate]
        );
    }

    /**
     * Get monthly summary for a doctor: each date with counts per status.
     */
    public function getMonthSummary(int $doctorId, int $year, int $month): array
    {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $rows = $this->db->fetchAll(
            "SELECT available_date, status, COUNT(*) as cnt
             FROM doctor_availability
             WHERE doctor_id = ? AND available_date BETWEEN ? AND ?
             AND available_date >= CURDATE()
             GROUP BY available_date, status
             ORDER BY available_date ASC",
            [$doctorId, $startDate, $endDate]
        );

        $summary = [];
        foreach ($rows as $row) {
            $d = $row['available_date'];
            if (!isset($summary[$d])) {
                $summary[$d] = ['available' => 0, 'booked' => 0, 'blocked' => 0, 'total' => 0];
            }
            $summary[$d][$row['status']] = (int) $row['cnt'];
            $summary[$d]['total'] += (int) $row['cnt'];
        }
        return $summary;
    }

    /**
     * Delete unbooked slots for a doctor on a specific date.
     * Preserves slots that already have booked appointments.
     */
    public function deleteSlotsByDate(int $doctorId, string $date, bool $includeBlocked = true): int
    {
        if ($includeBlocked) {
            return $this->db->execute(
                "DELETE FROM doctor_availability 
                 WHERE doctor_id = ? AND available_date = ? 
                   AND status IN ('available', 'blocked') 
                   AND appointment_id IS NULL",
                [$doctorId, $date]
            );
        }

        return $this->db->execute(
            "DELETE FROM doctor_availability 
             WHERE doctor_id = ? AND available_date = ? 
               AND status = 'available' 
               AND appointment_id IS NULL",
            [$doctorId, $date]
        );
    }
}
