<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\RateLimiter;
use App\Models\User;
use App\Models\SecurityAudit;

/**
 * AuthController — Autenticación unificada para todos los roles.
 *
 * Un único formulario de login resuelve admin, recepcionista, doctor y paciente.
 * El triple-lookup anterior (users → doctors → patients) fue reemplazado por
 * un único query en `users` usando User::findUnifiedByLogin().
 */
class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
        Session::start();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FORMULARIO DE LOGIN
    // ─────────────────────────────────────────────────────────────────────────

    public function loginForm(): void
    {
        if (Session::isLoggedIn()) {
            $this->redirectByRole();
        }

        if (isset($_GET['setup_cancelled'])) {
            Session::flash('info', 'Ha cancelado la creación de contraseña. Podrá establecerla en cualquier momento desde el enlace de su correo (válido por 24 horas), o ingresar con su contraseña temporal.');
        }

        View::render('auth.login', [
            'title' => 'Iniciar Sesión',
        ], 'main');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROCESO DE LOGIN
    // ─────────────────────────────────────────────────────────────────────────

    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/login');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 1. Detección de Bots vía Honeypot invisible
        if (!empty($_POST['website_hp'])) {
            SecurityAudit::log(
                null,
                SecurityAudit::EVENTO_BOT_DETECTADO,
                SecurityAudit::RESULTADO_BLOQUEADO,
                ['motivo' => 'Honeypot triggered', 'ip' => $ip]
            );
            SecurityAudit::logCriticalAlert("Bot detectado vía honeypot en /login", ['ip' => $ip]);
            usleep(1000000);
            Session::flash('error', 'Credenciales incorrectas o usuario inactivo.');
            View::redirect('/login');
            return;
        }

        // 2. Rate limit por IP ANTES de cualquier otra validación (5 intentos cada 15 min)
        RateLimiter::check('login_web', 5, 900, $ip);

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido. Por favor intente de nuevo.');
            View::redirect('/login');
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            Session::flash('error', 'Por favor complete todos los campos.');
            View::redirect('/login');
        }

        // Permitir correo electrónico o nombre de usuario
        if (strpos($email, '@') !== false && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El formato del correo electrónico no es válido.');
            View::redirect('/login');
            return;
        }

        // ── LOOKUP UNIFICADO: un único query para todos los roles ─────────────
        $user = $this->userModel->findUnifiedByLogin($email);

        // 3. Bloqueo Temporal de Cuenta (Si acumuló 5 fallos en 15 minutos)
        if ($user && $this->userModel->isAccountLocked($user)) {
            SecurityAudit::log(
                (int)$user['id'],
                SecurityAudit::EVENTO_CUENTA_BLOQUEADA,
                SecurityAudit::RESULTADO_BLOQUEADO,
                ['email' => $email, 'locked_until' => $user['locked_until'] ?? '']
            );
            usleep(1500000); // Throttling preventivo
            Session::flash('error', 'Credenciales incorrectas o usuario inactivo.');
            View::redirect('/login');
            return;
        }

        // 4. Throttling Progresivo según intentos fallidos previos
        $failedAttempts = (int)($user['failed_login_attempts'] ?? 0);
        if ($failedAttempts >= 3) {
            usleep(2000000); // 2 segundos
        } elseif ($failedAttempts >= 2) {
            usleep(1000000); // 1 segundo
        } elseif ($failedAttempts >= 1) {
            usleep(500000);  // 0.5 segundos
        }

        if ($user && password_verify($password, $user['password'])) {

            RateLimiter::clear('login_web', $ip);
            $this->userModel->resetFailedAttempts((int)$user['id']);

            // ── Verificar 2FA ─────────────────────────────────────────────────
            if ($this->requires2FA($user)) {
                $this->initiate2FA($user, $ip);
                return;
            }

            // ── Login completo (sin 2FA) ──────────────────────────────────────
            $this->completeLogin($user, $ip);

        } else {

            // Incrementar contador de intentos fallidos de la cuenta si el usuario existe
            if ($user) {
                $this->userModel->incrementFailedAttempts((int)$user['id']);
            }

            // Registrar intento fallido en auditoría de seguridad
            SecurityAudit::log(
                $user['id'] ?? null,
                SecurityAudit::EVENTO_LOGIN_FALLIDO,
                SecurityAudit::RESULTADO_FALLIDO,
                ['email_intentado' => $email]
            );

            Session::flash('error', 'Credenciales incorrectas o usuario inactivo.');
            View::redirect('/login');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2FA — FORMULARIO
    // ─────────────────────────────────────────────────────────────────────────

    public function twoFactorForm(): void
    {
        if (Session::isLoggedIn()) {
            $this->redirectByRole();
            return;
        }

        if (empty($_SESSION['2fa_pending'])) {
            View::redirect('/login');
            return;
        }

        $pending = $_SESSION['2fa_pending'];
        $email   = $pending['user_email'];

        // Enmascarar email para privacidad (ej: j****o@gmail.com)
        $parts      = explode('@', $email);
        $namePart   = $parts[0];
        $domainPart = $parts[1] ?? '';
        $maskedName = (strlen($namePart) <= 2)
            ? $namePart . '***'
            : substr($namePart, 0, 2) . str_repeat('*', max(3, strlen($namePart) - 2));
        $maskedEmail = $maskedName . '@' . $domainPart;

        $twoFactorModel   = new \App\Models\TwoFactorCode();
        $secondsRemaining = $twoFactorModel->getSecondsRemaining($pending['user_type'], (int)$pending['user_id']);
        $canResend        = $twoFactorModel->canResend($pending['user_type'], (int)$pending['user_id'], 60);

        View::render('auth.verify_2fa', [
            'title'            => 'Verificación de Seguridad (2FA)',
            'maskedEmail'      => $maskedEmail,
            'secondsRemaining' => $secondsRemaining,
            'canResend'        => $canResend,
            'userName'         => $pending['user_name'],
        ], 'main');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2FA — VERIFICACIÓN DEL CÓDIGO
    // ─────────────────────────────────────────────────────────────────────────

    public function verifyTwoFactor(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/login/2fa');
            return;
        }

        // Si ya está autenticado (evita bucles si llega petición duplicada o recarga previa), ir al dashboard
        if (Session::isLoggedIn()) {
            $this->redirectByRole();
            return;
        }

        if (empty($_SESSION['2fa_pending'])) {
            View::redirect('/login');
            return;
        }

        $pending = $_SESSION['2fa_pending'];
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Rate limit para prevenir fuerza bruta sobre el código 2FA
        RateLimiter::check('2fa_verify_' . $pending['user_id'], 5, 600, $ip);

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/login/2fa');
        }

        // Aceptar código desde un campo único o desde campos individuales (UI flexible)
        $code = trim($_POST['code'] ?? '');
        if (empty($code) && isset($_POST['code_1'])) {
            $code = '';
            for ($i = 1; $i <= 6; $i++) {
                $code .= trim($_POST["code_$i"] ?? '');
            }
        }
        $code = preg_replace('/\D/', '', $code); // Solo dígitos

        if (strlen($code) !== 6) {
            Session::flash('error', 'Por favor ingrese el código de 6 dígitos completo.');
            View::redirect('/login/2fa');
        }

        $twoFactorModel = new \App\Models\TwoFactorCode();
        $isValid        = $twoFactorModel->verify($pending['user_type'], (int)$pending['user_id'], $code);

        if (!$isValid) {
            SecurityAudit::log(
                (int)$pending['user_id'],
                SecurityAudit::EVENTO_2FA_FALLIDO,
                SecurityAudit::RESULTADO_FALLIDO
            );
            Session::flash('error', 'El código de seguridad es inválido o ha expirado. Solicite un nuevo código.');
            View::redirect('/login/2fa');
            return;
        }

        // Código válido — limpiar rate limiter y completar login
        RateLimiter::clear('2fa_verify_' . $pending['user_id'], $ip);

        SecurityAudit::log(
            (int)$pending['user_id'],
            SecurityAudit::EVENTO_2FA_EXITOSO,
            SecurityAudit::RESULTADO_EXITOSO
        );

        // Recuperar el registro completo del usuario desde users (lookup unificado)
        $user = $this->userModel->findById((int)$pending['user_id']);

        if (!$user) {
            Session::flash('error', 'Usuario no encontrado en el sistema.');
            View::redirect('/login');
            return;
        }

        // Enriquecer con roles (el findById base no hace el JOIN)
        $userWithRoles = $this->userModel->findUnifiedByLogin($user['email']);
        if ($userWithRoles) {
            $user = $userWithRoles;
        }

        $this->completeLogin($user, $ip);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2FA — REENVIAR CÓDIGO
    // ─────────────────────────────────────────────────────────────────────────

    public function resendTwoFactor(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/login/2fa');
            return;
        }

        if (Session::isLoggedIn()) {
            $this->redirectByRole();
            return;
        }

        if (empty($_SESSION['2fa_pending'])) {
            View::redirect('/login');
            return;
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/login/2fa');
        }

        $pending        = $_SESSION['2fa_pending'];
        $twoFactorModel = new \App\Models\TwoFactorCode();

        if (!$twoFactorModel->canResend($pending['user_type'], (int)$pending['user_id'], 60)) {
            Session::flash('error', 'Por favor espere un momento antes de solicitar un nuevo código.');
            View::redirect('/login/2fa');
            return;
        }

        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $code = $twoFactorModel->generate($pending['user_type'], (int)$pending['user_id'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');

        try {
            \App\Helpers\Mailer::sendTwoFactorCode($pending['user_email'], $code, $pending['user_name']);
        } catch (\Throwable $e) {
            error_log('[AuthController] Error reenviando código 2FA: ' . $e->getMessage());
        }

        SecurityAudit::log(
            (int)$pending['user_id'],
            SecurityAudit::EVENTO_2FA_ENVIADO,
            SecurityAudit::RESULTADO_EXITOSO,
            ['reenvio' => true]
        );

        Session::flash('success', 'Hemos enviado un nuevo código de verificación a su correo.');
        View::redirect('/login/2fa');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2FA — CANCELAR
    // ─────────────────────────────────────────────────────────────────────────

    public function cancelTwoFactor(): void
    {
        unset($_SESSION['2fa_pending']);
        View::redirect('/login');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────────────────────────────────

    public function logout(): void
    {
        $userId = Session::userId();

        if ($userId) {
            SecurityAudit::log(
                $userId,
                SecurityAudit::EVENTO_LOGOUT,
                SecurityAudit::RESULTADO_EXITOSO
            );
        }

        Session::destroy();
        View::redirect('/login');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REVOCAR TODAS LAS SESIONES (teléfono perdido / cuenta comprometida)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /account/revoke-sessions
     *
     * Marca todas las sesiones del usuario como revocadas en `sesiones_usuario`
     * y destruye la sesión actual. El usuario debe volver a iniciar sesión.
     */
    public function revokeAllSessions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/login');
        }

        $userId = Session::userId();
        if (!$userId) {
            View::redirect('/login');
        }

        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido.');
            View::redirect('/login');
        }

        $this->userModel->revokeAllSessions($userId);

        SecurityAudit::log(
            $userId,
            SecurityAudit::EVENTO_SESIONES_REVOCADAS_TODAS,
            SecurityAudit::RESULTADO_EXITOSO,
            ['ip_solicitante' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']
        );

        Session::destroy();
        Session::flash('success', 'Todas las sesiones han sido cerradas. Inicie sesión nuevamente.');
        View::redirect('/login');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MÉTODOS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Determina si el usuario requiere 2FA según configuración del sistema y por usuario.
     */
    private function requires2FA(array $user): bool
    {
        // 1. Si el usuario activó explícitamente 2FA en su perfil personal:
        if (isset($user['2fa_enabled']) && (int)$user['2fa_enabled'] === 1) {
            return true;
        }

        // 2. Si no lo activó individualmente, verificar si el sistema global lo tiene activado por rol:
        $settings      = new \App\Models\SystemSetting();
        $globalEnabled = ($settings->get('two_factor_enabled', '0') === '1');

        if (!$globalEnabled) {
            return false;
        }

        // Fallback: configuración por tipo de usuario (setting global)
        return match($user['user_type']) {
            'admin'   => ($settings->get('two_factor_admin',   '1') === '1'),
            'doctor'  => ($settings->get('two_factor_doctor',  '1') === '1'),
            'patient' => ($settings->get('two_factor_patient', '0') === '1'),
            default   => false,
        };
    }

    /**
     * Inicia el flujo 2FA: genera código, envía email y guarda estado en sesión.
     */
    private function initiate2FA(array $user, string $ip): void
    {
        $userName = $user['nombre_usuario'] ?? $user['name'] ?? $user['email'];

        $_SESSION['2fa_pending'] = [
            'user_id'      => (int)$user['id'],
            'user_type'    => $user['user_type'],
            'user_email'   => $user['email'],
            'user_name'    => $userName,
            'user_roles'   => $user['roles'] ?? '',
            'ip'           => $ip,
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'initiated_at' => time(),
        ];

        $twoFactorModel = new \App\Models\TwoFactorCode();
        $code = $twoFactorModel->generate($user['user_type'], (int)$user['id'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? '');

        try {
            \App\Helpers\Mailer::sendTwoFactorCode($user['email'], $code, $userName);
        } catch (\Throwable $e) {
            error_log('[AuthController] Error enviando código 2FA: ' . $e->getMessage());
        }

        SecurityAudit::log(
            (int)$user['id'],
            SecurityAudit::EVENTO_2FA_ENVIADO,
            SecurityAudit::RESULTADO_EXITOSO
        );

        Session::flash('info', 'Hemos enviado un código de verificación de 6 dígitos a su correo electrónico.');
        View::redirect('/login/2fa');
    }

    /**
     * Completa el proceso de login: crea sesión, asigna variables de sesión,
     * registra en auditoría y redirige al dashboard correspondiente.
     */
    private function completeLogin(array $user, string $ip): void
    {
        RateLimiter::clear('login_web', $ip);

        $userId      = (int)$user['id'];
        $userType    = $user['user_type']    ?? 'admin';
        $primaryRole = $user['primary_role'] ?? 'user';
        $userName    = $user['nombre_usuario'] ?? $user['name'] ?? $user['email'];

        // ── Variables de sesión (establecer ANTES de regenerar para evitar sesiones vacías en vuelo) ─
        Session::set('user_id',    $userId);
        Session::set('user_type',  $userType);
        Session::set('user_email', $user['email']);
        Session::set('user_role',  $primaryRole);
        Session::set('user_name',  $userName);

        // Permisos por rol asignados desde la matriz de roles y permisos
        $perms     = $this->userModel->getPermissions($userId);
        $permNames = !empty($perms) ? array_column($perms, 'name') : [];
        Session::set('user_permissions', $permNames);

        // Limpiar estado temporal 2FA y forzar nuevo CSRF para próximas vistas
        unset($_SESSION['2fa_pending']);
        unset($_SESSION['csrf_token']);

        // FIX-SEC-06: Prevenir Session Fixation — regeneración segura
        Session::regenerateId();

        // ── Actualizar last_activity en users ─────────────────────────────────
        $this->userModel->updateLastActivity($userId);

        // ── Registrar sesión en sesiones_usuario ──────────────────────────────
        $this->userModel->createSession($userId, session_id(), [
            'ip'         => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);

        // ── Auditoría de seguridad ────────────────────────────────────────────
        SecurityAudit::log(
            $userId,
            SecurityAudit::EVENTO_LOGIN_EXITOSO,
            SecurityAudit::RESULTADO_EXITOSO,
            [
                'rol'         => $primaryRole,
                'user_type'   => $userType,
                'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
            ]
        );

        // ── Alerta de seguridad por email (si está habilitado) ────────────────
        try {
            $settings = new \App\Models\SystemSetting();
            if ($settings->get('login_alert_email', '1') === '1') {
                \App\Helpers\Mailer::sendLoginAlert(
                    $user['email'],
                    $userName,
                    $ip,
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                    $userType
                );
            }
        } catch (\Throwable $e) {
            error_log('[AuthController] Error enviando alerta de login: ' . $e->getMessage());
        }

        Session::flash('success', 'Bienvenido, ' . htmlspecialchars($userName) . '.');

        // Forzar escritura de sesión a disco antes de redirigir
        // Previene pérdida de datos en redirecciones rápidas (fix 2FA double-login)
        session_write_close();

        $this->redirectByRole($primaryRole, $userType);
    }

    /**
     * Redirige al dashboard correspondiente según el rol del usuario.
     */
    private function redirectByRole(?string $primaryRole = null, ?string $userType = null): void
    {
        $role = $primaryRole ?? Session::userRole();
        $type = $userType    ?? Session::userType();

        if ($role === 'doctor' || $type === 'doctor') {
            View::redirect('/doctor/dashboard');
        } elseif ($role === 'patient' || $type === 'patient') {
            View::redirect('/patient/dashboard');
        } else {
            View::redirect('/admin/dashboard');
        }
    }
}
