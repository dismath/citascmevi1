<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\ImageOptimizer;
use App\Models\SystemSetting;

class AdminSettingsController
{
    private SystemSetting $settingsModel;

    public function __construct()
    {
        Auth::require();
        if (!Auth::hasRole('admin') && 
            !Auth::hasPermission('settings_read') && 
            !Auth::hasPermission('settings_general_read') && 
            !Auth::hasPermission('settings_email_read') && 
            !Auth::hasPermission('settings_security_read') && 
            !Auth::hasPermission('settings_catalogs_read') && 
            !Auth::hasPermission('settings_company_read')) {
            http_response_code(403);
            echo '<h1>403 - Acceso Denegado (Módulo de Configuración)</h1>';
            exit;
        }
        $this->settingsModel = new SystemSetting();
    }

    public function index()
    {
        $settings = $this->settingsModel->getAll();
        
        $db = \App\Helpers\Database::getInstance();
        $catalogTypes = $db->fetchAll("SELECT * FROM catalog_types ORDER BY name ASC");

        View::render('admin.settings.index', [
            'title' => 'Configuración del Sistema',
            'settings' => $settings,
            'catalogTypes' => $catalogTypes
        ], 'admin');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/settings');
        }

        $formSection = $_POST['form_section'] ?? '';

        // Validar permisos específicos de la pestaña
        $permMap = [
            'general'  => 'settings_general_update',
            'smtp'     => 'settings_email_update',
            'security' => 'settings_security_update',
            'empresa'  => 'settings_company_update',
        ];
        $requiredPerm = $permMap[$formSection] ?? 'settings_update';

        if (!Auth::hasRole('admin') && !Auth::hasPermission($requiredPerm) && !Auth::hasPermission('settings_update')) {
            Session::flash('error', 'No tiene permisos para modificar esta sección de configuración.');
            View::redirect('/admin/settings' . (!empty($formSection) ? '#' . $formSection : ''));
            return;
        }

        try {
            $allowedKeys = [
                'bank_name', 'bank_account_number', 'bank_account_owner',
                'bank_account_type', 'bank_email', 'bank_id_number', 'show_fee',
                'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_secure', 'smtp_from_email', 'smtp_from_name',
                'mail_send_mode',
                'validate_ecuadorian_id', 'max_reschedules', 'reschedule_hours_before',
                'company_ruc', 'company_name', 'company_phone', 'company_address', 'survey_url', 'company_email', 'company_website'
            ];

            foreach ($_POST as $key => $value) {
                if (in_array($key, $allowedKeys)) {
                    $this->settingsModel->set($key, trim($value));
                }
            }

            // show_fee and allow_weekends checkboxes only exist in the 'general' form
            if ($formSection === 'general') {
                $this->settingsModel->set('show_fee', isset($_POST['show_fee']) ? '1' : '0');
                $this->settingsModel->set('allow_weekends', isset($_POST['allow_weekends']) ? '1' : '0');
            }

            // mail_send_mode select only exists in the 'smtp' form
            if ($formSection === 'smtp') {
                $sendMode = in_array($_POST['mail_send_mode'] ?? '', ['sync', 'queue']) ? $_POST['mail_send_mode'] : 'sync';
                $this->settingsModel->set('mail_send_mode', $sendMode);
            }

            // validate_ecuadorian_id checkbox only exists in the 'security' form
            if ($formSection === 'security') {
                $this->settingsModel->set('validate_ecuadorian_id', isset($_POST['validate_ecuadorian_id']) ? '1' : '0');
                $this->settingsModel->set('two_factor_enabled', isset($_POST['two_factor_enabled']) ? '1' : '0');
                $this->settingsModel->set('two_factor_admin', isset($_POST['two_factor_admin']) ? '1' : '0');
                $this->settingsModel->set('two_factor_doctor', isset($_POST['two_factor_doctor']) ? '1' : '0');
                $this->settingsModel->set('two_factor_patient', isset($_POST['two_factor_patient']) ? '1' : '0');
                $this->settingsModel->set('login_alert_email', isset($_POST['login_alert_email']) ? '1' : '0');
            }

            // Empresa form handling: Logo upload and removal
            if ($formSection === 'empresa') {
                // Eliminar logo si el usuario seleccionó la casilla de eliminación
                if (!empty($_POST['remove_company_logo'])) {
                    $currentLogo = $this->settingsModel->get('company_logo');
                    if (!empty($currentLogo)) {
                        ImageOptimizer::deleteFile($currentLogo);
                        $this->settingsModel->set('company_logo', '');
                    }
                }

                // Subir y optimizar nuevo archivo de logo
                if (isset($_FILES['company_logo']) && is_array($_FILES['company_logo']) && !empty($_FILES['company_logo']['name']) && $_FILES['company_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newLogoPath = ImageOptimizer::processUpload(
                        $_FILES['company_logo'],
                        'company',
                        600,
                        300,
                        85
                    );

                    $currentLogo = $this->settingsModel->get('company_logo');
                    if (!empty($currentLogo) && $currentLogo !== $newLogoPath) {
                        ImageOptimizer::deleteFile($currentLogo);
                    }

                    $this->settingsModel->set('company_logo', $newLogoPath);
                }
            }

            Session::flash('success', 'Configuraciones actualizadas exitosamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar configuraciones: ' . $e->getMessage());
        }

        $redirectUrl = '/admin/settings';
        if (!empty($formSection)) {
            $redirectUrl .= '#' . $formSection;
        }
        View::redirect($redirectUrl);
    }

    public function storeCatalogType()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/settings');
        }

        try {
            $db = \App\Helpers\Database::getInstance();
            $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['code']));
            
            $db->execute(
                "INSERT INTO catalog_types (code, name, color) VALUES (?, ?, ?)",
                [$code, $_POST['name'], $_POST['color'] ?? 'primary']
            );
            Session::flash('success', 'Tipo de catálogo agregado.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al agregar tipo de catálogo. Es posible que el código ya exista.');
        }

        View::redirect('/admin/settings');
    }

    public function updateCatalogType(string $code)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/settings');
        }

        try {
            $db = \App\Helpers\Database::getInstance();
            $db->execute(
                "UPDATE catalog_types SET name = ?, color = ? WHERE code = ?",
                [$_POST['name'], $_POST['color'] ?? 'primary', $code]
            );
            Session::flash('success', 'Tipo de catálogo actualizado correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar tipo de catálogo.');
        }

        View::redirect('/admin/settings');
    }


    public function deleteCatalogType(string $code)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            View::redirect('/admin/settings');
        }

        try {
            $db = \App\Helpers\Database::getInstance();
            $db->execute("DELETE FROM catalog_types WHERE code = ?", [$code]);
            Session::flash('success', 'Tipo de catálogo eliminado.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar el tipo de catálogo porque puede estar en uso.');
        }

        View::redirect('/admin/settings');
    }

    /**
     * Envía un correo de prueba usando la configuración SMTP guardada.
     * Sirve para verificar que la configuración de mail.cmevi.com funciona correctamente.
     */
    public function testEmail(): void
    {
        Auth::require();
        if (!Auth::hasRole('admin') && !Auth::hasPermission('settings_email_update')) {
            Session::flash('error', 'No tienes permisos para enviar correos de prueba.');
            View::redirect('/admin/settings');
            return;
        }

        // Obtener el email del administrador logueado desde la BD
        $userId = Session::userId();
        $toEmail = '';
        if ($userId) {
            try {
                $db = \App\Helpers\Database::getInstance();
                $userData = $db->fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
                $toEmail = $userData['email'] ?? '';
            } catch (\Throwable $e) {
                error_log('[testEmail] Error obteniendo email: ' . $e->getMessage());
            }
        }

        if (empty($toEmail)) {
            Session::flash('error', 'No se pudo obtener el correo del administrador para el envío de prueba.');
            View::redirect('/admin/settings');
            return;
        }

        $smtpHost = $this->settingsModel->get('smtp_host', '');
        $smtpPort = $this->settingsModel->get('smtp_port', '587');
        $smtpUser = $this->settingsModel->get('smtp_user', '');

        if (empty($smtpHost) || empty($smtpUser)) {
            Session::flash('error', 'Configure primero el Host SMTP y el Usuario antes de enviar el correo de prueba.');
            View::redirect('/admin/settings');
            return;
        }

        $subject = '✅ Prueba de Correo — Portal Cmevi';
        $body = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f0f2f5;margin:0;padding:20px;">'
              . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.08);">'
              . '<div style="background:linear-gradient(135deg,#1a73e8,#0d47a1);padding:24px 32px;text-align:center;">'
              . '<h1 style="color:#fff;margin:0;font-size:22px;">✅ Correo de Prueba</h1>'
              . '<p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:13px;">Configuración SMTP verificada correctamente</p>'
              . '</div>'
              . '<div style="padding:24px 32px;">'
              . '<p style="color:#333;font-size:14px;line-height:1.6;">Si recibiste este mensaje, la configuración SMTP del sistema está funcionando correctamente.</p>'
              . '<table style="width:100%;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;padding:16px;font-size:13px;border-collapse:separate;border-spacing:0;">'
              . '<tr><td style="padding:6px 12px 6px 0;color:#64748b;">Servidor:</td><td style="color:#1e293b;font-weight:600;">' . htmlspecialchars($smtpHost) . '</td></tr>'
              . '<tr><td style="padding:6px 12px 6px 0;color:#64748b;">Puerto:</td><td style="color:#1e293b;font-weight:600;">' . htmlspecialchars($smtpPort) . '</td></tr>'
              . '<tr><td style="padding:6px 12px 6px 0;color:#64748b;">Usuario:</td><td style="color:#1e293b;font-weight:600;">' . htmlspecialchars($smtpUser) . '</td></tr>'
              . '<tr><td style="padding:6px 12px 6px 0;color:#64748b;">Enviado a:</td><td style="color:#1e293b;font-weight:600;">' . htmlspecialchars($toEmail) . '</td></tr>'
              . '</table>'
              . '<p style="color:#64748b;font-size:12px;margin-top:16px;text-align:center;">Este es un correo automático de verificación del sistema.</p>'
              . '</div></div></body></html>';

        $result = \App\Helpers\Mailer::send($toEmail, $subject, $body);

        if ($result) {
            Session::flash('success', "✅ Correo de prueba enviado correctamente a {$toEmail}. Revisa tu bandeja de entrada.");
        } else {
            Session::flash('error', '❌ No se pudo enviar el correo de prueba. Revisa la configuración SMTP y el error_log de PHP (C:\xampp\php\logs\php_error_log).');
        }

        View::redirect('/admin/settings');
    }
}
