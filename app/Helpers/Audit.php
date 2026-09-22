<?php
namespace App\Helpers;

/**
 * Audit Helper — Auditoría operacional y clínica.
 *
 * Existen DOS tipos de auditoría en el sistema:
 *
 *  1. Audit::log()            → audit_log         — cambios en datos (citas, pacientes, órdenes).
 *     Audit::logAction()      → audit_log         — alias simple para cambios.
 *
 *  2. Audit::clinicalAccess() → auditoria_clinica — QUIÉN accedió a la info clínica de un paciente.
 *     (Distinto: no es "se actualizó", sino "se VIO la historia del paciente X")
 *
 * Los fallos de auditoría son silenciosos para no interrumpir el flujo principal.
 */
class Audit
{
    /**
     * Registra una acción en el log de auditoría.
     *
     * @param string   $action    Acción realizada: 'create', 'update', 'delete', 'status_change', 'login', 'logout'
     * @param string   $entity    Nombre de la entidad afectada: 'appointments', 'patients', 'users', etc.
     * @param int|null $entityId  ID del registro afectado.
     * @param array|null $oldValue Estado del registro ANTES del cambio.
     * @param array|null $newValue Estado del registro DESPUÉS del cambio.
     */
    public static function log(
        string $action,
        string $entity,
        ?int $entityId = null,
        ?array $oldValue = null,
        ?array $newValue = null
    ): void {
        try {
            $db      = Database::getInstance();
            $userId  = Session::userId();
            $ip      = self::getClientIp();

            $db->execute(
                "INSERT INTO audit_log (user_id, action, entity, entity_id, old_value, new_value, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $action,
                    $entity,
                    $entityId,
                    $oldValue ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null,
                    $newValue ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null,
                    $ip,
                ]
            );
        } catch (\Exception $e) {
            // Fallo silencioso: la auditoría no debe bloquear la operación principal.
            error_log('[Audit] Error al registrar log: ' . $e->getMessage());
        }
    }

    /**
     * Atajo para registrar una acción simple (sin estado anterior/posterior complejo).
     *
     * @param string $action   Acción realizada.
     * @param string $entity   Entidad afectada.
     * @param int    $entityId ID del registro.
     * @param string $detail   Descripción breve del cambio (se guarda en new_value).
     */
    public static function logAction(string $action, string $entity, int $entityId, string $detail): void
    {
        self::log($action, $entity, $entityId, null, ['detail' => $detail]);
    }

    /**
     * Registra un acceso a información clínica del paciente.
     *
     * Diferente al audit_log operacional:
     *   - audit_log          → "Admin actualizó la cita #23"
     *   - clinicalAccess()   → "Dr. García VIO la historia clínica del Paciente Juan"
     *
     * @param string   $tipoRecurso  Constante de ClinicalAudit::RECURSO_*
     *                               Ej: 'historia_clinica', 'nota_medica', 'receta'
     * @param int|null $idRecurso    ID del registro específico (null para listados)
     * @param int      $idPaciente   ID del paciente en tabla patients
     * @param string   $accion       Constante ClinicalAudit::ACCION_* ('ver','crear','editar'...)
     * @param array    $metadata     Datos adicionales: nombre_paciente, diagnóstico, etc.
     */
    public static function clinicalAccess(
        string $tipoRecurso,
        ?int $idRecurso,
        int $idPaciente,
        string $accion,
        array $metadata = []
    ): void {
        $userId = Session::userId();
        if (!$userId) {
            return; // Sin usuario autenticado no se puede auditar el acceso
        }
        \App\Models\ClinicalAudit::log($userId, $tipoRecurso, $idRecurso, $idPaciente, $accion, $metadata);
    }

    /**
     * Resuelve la IP real del cliente, considerando proxies de confianza.
     */
    private static function getClientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                // X-Forwarded-For puede tener múltiples IPs separadas por coma
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return 'unknown';
    }
}

