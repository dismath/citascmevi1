<?php
/**
 * Cron Job: Enviar recordatorios de citas por correo electrónico
 *
 * Este script debe ejecutarse periódicamente (ej. cada hora) vía cron o Task Scheduler.
 *
 * Ejemplo crontab (Linux):
 *   0 * * * * /usr/bin/php /var/www/html/scripts/cron_reminders.php >> /var/www/html/storage/logs/cron_reminders.log 2>&1
 *
 * Ejemplo Task Scheduler (Windows/XAMPP):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\scripts\cron_reminders.php
 *
 * Lógica:
 *   1. Buscar citas con appointment_date dentro de las próximas 24 horas.
 *   2. Que estén en estado 'confirmed' y que reminder_sent = 0.
 *   3. Enviar correo de recordatorio al paciente y al doctor.
 *   4. Marcar reminder_sent = 1 en la cita.
 */

// Definir paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Cargar Env
require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(ROOT_PATH . '/.env');

// Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Configurar timezone
$appConfig = require CONFIG_PATH . '/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'America/Guayaquil');

// Inicializar DB
$dbConfig = require CONFIG_PATH . '/database.php';
\App\Helpers\Database::init($dbConfig);
$db = \App\Helpers\Database::getInstance();

// Log helper
function cronLog(string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
}

cronLog("=== Inicio del proceso de recordatorios ===");

// Buscar citas en las próximas 2 horas que estén confirmadas y sin recordatorio enviado
$appointments = $db->fetchAll(
    "SELECT a.*, 
            p.name as patient_name, p.id_number as patient_id_number, p.phone as patient_phone,
            p.id as p_id,
            d.name as doctor_name, d.phone as doctor_phone,
            d.id as d_id,
            s.name as specialty_name,
            u_patient.email as patient_email,
            u_doctor.email as doctor_email
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     LEFT JOIN users u_patient ON p.user_id = u_patient.id
     JOIN doctors d ON a.doctor_id = d.id
     LEFT JOIN users u_doctor ON d.user_id = u_doctor.id
     JOIN specialties s ON d.specialty_id = s.id
     WHERE a.status = 'confirmed'
       AND a.reminder_sent = 0
       AND a.appointment_date >= NOW()
       AND a.appointment_date <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
     ORDER BY a.appointment_date ASC"
);

$totalFound = count($appointments);
cronLog("Citas encontradas para recordatorio (próximas 2 horas): {$totalFound}");

if ($totalFound === 0) {
    cronLog("No hay citas pendientes de recordatorio. Finalizando.");
    exit(0);
}

$sentCount = 0;
$errorCount = 0;

foreach ($appointments as $appt) {
    $citaId = str_pad($appt['id'], 5, '0', STR_PAD_LEFT);
    $date = date('d/m/Y', strtotime($appt['appointment_date']));
    $time = date('H:i', strtotime($appt['appointment_date']));

    cronLog("Procesando cita #{$citaId} - Paciente: {$appt['patient_name']} - Fecha: {$date} {$time}");

    $patientSent = false;
    $doctorSent = false;

    // --- Recordatorio al PACIENTE ---
    if (!empty($appt['patient_email'])) {
        $subject = "🔔 Recordatorio: Su cita médica es en menos de 2 horas — Cita #{$citaId}";
        $htmlBody = buildReminderEmailPatient($appt);
        try {
            $patientSent = \App\Helpers\Mailer::send($appt['patient_email'], $subject, $htmlBody);
            if ($patientSent) {
                cronLog("  ✅ Recordatorio enviado al paciente: {$appt['patient_email']}");
            } else {
                cronLog("  ⚠️ No se pudo enviar recordatorio al paciente: {$appt['patient_email']}");
            }
        } catch (\Exception $e) {
            cronLog("  ❌ Error enviando al paciente: " . $e->getMessage());
        }
    } else {
        cronLog("  ⚠️ Paciente sin email registrado");
    }

    // --- Recordatorio al DOCTOR ---
    if (!empty($appt['doctor_email'])) {
        $subject = "🔔 Recordatorio: Cita programada en menos de 2 horas — #{$citaId}";
        $htmlBody = buildReminderEmailDoctor($appt);
        try {
            $doctorSent = \App\Helpers\Mailer::send($appt['doctor_email'], $subject, $htmlBody);
            if ($doctorSent) {
                cronLog("  ✅ Recordatorio enviado al doctor: {$appt['doctor_email']}");
            } else {
                cronLog("  ⚠️ No se pudo enviar recordatorio al doctor: {$appt['doctor_email']}");
            }
        } catch (\Exception $e) {
            cronLog("  ❌ Error enviando al doctor: " . $e->getMessage());
        }
    } else {
        cronLog("  ⚠️ Doctor sin email registrado");
    }

    // --- Notificaciones Push (FCM) a la app móvil ---
    $fcmTitle = "🔔 Recordatorio de Cita";

    // Push al paciente
    $patTokens = $db->fetchAll(
        "SELECT DISTINCT fcm_token FROM api_tokens 
         WHERE user_id = (SELECT user_id FROM patients WHERE id = ?) AND fcm_token IS NOT NULL",
        [$appt['p_id']]
    );
    $patFcmBody = "Su cita con el Dr. {$appt['doctor_name']} es hoy a las {$time}. Por favor, llegue 15 minutos antes.";
    foreach ($patTokens as $t) {
        try {
            \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $patFcmBody, ['type' => 'reminder', 'appointment_id' => (string)$appt['id']]);
            cronLog("  📱 Push enviado al paciente (FCM)");
        } catch (\Exception $e) {
            cronLog("  ❌ Error Push paciente: " . $e->getMessage());
        }
    }

    // Push al doctor
    $docTokens = $db->fetchAll(
        "SELECT DISTINCT fcm_token FROM api_tokens 
         WHERE user_id = (SELECT user_id FROM doctors WHERE id = ?) AND fcm_token IS NOT NULL",
        [$appt['d_id']]
    );
    $docFcmBody = "Tiene una cita con el paciente {$appt['patient_name']} hoy a las {$time}.";
    foreach ($docTokens as $t) {
        try {
            \App\Helpers\FirebaseService::sendPushNotification($t['fcm_token'], $fcmTitle, $docFcmBody, ['type' => 'reminder', 'appointment_id' => (string)$appt['id']]);
            cronLog("  📱 Push enviado al doctor (FCM)");
        } catch (\Exception $e) {
            cronLog("  ❌ Error Push doctor: " . $e->getMessage());
        }
    }

    // Marcar como enviado si al menos uno fue exitoso
    if ($patientSent || $doctorSent || !empty($patTokens) || !empty($docTokens)) {
        $db->execute("UPDATE appointments SET reminder_sent = 1 WHERE id = ?", [$appt['id']]);
        $sentCount++;
    } else {
        $errorCount++;
    }
}

cronLog("=== Proceso finalizado ===");
cronLog("Recordatorios enviados: {$sentCount} | Errores: {$errorCount}");

// =====================================================================
// Funciones auxiliares para generar los emails de recordatorio
// =====================================================================

function buildReminderEmailPatient(array $appt): string
{
    $patientName  = htmlspecialchars($appt['patient_name'] ?? '');
    $doctorName   = htmlspecialchars($appt['doctor_name'] ?? '');
    $specialty    = htmlspecialchars($appt['specialty_name'] ?? '');
    $date         = date('d/m/Y', strtotime($appt['appointment_date']));
    $time         = date('H:i', strtotime($appt['appointment_date']));
    $citaId       = str_pad($appt['id'], 5, '0', STR_PAD_LEFT);

    $settings = new \App\Models\SystemSetting();
    $clinicName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
    $companyAddress = htmlspecialchars($settings->get('company_address', ''));
    $companyPhone = htmlspecialchars($settings->get('company_phone', ''));

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">🔔 Recordatorio de Cita</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Su cita médica es pronto</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>{$patientName}</strong>, le recordamos que tiene una cita médica programada:
                </p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #faf5ff; border-radius: 10px; border: 1px solid #e9d5ff; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #7c3aed; font-size: 16px; border-bottom: 2px solid #e9d5ff; padding-bottom: 10px;">📋 Cita #{$citaId}</h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%;">🩺 Médico:</td><td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">Dr(a). {$doctorName}</td></tr>
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px;">🏥 Especialidad:</td><td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$specialty}</td></tr>
                                <tr><td colspan="2" style="padding: 10px 0 5px;"><hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;"></td></tr>
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px;">📅 Fecha:</td><td style="padding: 8px 0; color: #7c3aed; font-size: 16px; font-weight: 700;">{$date}</td></tr>
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px;">🕐 Hora:</td><td style="padding: 8px 0; color: #7c3aed; font-size: 16px; font-weight: 700;">{$time}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fef9e7; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                                <strong>⚠️ Recuerde:</strong> Llegue 15 minutos antes con su cédula de identidad. Si no puede asistir, comuníquese con nosotros para reagendar.
                            </p>
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 5px 0; color: #64748b; font-size: 13px;"><strong style="color: #475569;">📍 Dirección:</strong> {$companyAddress}</p>
                            <p style="margin: 5px 0; color: #64748b; font-size: 13px;"><strong style="color: #475569;">📞 Teléfono:</strong> {$companyPhone}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background: #1e293b; padding: 20px 40px; text-align: center;">
                <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin: 0; line-height: 1.6;">
                    Recordatorio automático de <strong style="color: #fff;">{$clinicName}</strong>.<br>Por favor, no responda a este mensaje.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

function buildReminderEmailDoctor(array $appt): string
{
    $doctorName   = htmlspecialchars($appt['doctor_name'] ?? '');
    $patientName  = htmlspecialchars($appt['patient_name'] ?? '');
    $patientId    = htmlspecialchars($appt['patient_id_number'] ?? '');
    $patientPhone = htmlspecialchars($appt['patient_phone'] ?? '');
    $specialty    = htmlspecialchars($appt['specialty_name'] ?? '');
    $date         = date('d/m/Y', strtotime($appt['appointment_date']));
    $time         = date('H:i', strtotime($appt['appointment_date']));
    $citaId       = str_pad($appt['id'], 5, '0', STR_PAD_LEFT);

    $settings = new \App\Models\SystemSetting();
    $clinicName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #0f766e, #0d9488); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">🔔 Recordatorio de Agenda</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Tiene una cita programada próximamente</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>Dr(a). {$doctorName}</strong>, le recordamos que tiene la siguiente cita en su agenda:
                </p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f0fdf9; border-radius: 10px; border: 1px solid #ccfbf1; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #0f766e; font-size: 16px; border-bottom: 2px solid #ccfbf1; padding-bottom: 10px;">📅 Cita #{$citaId} — {$date} a las {$time}</h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%;">👤 Paciente:</td><td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td></tr>
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px;">🪪 Cédula:</td><td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td></tr>
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px;">📞 Teléfono:</td><td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td></tr>
                                <tr><td style="padding: 8px 0; color: #64748b; font-size: 13px;">🩺 Especialidad:</td><td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td></tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="background: #1e293b; padding: 20px 40px; text-align: center;">
                <p style="color: rgba(255,255,255,0.7); font-size: 12px; margin: 0; line-height: 1.6;">
                    Recordatorio automático de <strong style="color: #fff;">{$clinicName}</strong>.<br>Por favor, no responda a este mensaje.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
