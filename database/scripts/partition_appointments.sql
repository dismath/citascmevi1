-- Guía de Particionamiento para Administradores de Base de Datos (DBA)
-- FASE 3: Escalabilidad
-- Este script NO debe ejecutarse automáticamente. Es una guía para cuando la tabla 
-- appointments supere el millón de registros y las consultas comiencen a degradarse.

-- ATENCIÓN: El particionamiento en MySQL requiere que todas las columnas usadas en
-- la partición sean parte de la Primary Key o Unique Key.
-- Si `id` es la única PK actualmente, debe cambiarse a (`id`, `appointment_date`).

-- 1. Respaldar la tabla actual (OBLIGATORIO)
-- CREATE TABLE appointments_backup AS SELECT * FROM appointments;

-- 2. Modificar la Llave Primaria para incluir la fecha de la cita
-- ALTER TABLE appointments DROP PRIMARY KEY, ADD PRIMARY KEY (id, appointment_date);

-- 3. Aplicar el particionamiento por rangos (Años)
/*
ALTER TABLE appointments 
PARTITION BY RANGE (YEAR(appointment_date)) (
    PARTITION p_history VALUES LESS THAN (2024), -- Todo antes del 2024
    PARTITION p2024 VALUES LESS THAN (2025),     -- Citas del 2024
    PARTITION p2025 VALUES LESS THAN (2026),     -- Citas del 2025
    PARTITION p2026 VALUES LESS THAN (2027),     -- Citas del 2026
    PARTITION p_future VALUES LESS THAN MAXVALUE -- Citas del 2027 en adelante
);
*/

-- Mantenimiento Anual:
-- Cada diciembre, el DBA debe dividir la partición p_future para acomodar el nuevo año.
/*
ALTER TABLE appointments REORGANIZE PARTITION p_future INTO (
    PARTITION p2027 VALUES LESS THAN (2028),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
*/
