<?php
namespace App\Models;

use App\Helpers\Database;
use App\Helpers\Session;

/**
 * SecurityAudit — Auditoría de eventos de seguridad y autenticación.
 *
 * Registra eventos como: login exitoso/fallido, logout, bloqueos, 2FA, etc.
 * Esta tabla es DIFERENTE al audit_log operacional (que registra cambios en citas, pacientes, etc.)
 *
 * Uso:
 *   SecurityAudit::log($userId, 'login_exitoso', 'exitoso', ['ip' => '...']);
 */
class SecurityAudit extends BaseModel
{
    protected string $table = 'auditoria_seguridad';

    // ─── Eventos disponibles ────────────────────────────────────────────────
    public const EVENTO_LOGIN_EXITOSO              = 'login_exitoso';
    public const EVENTO_LOGIN_FALLIDO              = 'login_fallido';
    public const EVENTO_LOGOUT                     = 'logout';
    public const EVENTO_CUENTA_BLOQUEADA           = 'cuenta_bloqueada';
    public const EVENTO_CUENTA_SUSPENDIDA          = 'cuenta_suspendida';
    public const EVENTO_2FA_ENVIADO                = '2fa_enviado';
    public const EVENTO_2FA_EXITOSO                = '2fa_exitoso';
    public const EVENTO_2FA_FALLIDO                = '2fa_fallido';
    public const EVENTO_SESION_REVOCADA            = 'sesion_revocada';
    public const EVENTO_SESIONES_REVOCADAS_TODAS   = 'sesiones_revocadas_todas';
    public const EVENTO_PASSWORD_CAMBIADO          = 'password_cambiado';
    public const EVENTO_PASSWORD_RESET_SOLICITADO  = 'password_reset_solicitado';
    public const EVENTO_CUENTA_CREADA              = 'cuenta_creada';
    public const EVENTO_CUENTA_DESACTIVADA         = 'cuenta_desactivada';
    public const EVENTO_RATE_LIMIT_EXCEDIDO        = 'rate_limit_excedido';
    public const EVENTO_BOT_DETECTADO              = 'bot_detectado';
    public const EVENTO_BURST_EXCEDIDO             = 'burst_excedido';

    // ─── Resultados disponibles ─────────────────────────────────────────────
    public const RESULTADO_EXITOSO   = 'exitoso';
    public const RESULTADO_FALLIDO   = 'fallido';
    public const RESULTADO_BLOQUEADO = 'bloqueado';

    /**
     * Registra un evento de seguridad. Fallo silencioso — nunca interrumpe el flujo principal.
     *
     * @param int|null $userId   ID del usuario en `users`. NULL si no se pudo identificar (login fallido).
     * @param string   $evento   Constante EVENTO_* de esta clase.
     * @param string   $resultado Constante RESULTADO_* de esta clase.
     * @param array    $metadata  Datos contextuales adicionales (email intentado, motivo, etc.)
     */
    public static function log(
        ?int $userId,
        string $evento,
        string $resultado,
        array $metadata = []
    ): void {
        try {
            $db        = Database::getInstance();
            $ip        = self::resolveIp();
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            $db->execute(
                "INSERT INTO auditoria_seguridad
                    (id_usuario, evento, ip, user_agent, fecha, resultado, metadata)
                 VALUES (?, ?, ?, ?, NOW(), ?, ?)",
                [
                    $userId,
                    $evento,
                    $ip,
                    $userAgent,
                    $resultado,
                    !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                ]
            );
        } catch (\Throwable $e) {
            // Fallo silencioso: la auditoría nunca debe bloquear la operación principal.
            error_log('[SecurityAudit] Error al registrar evento "' . $evento . '": ' . $e->getMessage());
        }
    }

    /**
     * Retorna los últimos N eventos de seguridad de un usuario.
     */
    public function getRecentByUser(int $userId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM auditoria_seguridad
             WHERE id_usuario = ?
             ORDER BY fecha DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Cuenta cuántos login fallidos hubo desde una IP en los últimos N minutos.
     * Útil para reforzar el rate limiting y decidir si mostrar CAPTCHA.
     */
    public function countFailedLogins(string $ip, int $minutes = 15): int
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) AS total FROM auditoria_seguridad
             WHERE ip = ?
               AND evento = ?
               AND resultado = ?
               AND fecha >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$ip, self::EVENTO_LOGIN_FALLIDO, self::RESULTADO_FALLIDO, $minutes]
        );
        return (int)($result['total'] ?? 0);
    }

    /**
     * Lista paginada de todos los eventos para el panel de administración.
     *
     * @return array{data: array, total: int, pages: int}
     */
    public function getAll(int $page = 1, int $perPage = 50): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $data = $this->db->fetchAll(
            "SELECT aus.*, u.email AS usuario_email, u.nombre_usuario
             FROM auditoria_seguridad aus
             LEFT JOIN users u ON aus.id_usuario = u.id
             ORDER BY aus.fecha DESC
             LIMIT ? OFFSET ?",
            [$perPage, $offset]
        );

        $total = $this->count();
        return [
            'data'    => $data,
            'total'   => $total,
            'pages'   => (int)ceil($total / $perPage),
            'page'    => $page,
        ];
    }

    /**
     * Obtiene los últimos eventos de una IP específica.
     * Útil para investigar posibles ataques.
     */
    public function getByIp(string $ip, int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT aus.*, u.email AS usuario_email
             FROM auditoria_seguridad aus
             LEFT JOIN users u ON aus.id_usuario = u.id
             WHERE aus.ip = ?
             ORDER BY aus.fecha DESC
             LIMIT ?",
            [$ip, $limit]
        );
    }

    /**
     * Registra una alerta de seguridad crítica en el archivo de logs dedicado.
     */
    public static function logCriticalAlert(string $title, array $context = []): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
            }
            $logFile = $logDir . '/security_alerts.log';
            $ip = self::resolveIp();
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'none', 0, 200);
            $timestamp = date('Y-m-d H:i:s');
            $data = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '{}';
            $line = "[{$timestamp}] [ALERT] [IP: {$ip}] [UA: {$ua}] {$title} | Context: {$data}\n";
            @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {}
    }

    /**
     * Resuelve la IP real del cliente, considerando proxies y CDN (Cloudflare).
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
