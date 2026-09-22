<?php
namespace App\Models;

use App\Helpers\Database;
use App\Helpers\Session;

/**
 * ClinicalAudit — Auditoría de accesos a información clínica del paciente.
 *
 * Registra eventos DIFERENTES al audit_log operacional:
 *   - audit_log  → "Admin actualizó la cita #23" (cambio de datos)
 *   - ClinicalAudit → "Dr. García vio la historia clínica del Paciente Juan Pérez" (acceso a info)
 *
 * Esta distinción es fundamental para cumplimiento de privacidad (HIPAA-like).
 *
 * Uso:
 *   ClinicalAudit::log($userId, 'historia_clinica', $patientId, $patientId, 'ver');
 *   // O desde el helper:
 *   Audit::clinicalAccess('nota_medica', $noteId, $patientId, 'crear');
 */
class ClinicalAudit extends BaseModel
{
    protected string $table = 'auditoria_clinica';

    // ─── Tipos de recurso clínico ────────────────────────────────────────────
    public const RECURSO_HISTORIA_CLINICA    = 'historia_clinica';
    public const RECURSO_NOTA_MEDICA         = 'nota_medica';
    public const RECURSO_RECETA              = 'receta';
    public const RECURSO_ORDEN_LABORATORIO   = 'orden_laboratorio';
    public const RECURSO_ORDEN_IMAGEN        = 'orden_imagen';
    public const RECURSO_CITA               = 'cita';
    public const RECURSO_DOCUMENTO          = 'documento';

    // ─── Acciones ────────────────────────────────────────────────────────────
    public const ACCION_VER        = 'ver';
    public const ACCION_CREAR      = 'crear';
    public const ACCION_EDITAR     = 'editar';
    public const ACCION_ELIMINAR   = 'eliminar';
    public const ACCION_IMPRIMIR   = 'imprimir';
    public const ACCION_EXPORTAR   = 'exportar';
    public const ACCION_DESCARGAR  = 'descargar';

    /**
     * Registra un acceso a información clínica. Fallo silencioso.
     *
     * @param int    $userId      ID del usuario que accede (médico, admin, recepcionista) — de users.id
     * @param string $tipoRecurso Constante RECURSO_* de esta clase.
     * @param int|null $idRecurso ID del registro específico (null para listados generales).
     * @param int    $idPaciente  ID del paciente en tabla `patients`.
     * @param string $accion      Constante ACCION_* de esta clase.
     * @param array  $metadata    Datos adicionales: nombre_paciente, diagnóstico, etc.
     */
    public static function log(
        int $userId,
        string $tipoRecurso,
        ?int $idRecurso,
        int $idPaciente,
        string $accion,
        array $metadata = []
    ): void {
        try {
            $db        = Database::getInstance();
            $ip        = self::resolveIp();

            $db->execute(
                "INSERT INTO auditoria_clinica
                    (id_usuario_acceso, tipo_recurso, id_recurso, id_paciente_afectado, accion, ip, fecha, metadata)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)",
                [
                    $userId,
                    $tipoRecurso,
                    $idRecurso,
                    $idPaciente,
                    $accion,
                    $ip,
                    !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable $e) {
            // Fallo silencioso: el acceso médico no debe bloquearse por un fallo de auditoría.
            error_log('[ClinicalAudit] Error al registrar acceso clínico: ' . $e->getMessage());
        }
    }

    /**
     * Historial de todos los accesos a la información de un paciente específico.
     * "¿Quién ha visto la historia de Juan Pérez?"
     *
     * @return array Lista de accesos con nombre del médico/usuario que accedió.
     */
    public function getByPatient(int $patientId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT
                ac.*,
                u.email           AS usuario_email,
                u.nombre_usuario  AS usuario_nombre,
                COALESCE(
                    (SELECT r.name FROM user_roles ur JOIN roles r ON ur.role_id = r.id WHERE ur.user_id = u.id LIMIT 1),
                    'desconocido'
                ) AS usuario_rol
             FROM auditoria_clinica ac
             JOIN users u ON ac.id_usuario_acceso = u.id
             WHERE ac.id_paciente_afectado = ?
             ORDER BY ac.fecha DESC
             LIMIT ?",
            [$patientId, $limit]
        );
    }

    /**
     * Historial de qué accedió un médico o usuario (trazabilidad por profesional).
     * "¿A qué pacientes accedió el Dr. García hoy?"
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT
                ac.*,
                p.name AS paciente_nombre,
                p.id_number AS paciente_cedula
             FROM auditoria_clinica ac
             JOIN patients p ON ac.id_paciente_afectado = p.id
             WHERE ac.id_usuario_acceso = ?
             ORDER BY ac.fecha DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Lista paginada de todos los accesos clínicos para el panel de administración.
     *
     * @return array{data: array, total: int, pages: int}
     */
    public function getAll(int $page = 1, int $perPage = 50): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $data = $this->db->fetchAll(
            "SELECT
                ac.*,
                u.email          AS usuario_email,
                u.nombre_usuario AS usuario_nombre,
                p.name           AS paciente_nombre
             FROM auditoria_clinica ac
             JOIN users    u ON ac.id_usuario_acceso    = u.id
             JOIN patients p ON ac.id_paciente_afectado = p.id
             ORDER BY ac.fecha DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );

        $total = $this->count();
        return [
            'data'  => $data,
            'total' => $total,
            'pages' => (int)ceil($total / $perPage),
            'page'  => $page,
        ];
    }

    /**
     * Resuelve la IP real del cliente.
     */
    private static function resolveIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return 'unknown';
    }
}
