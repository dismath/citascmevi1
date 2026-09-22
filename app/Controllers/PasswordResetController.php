<?php
namespace App\Controllers;

use App\Models\User;
use App\Models\PasswordReset;
use App\Models\SecurityAudit;
use App\Helpers\View;
use App\Helpers\Session;
use App\Helpers\RateLimiter;
use App\Helpers\Mailer;
use App\Helpers\Env;

class PasswordResetController
{
    private PasswordReset $resetModel;
    private User $userModel;

    public function __construct()
    {
        $this->resetModel = new PasswordReset();
        $this->userModel = new User();
    }

    /**
     * Muestra el formulario para solicitar el enlace de recuperación (olvidé mi contraseña).
     */
    public function showForgotForm(): void
    {
        if (Session::isLoggedIn()) {
            View::redirect('/admin');
            return;
        }

        View::render('auth.forgot_password', [
            'title' => 'Recuperar Contraseña'
        ], 'main');
    }

    /**
     * Procesa la solicitud de recuperación y envía el enlace por correo electrónico.
     */
    public function sendResetLink(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/forgot-password');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 1. Detección de bots vía honeypot
        if (!empty($_POST['website_hp'])) {
            SecurityAudit::log(
                null,
                SecurityAudit::EVENTO_BOT_DETECTADO,
                SecurityAudit::RESULTADO_BLOQUEADO,
                ['motivo' => 'Honeypot en forgot-password', 'ip' => $ip]
            );
            usleep(1000000);
            Session::flash('success', 'Si el correo electrónico ingresado está registrado, recibirá un enlace para restablecer su contraseña en los próximos minutos.');
            View::redirect('/forgot-password');
            return;
        }

        // 2. Rate limit estricto por IP: 5 intentos cada 15 minutos
        RateLimiter::check('forgot_password', 5, 900, $ip);

        // 3. Validación de token CSRF
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido. Por favor intente nuevamente.');
            View::redirect('/forgot-password');
            return;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Por favor ingrese un correo electrónico válido.');
            View::redirect('/forgot-password');
            return;
        }

        // 4. Búsqueda unificada en la base de datos
        $user = $this->userModel->findUnifiedByLogin($email);

        if ($user) {
            $userType = 'admin';
            if (!empty($user['roles'])) {
                $roles = explode(',', $user['roles']);
                if (in_array('doctor', $roles, true)) {
                    $userType = 'doctor';
                } elseif (in_array('patient', $roles, true)) {
                    $userType = 'patient';
                }
            }

            $token = $this->resetModel->createTokenForType((int)$user['id'], $userType, 24);

            // Construir link de reseteo compatible con el host activo
            $appUrl = Env::getAppUrl();
            $resetLink = $appUrl . '/reset-password?token=' . urlencode($token);
            $userName = $user['nombre_completo'] ?? $user['name'] ?? $user['nombre_usuario'] ?? 'Usuario';

            try {
                Mailer::sendPasswordResetEmail($user['email'], $userName, $resetLink);
            } catch (\Throwable $e) {
                error_log('[PasswordResetController] Error enviando correo: ' . $e->getMessage());
            }

            SecurityAudit::log(
                (int)$user['id'],
                SecurityAudit::EVENTO_PASSWORD_RESET_SOLICITADO,
                SecurityAudit::RESULTADO_EXITOSO,
                ['email' => $email]
            );
        } else {
            // Protección contra enumeración de cuentas: tiempo de respuesta constante
            usleep(500000);

            SecurityAudit::log(
                null,
                SecurityAudit::EVENTO_PASSWORD_RESET_SOLICITADO,
                SecurityAudit::RESULTADO_FALLIDO,
                ['email' => $email, 'motivo' => 'usuario_no_encontrado']
            );
        }

        Session::flash(
            'success',
            'Si el correo electrónico ingresado está registrado en nuestro sistema, recibirá un enlace para restablecer su contraseña en los próximos minutos. Por favor revise también su carpeta de spam o correo no deseado.'
        );
        View::redirect('/forgot-password');
    }

    /**
     * Muestra el formulario para ingresar la nueva contraseña usando el token.
     * Si el enlace cancela antes de guardar, sigue permitiendo la opción de crear la nueva contraseña.
     * Solo redirige a recuperar contraseña si caducó el tiempo establecido (24 horas).
     */
    public function showForm(): void
    {
        $token = trim($_GET['token'] ?? '');

        if (empty($token)) {
            Session::flash('error', 'El enlace proporcionado no es válido o ha sido eliminado.');
            View::redirect('/login');
            return;
        }

        $inspection = $this->resetModel->inspectToken($token);

        switch ($inspection['status']) {
            case PasswordReset::STATUS_EXPIRED:
                // SOLO si caducó el tiempo establecido, se redirecciona a recuperar contraseña:
                Session::flash('error', 'El enlace para establecer su contraseña ha caducado (ha superado el límite de 24 horas). Ingrese su correo para solicitar uno nuevo.');
                View::redirect('/forgot-password');
                return;

            case PasswordReset::STATUS_USED:
                // Si ya fue utilizado para establecer o cambiar la contraseña:
                Session::flash('info', 'Este enlace ya fue utilizado para establecer su contraseña. Por favor inicie sesión con su nueva clave.');
                View::redirect('/login');
                return;

            case PasswordReset::STATUS_INVALID:
                Session::flash('error', 'El enlace proporcionado no es válido o ha sido modificado.');
                View::redirect('/login');
                return;

            case PasswordReset::STATUS_VALID:
            default:
                // Token válido y activo. Si el usuario cancela la pantalla,
                // el token NO se marca como usado y seguirá disponible hasta expirar.
                break;
        }

        $resetData = $inspection['data'];
        $user = $this->userModel->findById((int)$resetData['user_id']);
        $userEmail = $user['email'] ?? '';
        $userName = $user['nombre_usuario'] ?? $user['name'] ?? '';

        View::render('auth.reset_password', [
            'title'     => 'Establecer Contraseña',
            'token'     => $token,
            'userEmail' => $userEmail,
            'userName'  => $userName,
        ], 'main');
    }

    /**
     * Procesa el cambio efectivo de contraseña.
     */
    public function process(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/login');
            return;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 1. Rate limiting
        RateLimiter::check('reset_password_process', 5, 900, $ip);

        $token           = trim($_POST['token'] ?? '');
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // 2. Validación de CSRF
        if (!Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Token de seguridad inválido. Por favor intente de nuevo.');
            View::redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        if (empty($token)) {
            Session::flash('error', 'Token de recuperación inválido o faltante.');
            View::redirect('/login');
            return;
        }

        $inspection = $this->resetModel->inspectToken($token);

        if ($inspection['status'] === PasswordReset::STATUS_EXPIRED) {
            // SOLO si caducó el tiempo establecido, se redirecciona a recuperar contraseña
            Session::flash('error', 'El enlace para establecer su contraseña ha caducado (ha superado el tiempo límite). Por favor ingrese su correo para solicitar uno nuevo.');
            View::redirect('/forgot-password');
            return;
        }

        if ($inspection['status'] === PasswordReset::STATUS_USED) {
            Session::flash('info', 'Este enlace ya fue utilizado para establecer su contraseña. Inicie sesión con su nueva clave.');
            View::redirect('/login');
            return;
        }

        if ($inspection['status'] !== PasswordReset::STATUS_VALID) {
            Session::flash('error', 'El enlace proporcionado no es válido o ha expirado.');
            View::redirect('/login');
            return;
        }

        $resetData = $inspection['data'];

        if (strlen($password) < 8) {
            Session::flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
            View::redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            Session::flash('error', 'La nueva contraseña debe contener al menos una letra y un número.');
            View::redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        if ($password !== $passwordConfirm) {
            Session::flash('error', 'Las contraseñas ingresadas no coinciden.');
            View::redirect('/reset-password?token=' . urlencode($token));
            return;
        }

        $userId = (int)$resetData['user_id'];

        // 3. Actualizar contraseña y reiniciar intentos fallidos / bloqueo
        $this->userModel->update($userId, [
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])
        ]);
        $this->userModel->resetFailedAttempts($userId);

        // 4. Marcar token como utilizado (únicamente aquí se invalida tras cambio exitoso)
        $this->resetModel->markUsed($token);

        // 5. Auditoría
        SecurityAudit::log(
            $userId,
            SecurityAudit::EVENTO_PASSWORD_CAMBIADO,
            SecurityAudit::RESULTADO_EXITOSO,
            ['metodo' => 'recovery_token']
        );

        Session::flash('success', '¡Su contraseña ha sido establecida exitosamente! Ya puede iniciar sesión con su nueva clave.');
        View::redirect('/login');
    }
}
