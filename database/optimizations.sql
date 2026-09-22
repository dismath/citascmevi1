-- Portal Cmevi Pro - Optimizaciones de Alto Rendimiento (High Concurrency)
-- Ejecutar este script para preparar la base de datos para 200-500 usuarios concurrentes

-- 1. Índices para el Motor de Búsqueda de Turnos y Disponibilidad (Crítico para el Wizard)
-- Optimiza las consultas AJAX que se disparan masivamente al cambiar fechas
ALTER TABLE `doctor_availability` 
    ADD INDEX `idx_availability_lookup` (`doctor_id`, `available_date`, `status`),
    ADD INDEX `idx_availability_date` (`available_date`);

-- 2. Índices para el Panel Administrativo (Dashboard y Listados)
-- Optimiza el conteo de citas de hoy y búsquedas por estado
ALTER TABLE `appointments`
    ADD INDEX `idx_appointments_date_status` (`appointment_date`, `status`),
    ADD INDEX `idx_appointments_patient` (`patient_id`),
    ADD INDEX `idx_appointments_doctor` (`doctor_id`);

-- 3. Índices para Autenticación Rápida
-- Acelera el proceso de Login bajo ataques o picos de tráfico
ALTER TABLE `users`
    ADD INDEX `idx_users_login` (`email`, `status`);

-- Acelera la verificación de tokens de la API móvil
ALTER TABLE `api_tokens`
    ADD INDEX `idx_api_tokens_hash` (`token_hash`, `expires_at`);

-- 4. Optimización de Búsqueda de Pacientes (Para validaciones en el Wizard)
ALTER TABLE `patients`
    ADD INDEX `idx_patients_id_lookup` (`id_number`);

-- 5. Optimización del Catálogo Médico
ALTER TABLE `specialties`
    ADD INDEX `idx_specialties_filtering` (`status`, `is_service`, `is_laboratory`);

-- NOTA TÉCNICA PARA EL SERVIDOR MySQL:
-- Asegúrese de que su archivo my.cnf / my.ini tenga optimizado lo siguiente para 500 usuarios:
-- max_connections = 10
-- innodb_buffer_pool_size = (70% de la RAM disponible)
-- innodb_log_file_size = 512M
-- innodb_flush_log_at_trx_commit = 2 (Mejora el rendimiento de I/O en escrituras concurrentes de citas)

-- 6. Tabla optimizada para Rate Limiting (Protección Anti-Fuerza Bruta)
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `ip_address` VARCHAR(45) NOT NULL,
    `endpoint` VARCHAR(100) NOT NULL,
    `time_bucket` INT NOT NULL,
    `hits` INT DEFAULT 1,
    PRIMARY KEY (`ip_address`, `endpoint`, `time_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
