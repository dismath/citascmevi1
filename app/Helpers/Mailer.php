<?php
namespace App\Helpers;

use App\Models\SystemSetting;

class Mailer
{
    /**
     * Envía un correo HTML usando la configuración SMTP del sistema.
     * Usa la función nativa mail() de PHP como fallback si SMTP no está configurado.
     * @param array $attachments Array de adjuntos [['name'=>'file.pdf', 'data'=>'raw_data', 'type'=>'application/pdf']]
     */
    public static function send(string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        $settings = new SystemSetting();
        $smtpHost = $settings->get('smtp_host', '');
        $smtpPort = (int) $settings->get('smtp_port', '587');
        $smtpUser = $settings->get('smtp_user', '');
        $smtpPass = $settings->get('smtp_pass', '');
        $fromEmail = $settings->get('smtp_from_email');
        if (empty($fromEmail)) {
            $fromEmail = !empty($smtpUser) ? $smtpUser : 'no-reply@medsystem.com';
        }
        $fromName = $settings->get('smtp_from_name');
        if (empty($fromName)) {
            $fromName = 'Portal Cmevi Pro';
        }

        $smtpSecure = $settings->get('smtp_secure', '');

        // Auto-detect smtp_secure basado en puerto si está vacío
        if (empty($smtpSecure)) {
            if ($smtpPort === 465) {
                $smtpSecure = 'ssl';
            } else {
                $smtpSecure = 'tls'; // Puerto 587 y otros usan TLS por defecto
            }
        }

        // Si SMTP está configurado, intentar enviar por SMTP
        if (!empty($smtpHost) && !empty($smtpUser) && !empty($smtpPass)) {
            return self::sendSmtp($smtpHost, $smtpPort, $smtpUser, $smtpPass, $smtpSecure, $fromEmail, $fromName, $to, $subject, $htmlBody, $attachments);
        }

        // Fallback: usar mail() de PHP
        return self::sendNativeMail($fromEmail, $fromName, $to, $subject, $htmlBody, $attachments);
    }

    /**
     * Envía o encola un correo según el parámetro 'mail_send_mode' del sistema.
     *
     * - 'sync'  → Envío inmediato y bloqueante (send()). La página espera la respuesta SMTP.
     *             Úsalo cuando el servidor SMTP es rápido y confiable (recomendado para producción estable).
     * - 'queue' → Encola en background_jobs y dispara el worker. La página responde al instante.
     *             Úsalo si el SMTP es lento o poco confiable.
     *
     * El modo se configura desde: Panel Admin → Configuración → Servidor de Correo → Modo de Envío.
     *
     * @param string $to          Destinatario
     * @param string $subject     Asunto
     * @param string $htmlBody    Cuerpo HTML
     * @param int    $delaySeconds Segundos de retraso si se usa modo queue (0 = inmediato)
     */
    public static function queue(string $to, string $subject, string $htmlBody, int $delaySeconds = 0): bool
    {
        // Leer el modo de envío desde la configuración del sistema
        try {
            $settings = new SystemSetting();
            $mode = $settings->get('mail_send_mode', 'sync');
        } catch (\Throwable $e) {
            $mode = 'sync'; // Ante cualquier fallo, usar envío directo
        }

        // MODO SÍNCRONO: envío inmediato (predeterminado)
        if ($mode !== 'queue') {
            return self::send($to, $subject, $htmlBody);
        }

        // MODO ASÍNCRONO: encolar en background_jobs
        try {
            $db = Database::getInstance();
            $payload = json_encode([
                'type'    => 'send_email',
                'to'      => $to,
                'subject' => $subject,
                'body'    => $htmlBody,
            ]);

            $availableAt = time() + $delaySeconds;

            $db->execute(
                "INSERT INTO background_jobs (queue, payload, attempts, available_at, created_at)
                 VALUES ('default', ?, 0, ?, NOW())",
                [$payload, $availableAt]
            );

            // Disparar el worker en background para procesar inmediatamente
            self::dispatchWorker();

            return true;
        } catch (\Throwable $e) {
            error_log('[Mailer::queue] Error al encolar email para ' . $to . ': ' . $e->getMessage());
            // Fallback: envío directo si falla el encolamiento
            return self::send($to, $subject, $htmlBody);
        }
    }


    /**
     * Lanza el worker.php en background sin bloquear la respuesta HTTP.
     * Usa proc_open con descriptores nulos en Windows para desconectar completamente
     * el proceso hijo del proceso Apache padre, evitando cualquier bloqueo.
     */
    private static function dispatchWorker(): void
    {
        $workerPath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'worker.php';

        if (!file_exists($workerPath)) {
            return;
        }

        $phpBin = PHP_BINARY;

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: proc_open con pipes nulos — el proceso hijo queda completamente
            // desconectado de Apache y no bloquea la respuesta HTTP.
            $descriptors = [
                0 => ['pipe', 'r'],   // stdin: pipe vacío
                1 => ['file', 'NUL', 'w'], // stdout → NUL (descartado)
                2 => ['file', 'NUL', 'w'], // stderr → NUL (descartado)
            ];
            $proc = @proc_open(
                "\"{$phpBin}\" \"{$workerPath}\" default 10",
                $descriptors,
                $pipes
            );
            if (is_resource($proc)) {
                fclose($pipes[0]);
                proc_close($proc); // Inmediato — el hijo sigue en background
            }
        } else {
            // Linux/Mac: nohup para que el proceso no muera con el padre
            $logDir = dirname(dirname(dirname(__FILE__))) . '/storage/logs';
            $logFile = is_dir($logDir) ? $logDir . '/worker.log' : '/dev/null';
            $cmd = "nohup \"{$phpBin}\" \"{$workerPath}\" default 10 >> \"{$logFile}\" 2>&1 &";
            exec($cmd);
        }
    }


    /**
     * Envía correo usando mail() nativo de PHP con soporte para adjuntos.
     */
    private static function sendNativeMail(string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        $boundary = md5(time());
        
        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PortalCmeviPro/1.0\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if (!empty($attachments)) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $message  = "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";

            foreach ($attachments as $attachment) {
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: " . $attachment['type'] . "; name=\"" . $attachment['name'] . "\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"" . $attachment['name'] . "\"\r\n\r\n";
                $message .= chunk_split(base64_encode($attachment['data'])) . "\r\n";
            }
            $message .= "--{$boundary}--";
        } else {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $message  = chunk_split(base64_encode($htmlBody));
        }

        return @mail($to, $subject, $message, $headers);
    }

    /**
     * Envía correo usando socket SMTP directo.
     * Compatible con Gmail, Outlook, cPanel, Plesk, Dovecot y servidores privados.
     * Soporta certificados autofirmados y TLS flexible.
     */
    private static function sendSmtp(string $host, int $port, string $user, string $pass, string $secure, string $fromEmail, string $fromName, string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        // Hostname local del servidor que envía (para EHLO)
        $ehloHost = gethostname() ?: 'localhost';

        // Contexto SSL: permite certificados autofirmados de servidores privados
        $sslContext = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLS_CLIENT
                                     | STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
                                     | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                                     | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                                     | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0),
            ]
        ]);

        try {
            $prefix = ($secure === 'ssl') ? 'ssl://' : '';

            // Conectar con contexto SSL para evitar rechazo de cert. autofirmado
            $socket = @stream_socket_client(
                $prefix . $host . ':' . $port,
                $errno,
                $errstr,
                20,
                STREAM_CLIENT_CONNECT,
                $sslContext
            );

            if (!$socket) {
                error_log("SMTP Error: No se pudo conectar a {$host}:{$port} - {$errstr} ({$errno})");
                return self::sendNativeMail($fromEmail, $fromName, $to, $subject, $htmlBody);
            }

            stream_set_timeout($socket, 20);

            // Leer saludo del servidor
            $greeting = self::smtpRead($socket);
            error_log("SMTP [{$host}:{$port}] Saludo: " . trim($greeting));

            if (strpos($greeting, '220') === false) {
                error_log("SMTP Error: Saludo inesperado: " . $greeting);
                fclose($socket);
                return self::sendNativeMail($fromEmail, $fromName, $to, $subject, $htmlBody);
            }

            // EHLO con hostname real
            self::smtpWrite($socket, "EHLO {$ehloHost}\r\n");
            $ehloResp = self::smtpRead($socket);
            error_log("SMTP [{$host}] EHLO: " . trim(explode("\n", $ehloResp)[0]));

            // STARTTLS si el modo es 'tls'
            if ($secure === 'tls') {
                self::smtpWrite($socket, "STARTTLS\r\n");
                $tlsResp = self::smtpRead($socket);
                if (strpos($tlsResp, '220') === false) {
                    error_log("SMTP Warning: STARTTLS rechazado ({$tlsResp}), continuando sin TLS...");
                } else {
                    // Habilitar TLS flexible (compatible con TLS 1.0 a 1.3)
                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT
                                  | STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
                                  | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                                  | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                                  | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);

                    $tlsEnabled = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                    if (!$tlsEnabled) {
                        // Si TLS falla, intentar solo TLSv1.2 como fallback
                        $tlsEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                        if (!$tlsEnabled) {
                            error_log("SMTP Warning: No se pudo habilitar TLS, continuando sin cifrado...");
                        }
                    }
                    // Re-enviar EHLO después de STARTTLS
                    self::smtpWrite($socket, "EHLO {$ehloHost}\r\n");
                    self::smtpRead($socket);
                }
            }

            // AUTH LOGIN
            self::smtpWrite($socket, "AUTH LOGIN\r\n");
            $authPrompt = self::smtpRead($socket);
            // 334 = servidor pide usuario
            if (strpos($authPrompt, '334') === false) {
                // Intentar AUTH PLAIN como alternativa
                self::smtpWrite($socket, "AUTH PLAIN " . base64_encode("\0{$user}\0{$pass}") . "\r\n");
                $authResponse = self::smtpRead($socket);
            } else {
                self::smtpWrite($socket, base64_encode($user) . "\r\n");
                self::smtpRead($socket);
                self::smtpWrite($socket, base64_encode($pass) . "\r\n");
                $authResponse = self::smtpRead($socket);
            }

            if (strpos($authResponse, '235') === false) {
                error_log("SMTP Auth Error [{$host}]: " . trim($authResponse));
                fclose($socket);
                return self::sendNativeMail($fromEmail, $fromName, $to, $subject, $htmlBody, $attachments);
            }
            error_log("SMTP Auth OK [{$host}]");

            // MAIL FROM
            self::smtpWrite($socket, "MAIL FROM:<{$fromEmail}>\r\n");
            $mailFromResp = self::smtpRead($socket);
            if (strpos($mailFromResp, '250') === false) {
                error_log("SMTP MAIL FROM error: " . trim($mailFromResp));
                fclose($socket);
                return false;
            }

            // RCPT TO
            self::smtpWrite($socket, "RCPT TO:<{$to}>\r\n");
            $rcptResp = self::smtpRead($socket);
            if (strpos($rcptResp, '250') === false && strpos($rcptResp, '251') === false) {
                error_log("SMTP RCPT TO error [{$to}]: " . trim($rcptResp));
                fclose($socket);
                return false;
            }

            // DATA
            self::smtpWrite($socket, "DATA\r\n");
            $dataResp = self::smtpRead($socket);
            if (strpos($dataResp, '354') === false) {
                error_log("SMTP DATA error: " . trim($dataResp));
                fclose($socket);
                return false;
            }

            // Construir mensaje
            $boundary = 'bound_' . md5(uniqid('', true));
            $date     = date('r');
            $message  = "Date: {$date}\r\n";
            $message .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "X-Mailer: PortalCmeviPro/2.0\r\n";

            if (!empty($attachments)) {
                $message .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
                foreach ($attachments as $attachment) {
                    $message .= "--{$boundary}\r\n";
                    $message .= "Content-Type: " . $attachment['type'] . "; name=\"" . $attachment['name'] . "\"\r\n";
                    $message .= "Content-Transfer-Encoding: base64\r\n";
                    $message .= "Content-Disposition: attachment; filename=\"" . $attachment['name'] . "\"\r\n\r\n";
                    $message .= chunk_split(base64_encode($attachment['data'])) . "\r\n";
                }
                $message .= "--{$boundary}--\r\n";
            } else {
                $message .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $message .= chunk_split(base64_encode($htmlBody)) . "\r\n";
            }
            // Finalizar DATA
            $message .= "\r\n.\r\n";

            self::smtpWrite($socket, $message);
            $sendResp = self::smtpRead($socket);

            if (strpos($sendResp, '250') === false) {
                error_log("SMTP Send Error: " . trim($sendResp));
                fclose($socket);
                return false;
            }

            error_log("SMTP Correo enviado correctamente a {$to} via {$host}:{$port}");

            // QUIT
            self::smtpWrite($socket, "QUIT\r\n");
            fclose($socket);

            return true;

        } catch (\Exception $e) {
            error_log("SMTP Exception [{$host}:{$port}]: " . $e->getMessage());
            return self::sendNativeMail($fromEmail, $fromName, $to, $subject, $htmlBody, $attachments);
        }
    }

    private static function smtpWrite($socket, string $data): void
    {
        fwrite($socket, $data);
    }

    private static function smtpRead($socket): string
    {
        $response = '';
        stream_set_timeout($socket, 15);
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // El último comando termina con espacio en posición 3 (ej: "250 OK")
            if (strlen($line) >= 4 && $line[3] === ' ') break;
        }
        return $response;
    }

    /**
     * Obtiene los datos de la clínica configurados en el sistema.
     *
     * @return array [name, address, email, phone, website]
     */
    public static function getClinicInfo(): array
    {
        $settings = new SystemSetting();

        $name = trim((string)$settings->get('company_name', ''));
        if (empty($name)) {
            $name = trim((string)$settings->get('smtp_from_name', 'Portal Cmevi Pro'));
        }
        if (empty($name)) {
            $name = 'Portal Cmevi Pro';
        }

        $address = trim((string)$settings->get('company_address', ''));

        $email = trim((string)$settings->get('company_email', ''));
        if (empty($email)) {
            $email = trim((string)$settings->get('smtp_from_email', ''));
        }
        if (empty($email)) {
            $email = trim((string)$settings->get('smtp_user', ''));
        }

        $phone = trim((string)$settings->get('company_phone', ''));

        $website = trim((string)$settings->get('company_website', ''));

        return [
            'name'    => $name,
            'address' => $address,
            'email'   => $email,
            'phone'   => $phone,
            'website' => $website,
        ];
    }

    /**
     * Genera el HTML del pie de página para los correos con todos los datos de la clínica:
     * Nombre, Dirección, Correo, Teléfono y Página Web.
     */
    public static function buildEmailFooter(): string
    {
        $info = self::getClinicInfo();
        $clinicName = htmlspecialchars($info['name']);
        $address = htmlspecialchars($info['address']);
        $email = htmlspecialchars($info['email']);
        $phone = htmlspecialchars($info['phone']);
        $website = htmlspecialchars($info['website']);

        $cleanPhone = preg_replace('/[^0-9+]/', '', $info['phone']);

        $webUrl = $info['website'];
        if (!empty($webUrl) && !preg_match('#^https?://#i', $webUrl)) {
            $webUrl = 'https://' . $webUrl;
        }
        $webUrlSafe = htmlspecialchars($webUrl);
        $webDisplay = htmlspecialchars(preg_replace('#^https?://#i', '', rtrim($info['website'], '/')));

        // Bloque de Dirección
        $addressHtml = '';
        if (!empty($address)) {
            $addressHtml = <<<ADDR
                <tr>
                    <td style="padding: 4px 0; color: #cbd5e1; font-size: 13px; line-height: 1.5;">
                        <span style="color: #94a3b8; font-weight: 600;">📍 Dirección:</span> {$address}
                    </td>
                </tr>
ADDR;
        }

        // Bloque de Teléfono y Correo
        $contactItems = [];
        if (!empty($phone)) {
            $contactItems[] = <<<PHONE
<span style="color: #94a3b8; font-weight: 600;">📞 Teléfono:</span> <a href="tel:{$cleanPhone}" style="color: #60a5fa; text-decoration: none; font-weight: 500;">{$phone}</a>
PHONE;
        }
        if (!empty($email)) {
            $contactItems[] = <<<EMAIL
<span style="color: #94a3b8; font-weight: 600;">✉️ Correo:</span> <a href="mailto:{$email}" style="color: #60a5fa; text-decoration: none; font-weight: 500;">{$email}</a>
EMAIL;
        }
        $contactHtml = '';
        if (!empty($contactItems)) {
            $contactStr = implode('&nbsp;&nbsp;|&nbsp;&nbsp;', $contactItems);
            $contactHtml = <<<CONTACT
                <tr>
                    <td style="padding: 4px 0; color: #cbd5e1; font-size: 13px; line-height: 1.5;">
                        {$contactStr}
                    </td>
                </tr>
CONTACT;
        }

        // Bloque de Página Web
        $websiteHtml = '';
        if (!empty($website)) {
            $websiteHtml = <<<WEB
                <tr>
                    <td style="padding: 4px 0; color: #cbd5e1; font-size: 13px; line-height: 1.5;">
                        <span style="color: #94a3b8; font-weight: 600;">🌐 Página Web:</span> <a href="{$webUrlSafe}" target="_blank" rel="noopener noreferrer" style="color: #60a5fa; text-decoration: underline; font-weight: 500;">{$webDisplay}</a>
                    </td>
                </tr>
WEB;
        }

        return <<<HTML
        <!-- Footer con datos de la clínica -->
        <tr>
            <td style="background-color: #1e293b; padding: 28px 36px; text-align: center; font-family: 'Segoe UI', Arial, sans-serif; border-top: 3px solid #3b82f6;">
                <p style="color: #ffffff; font-size: 16px; font-weight: 700; margin: 0 0 12px 0; letter-spacing: 0.3px;">
                    🏥 {$clinicName}
                </p>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin: 0 auto; text-align: center; max-width: 520px;">
                    {$addressHtml}
                    {$contactHtml}
                    {$websiteHtml}
                </table>
                <div style="border-top: 1px solid #334155; padding-top: 14px; margin-top: 14px;">
                    <p style="color: #94a3b8; font-size: 11px; margin: 0; line-height: 1.6;">
                        Este correo fue enviado automáticamente por el sistema de <strong>{$clinicName}</strong>.<br>
                        Por favor, no responda directamente a este mensaje.
                    </p>
                </div>
            </td>
        </tr>
HTML;
    }

    /**
     * Genera el HTML bonito para el correo de confirmación de cita.
     */
    public static function buildConfirmationEmail(array $appointment): string
    {
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $patientId    = htmlspecialchars($appointment['patient_id_number'] ?? '');
        $patientPhone = htmlspecialchars($appointment['patient_phone'] ?? '');
        $patientEmail = htmlspecialchars($appointment['patient_email'] ?? '');
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $specialty    = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time         = date('H:i', strtotime($appointment['appointment_date']));
        $citaId       = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);

        $footerHtml = self::buildEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #1a73e8, #0d47a1); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">✅ Cita Confirmada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Su cita ha sido confirmada exitosamente</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>{$patientName}</strong>, le informamos que su cita médica ha sido <span style="color: #1a73e8; font-weight: 600;">CONFIRMADA</span>. A continuación los detalles:
                </p>

                <!-- Appointment Details Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #1a73e8; font-size: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                                📋 Orden de Cita #{$citaId}
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">Paciente:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Cédula:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Teléfono:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 10px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Especialidad:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Médico:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">Dr(a). {$doctorName}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 10px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📅 Fecha:</td>
                                    <td style="padding: 8px 0; color: #1a73e8; font-size: 16px; font-weight: 700;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🕐 Hora:</td>
                                    <td style="padding: 8px 0; color: #1a73e8; font-size: 16px; font-weight: 700;">{$time}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Important Notice -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fef9e7; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                                <strong>⚠️ Importante:</strong> Por favor, llegue 15 minutos antes de la hora programada con su cédula de identidad. Para REAGENDAMIENTO la solicitud debe realizarse máximo 24 HORAS ANTES DE LA CITA, caso contrario de no acudir a la cita en el día y hora señalada, pierde el turno. "NO REALIZAMOS DEVOLUCIÓN"
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML bonito para el correo de reagendamiento de cita.
     */
    public static function buildRescheduleEmail(array $appointment): string
    {
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $patientId    = htmlspecialchars($appointment['patient_id_number'] ?? '');
        $patientPhone = htmlspecialchars($appointment['patient_phone'] ?? '');
        $patientEmail = htmlspecialchars($appointment['patient_email'] ?? '');
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $specialty    = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time         = date('H:i', strtotime($appointment['appointment_date']));
        $citaId       = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);
        $rescheduleCount = (int)($appointment['reschedule_count'] ?? 1);

        $footerHtml = self::buildEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">🔄 Cita Reagendada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Su cita ha sido reprogramada exitosamente</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>{$patientName}</strong>, le informamos que su cita médica ha sido <span style="color: #d97706; font-weight: 600;">REAGENDADA</span>. A continuación los nuevos detalles:
                </p>

                <!-- Appointment Details Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #d97706; font-size: 16px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                                📋 Cita Reagendada #{$citaId} (Reagendamiento #{$rescheduleCount})
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">Paciente:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Cédula:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Teléfono:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 10px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Especialidad:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">Médico:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">Dr(a). {$doctorName}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 10px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📅 Nueva Fecha:</td>
                                    <td style="padding: 8px 0; color: #d97706; font-size: 16px; font-weight: 700;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🕐 Nueva Hora:</td>
                                    <td style="padding: 8px 0; color: #d97706; font-size: 16px; font-weight: 700;">{$time}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Important Notice -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fef9e7; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                                <strong>⚠️ Importante:</strong> Por favor, llegue 15 minutos antes de la hora programada con su cédula de identidad. Para REAGENDAMIENTO la solicitud debe realizarse máximo 24 HORAS ANTES DE LA CITA, caso contrario de no acudir a la cita en el día y hora señalada, pierde el turno. "NO REALIZAMOS DEVOLUCIÓN"
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML para notificar al DOCTOR de una cita confirmada.
     */
    public static function buildDoctorConfirmationEmail(array $appointment): string
    {
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $patientId    = htmlspecialchars($appointment['patient_id_number'] ?? '');
        $patientPhone = htmlspecialchars($appointment['patient_phone'] ?? '');
        $patientEmail = htmlspecialchars($appointment['patient_email'] ?? '');
        $specialty    = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time         = date('H:i', strtotime($appointment['appointment_date']));
        $citaId       = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);
        $notes        = htmlspecialchars($appointment['notes'] ?? '');

        $footerHtml   = self::buildEmailFooter();

        $notesRow = $notes
            ? "<tr><td style=\"padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;\">📝 Notas:</td><td style=\"padding: 8px 0; color: #1e293b; font-size: 14px;\">{$notes}</td></tr>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #0f766e, #0d9488); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">📋 Nueva Cita Confirmada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Tiene una cita confirmada con un paciente</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>Dr(a). {$doctorName}</strong>, se le notifica que la siguiente cita ha sido <span style="color: #0f766e; font-weight: 600;">CONFIRMADA</span> en su agenda:
                </p>

                <!-- Appointment Details Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f0fdf9; border-radius: 10px; border: 1px solid #ccfbf1; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #0f766e; font-size: 16px; border-bottom: 2px solid #ccfbf1; padding-bottom: 10px;">
                                📅 Cita #{$citaId} — {$date} a las {$time}
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">👤 Paciente:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🪪 Cédula:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📞 Teléfono:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">✉️ Email:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientEmail}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 10px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🩺 Especialidad:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📅 Fecha:</td>
                                    <td style="padding: 8px 0; color: #0f766e; font-size: 16px; font-weight: 700;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🕐 Hora:</td>
                                    <td style="padding: 8px 0; color: #0f766e; font-size: 16px; font-weight: 700;">{$time}</td>
                                </tr>
                                {$notesRow}
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Info Notice -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f0fdf4; border-radius: 8px; border-left: 4px solid #22c55e; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #14532d; font-size: 13px; line-height: 1.5;">
                                <strong>ℹ️ Recuerde:</strong> El paciente ya fue notificado de esta cita. Si necesita cancelar o modificar, por favor contacte a la administración.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML para notificar al DOCTOR de un reagendamiento de cita.
     */
    public static function buildDoctorRescheduleEmail(array $appointment): string
    {
        $doctorName     = htmlspecialchars($appointment['doctor_name'] ?? '');
        $patientName    = htmlspecialchars($appointment['patient_name'] ?? '');
        $patientId      = htmlspecialchars($appointment['patient_id_number'] ?? '');
        $patientPhone   = htmlspecialchars($appointment['patient_phone'] ?? '');
        $patientEmail   = htmlspecialchars($appointment['patient_email'] ?? '');
        $specialty      = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date           = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time           = date('H:i', strtotime($appointment['appointment_date']));
        $citaId         = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);
        $rescheduleCount = (int)($appointment['reschedule_count'] ?? 1);
        $notes          = htmlspecialchars($appointment['notes'] ?? '');

        $footerHtml     = self::buildEmailFooter();

        $notesRow = $notes
            ? "<tr><td style=\"padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;\">📝 Notas:</td><td style=\"padding: 8px 0; color: #1e293b; font-size: 14px;\">{$notes}</td></tr>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #7c3aed, #6d28d9); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">🔄 Cita Reagendada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Una cita en su agenda ha sido reprogramada (Reagendamiento #{$rescheduleCount})</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>Dr(a). {$doctorName}</strong>, le informamos que una cita en su agenda ha sido <span style="color: #7c3aed; font-weight: 600;">REAGENDADA</span>. A continuación los nuevos detalles:
                </p>

                <!-- Appointment Details Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #faf5ff; border-radius: 10px; border: 1px solid #e9d5ff; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #7c3aed; font-size: 16px; border-bottom: 2px solid #e9d5ff; padding-bottom: 10px;">
                                📋 Cita #{$citaId} — Nueva fecha: {$date} a las {$time}
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">👤 Paciente:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🪪 Cédula:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📞 Teléfono:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">✉️ Email:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientEmail}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 10px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #cbd5e1; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🩺 Especialidad:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📅 Nueva Fecha:</td>
                                    <td style="padding: 8px 0; color: #7c3aed; font-size: 16px; font-weight: 700;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🕐 Nueva Hora:</td>
                                    <td style="padding: 8px 0; color: #7c3aed; font-size: 16px; font-weight: 700;">{$time}</td>
                                </tr>
                                {$notesRow}
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Warning Notice -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fffbeb; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                                <strong>⚠️ Aviso:</strong> Por favor actualice su agenda para reflejar este cambio de horario. El paciente también ha sido notificado de este reagendamiento.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML para notificar la creación de cuenta y credenciales temporales.
     */
    public static function buildUserCreationEmail(array $data): string
    {
        $name = htmlspecialchars($data['name'] ?? '');
        $email = htmlspecialchars($data['email'] ?? '');
        $username = htmlspecialchars($data['username'] ?? '');
        $tempPassword = htmlspecialchars($data['tempPassword'] ?? '');
        $resetLink = htmlspecialchars($data['resetLink'] ?? '');

        $footerHtml = self::buildEmailFooter();

        $userDisplay = !empty($username) ? $username : $email;
        $usernameRow = '';
        if (!empty($username) && $username !== $email) {
            $usernameRow = <<<TR
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">Usuario de acceso:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 700;"><code style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-family: Consolas, monospace; font-size: 14px; color: #1e40af;">{$userDisplay}</code></td>
                                </tr>
TR;
        } else {
            $usernameRow = <<<TR
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">Usuario / Email:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 700;"><code style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-family: Consolas, monospace; font-size: 14px; color: #1e40af;">{$email}</code></td>
                                </tr>
TR;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #1a73e8, #0d47a1); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">🎉 Cuenta Creada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Credenciales de Acceso al Portal</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>{$name}</strong>, le informamos que su cuenta en nuestro sistema ha sido creada exitosamente. A continuación sus credenciales de acceso:
                </p>

                <!-- Credentials Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                {$usernameRow}
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">Correo electrónico:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$email}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">Contraseña temporal:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 700;"><code style="background: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-family: Consolas, monospace; font-size: 14px; color: #1e40af;">{$tempPassword}</code></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Para establecer su propia contraseña y acceder al portal en cualquier momento, por favor haga clic en el siguiente botón (enlace válido por 24 horas):
                </p>

                <div style="text-align: center; margin-bottom: 30px;">
                    <a href="{$resetLink}" style="background: linear-gradient(135deg, #1a73e8, #0d47a1); color: #ffffff; text-decoration: none; padding: 13px 28px; border-radius: 8px; font-weight: 600; display: inline-block; box-shadow: 0 4px 12px rgba(26,115,232,0.25);">Establecer mi contraseña</a>
                </div>

            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML para el correo de restablecimiento de contraseña.
     */
    public static function buildPasswordResetEmail(array $data): string
    {
        $name = htmlspecialchars($data['name'] ?? 'Usuario');
        $resetLink = htmlspecialchars($data['resetLink'] ?? '');

        $settings = new SystemSetting();
        $clinicName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
        $footerHtml = self::buildEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #1a73e8, #0d47a1); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">🔐 Restablecer Contraseña</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Solicitud de recuperación de cuenta</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 15px;">
                    Estimado/a <strong>{$name}</strong>,
                </p>
                <p style="color: #4b5563; font-size: 14px; line-height: 1.6; margin: 0 0 25px;">
                    Hemos recibido una solicitud para restablecer la contraseña de su cuenta en <strong>{$clinicName}</strong>. Para continuar con el proceso, haga clic en el botón a continuación:
                </p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{$resetLink}" style="background-color: #1a73e8; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; font-size: 15px; display: inline-block; box-shadow: 0 4px 12px rgba(26,115,232,0.3);">
                        Restablecer mi Contraseña
                    </a>
                </div>

                <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; margin-bottom: 25px;">
                    <p style="color: #92400e; font-size: 13px; line-height: 1.5; margin: 0;">
                        ⏱️ <strong>Aviso de Seguridad:</strong> Este enlace expirará en <strong>2 horas</strong> por motivos de protección. Si usted no solicitó este cambio, puede ignorar este mensaje; su contraseña actual continuará siendo segura.
                    </p>
                </div>

                <p style="color: #6b7280; font-size: 12px; line-height: 1.5; margin: 0 0 10px;">
                    Si el botón no funciona, copie y pegue el siguiente enlace en su navegador:
                </p>
                <p style="color: #1a73e8; font-size: 11px; word-break: break-all; margin: 0 0 25px; line-height: 1.4;">
                    {$resetLink}
                </p>

            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML del correo de cancelación (estado: cancelled).
     *
     * @param array  $appointment  Datos de la cita
     */
    public static function buildCancelledVisitEmail(array $appointment): string
    {
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time         = date('H:i', strtotime($appointment['appointment_date']));

        $settings       = new SystemSetting();
        $clinicName     = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
        $footerHtml     = self::buildEmailFooter();
        
        $appConfig = require CONFIG_PATH . '/app.php';
        $baseUrl = rtrim($appConfig['base_url'], '/');
        $bookingLink = $baseUrl . '/booking';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 6px 30px rgba(0,0,0,0.10);">

        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #ef4444, #b91c1c); padding: 36px 40px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 10px;">❌</div>
                <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">
                    Tu cita médica ha sido anulada
                </h1>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 32px 40px;">

                <!-- Greeting -->
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 18px;">
                    Estimado/a <strong>{$patientName}</strong>, le informamos que la cita médica programada para el <strong>{$date}</strong>, a las <strong>{$time}</strong>, con el Dr. <strong>{$doctorName}</strong>, ha sido anulada.
                </p>
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 18px;">
                    De acuerdo con nuestras políticas de atención, el pago realizado por esta cita no será reembolsado.
                </p>
                
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                    <tr>
                        <td style="text-align: center; padding: 10px 0;">
                            <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 14px;">
                                Si desea programar una nueva cita, puedes hacerlo a través de este enlace:
                            </p>
                            <a href="{$bookingLink}"
                               style="display: inline-block; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #ffffff; text-decoration: none;
                                      padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 700;
                                      letter-spacing: 0.3px; box-shadow: 0 4px 14px rgba(59,130,246,0.35);">
                                📅 Agendar nueva cita
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 24px;">
                    Si tienes alguna consulta sobre esta cancelación, contáctanos por cualquiera de nuestros canales de atención. Agradecemos tu comprensión.
                </p>

                <!-- Closing -->
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 28px;">
                    <strong style="color: #1e293b;">Saludos cordiales,</strong><br>
                    <span style="color: #b91c1c;">{$clinicName}</span>
                </p>
            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML para notificar al MÉDICO que una cita de su agenda fue ANULADA.
     */
    public static function buildDoctorCancelledVisitEmail(array $appointment): string
    {
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $patientId    = htmlspecialchars($appointment['patient_id_number'] ?? '');
        $patientPhone = htmlspecialchars($appointment['patient_phone'] ?? '');
        $specialty    = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time         = date('H:i', strtotime($appointment['appointment_date']));
        $citaId       = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);
        $footerHtml   = self::buildEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #dc2626, #b91c1c); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">❌ Cita Anulada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Una cita en su agenda ha sido cancelada</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>Dr(a). {$doctorName}</strong>, le informamos que la siguiente cita en su agenda ha sido <span style="color: #dc2626; font-weight: 600;">ANULADA</span>:
                </p>

                <!-- Appointment Details Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fef2f2; border-radius: 10px; border: 1px solid #fecaca; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #dc2626; font-size: 16px; border-bottom: 2px solid #fecaca; padding-bottom: 10px;">
                                📋 Cita Cancelada #{$citaId} — {$date} a las {$time}
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">👤 Paciente:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🪪 Cédula:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📞 Teléfono:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 8px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #fecaca; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🩺 Especialidad:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📅 Fecha:</td>
                                    <td style="padding: 8px 0; color: #dc2626; font-size: 16px; font-weight: 700;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🕐 Hora:</td>
                                    <td style="padding: 8px 0; color: #dc2626; font-size: 16px; font-weight: 700;">{$time}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Warning Notice -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fffbeb; border-radius: 8px; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;">
                                <strong>⚠️ Aviso:</strong> Por favor actualice su agenda. El paciente también ha sido notificado de la cancelación de esta cita.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML para notificar al MÉDICO que una consulta fue completada.
     */
    public static function buildDoctorCompletedVisitEmail(array $appointment): string
    {
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $patientId    = htmlspecialchars($appointment['patient_id_number'] ?? '');
        $patientPhone = htmlspecialchars($appointment['patient_phone'] ?? '');
        $specialty    = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $time         = date('H:i', strtotime($appointment['appointment_date']));
        $citaId       = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);
        $footerHtml   = self::buildEmailFooter();

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #059669, #047857); padding: 30px 40px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">✅ Consulta Completada</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 8px 0 0; font-size: 14px;">Resumen de atención médica registrada</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 30px 40px;">
                <p style="color: #333; font-size: 15px; line-height: 1.6; margin: 0 0 20px;">
                    Estimado/a <strong>Dr(a). {$doctorName}</strong>, la siguiente consulta ha sido <span style="color: #059669; font-weight: 600;">COMPLETADA</span> y registrada en el sistema:
                </p>

                <!-- Appointment Details Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 20px;">
                            <h3 style="margin: 0 0 15px; color: #059669; font-size: 16px; border-bottom: 2px solid #bbf7d0; padding-bottom: 10px;">
                                📋 Consulta #{$citaId} — {$date} a las {$time}
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; width: 40%; vertical-align: top;">👤 Paciente:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$patientName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🪪 Cédula:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientId}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📞 Teléfono:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px;">{$patientPhone}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" style="padding: 8px 0 5px;">
                                        <hr style="border: none; border-top: 1px dashed #bbf7d0; margin: 0;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🩺 Especialidad:</td>
                                    <td style="padding: 8px 0; color: #1e293b; font-size: 14px; font-weight: 600;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">📅 Fecha:</td>
                                    <td style="padding: 8px 0; color: #059669; font-size: 16px; font-weight: 700;">{$date}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; color: #64748b; font-size: 13px; vertical-align: top;">🕐 Hora:</td>
                                    <td style="padding: 8px 0; color: #059669; font-size: 16px; font-weight: 700;">{$time}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Info Notice -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f0fdf4; border-radius: 8px; border-left: 4px solid #22c55e; margin-bottom: 20px;">
                    <tr>
                        <td style="padding: 15px 20px;">
                            <p style="margin: 0; color: #14532d; font-size: 13px; line-height: 1.5;">
                                <strong>ℹ️ Nota:</strong> La evolución médica ha sido registrada en el historial clínico del paciente. El paciente también ha recibido un correo de agradecimiento por su visita.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera el HTML del correo de agradecimiento post-visita al PACIENTE.
     *
     * @param array  $appointment  Datos de la cita (patient_name, doctor_name, appointment_date, etc.)
     * @param string $surveyUrl    URL de la encuesta de satisfacción (puede estar vacía)
     */
    public static function buildCompletedVisitEmail(array $appointment, string $surveyUrl = ''): string
    {
        $patientName  = htmlspecialchars($appointment['patient_name'] ?? '');
        $doctorName   = htmlspecialchars($appointment['doctor_name'] ?? '');
        $specialty    = htmlspecialchars($appointment['specialty_name'] ?? '');
        $date         = date('d/m/Y', strtotime($appointment['appointment_date']));
        $citaId       = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);

        $settings       = new SystemSetting();
        $clinicName     = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
        $footerHtml     = self::buildEmailFooter();

        // Bloque CTA encuesta — solo si hay URL configurada
        $surveyBlock = '';
        if (!empty($surveyUrl)) {
            $surveyUrlSafe = htmlspecialchars($surveyUrl);
            $surveyBlock = <<<SURVEY
                <!-- Survey CTA -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
                    <tr>
                        <td style="text-align: center; padding: 10px 0;">
                            <p style="margin: 0 0 14px; color: #475569; font-size: 14px; line-height: 1.6;">
                                Si lo deseas, cuéntanos cómo fue tu experiencia. Tu opinión nos ayuda a mejorar:
                            </p>
                            <a href="{$surveyUrlSafe}"
                               style="display: inline-block; background: linear-gradient(135deg, #10b981, #059669); color: #ffffff; text-decoration: none;
                                      padding: 14px 32px; border-radius: 50px; font-size: 15px; font-weight: 700;
                                      letter-spacing: 0.3px; box-shadow: 0 4px 14px rgba(16,185,129,0.35);">
                                ⭐ Calificar mi experiencia
                            </a>
                        </td>
                    </tr>
                </table>
SURVEY;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 6px 30px rgba(0,0,0,0.10);">

        <!-- Header -->
        <tr>
            <td style="background: linear-gradient(135deg, #10b981, #047857); padding: 36px 40px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 10px;">🌟</div>
                <h1 style="color: #ffffff; margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.5px;">
                    Gracias por tu visita
                </h1>
                <p style="color: rgba(255,255,255,0.88); margin: 10px 0 0; font-size: 15px; line-height: 1.5;">
                    Esperamos haberte brindado una excelente atención
                </p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style="padding: 32px 40px;">

                <!-- Greeting -->
                <p style="color: #1e293b; font-size: 16px; line-height: 1.7; margin: 0 0 18px;">
                    Hola <strong>{$patientName}</strong>,
                </p>
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 18px;">
                    Gracias por confiar en <strong style="color: #047857;">{$clinicName}</strong>.
                    Esperamos que la atención recibida durante tu cita con
                    <strong>Dr(a). {$doctorName}</strong> haya sido satisfactoria.
                </p>
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 24px;">
                    Tu bienestar es nuestra prioridad. Si necesitas programar una nueva cita
                    o dar seguimiento a tu tratamiento, estaremos encantados de atenderte.
                </p>

                <!-- Appointment Summary Card -->
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                       style="background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0; margin-bottom: 28px;">
                    <tr>
                        <td style="padding: 20px 24px;">
                            <h3 style="margin: 0 0 14px; color: #065f46; font-size: 15px; border-bottom: 1px solid #bbf7d0; padding-bottom: 10px;">
                                ✅ Resumen de tu cita #{$citaId}
                            </h3>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 6px 0; color: #6b7280; font-size: 13px; width: 38%; vertical-align: top;">Médico:</td>
                                    <td style="padding: 6px 0; color: #1e293b; font-size: 14px; font-weight: 600;">Dr(a). {$doctorName}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #6b7280; font-size: 13px; vertical-align: top;">Especialidad:</td>
                                    <td style="padding: 6px 0; color: #1e293b; font-size: 14px;">{$specialty}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #6b7280; font-size: 13px; vertical-align: top;">Fecha:</td>
                                    <td style="padding: 6px 0; color: #047857; font-size: 14px; font-weight: 700;">{$date}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                {$surveyBlock}

                <!-- Closing -->
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 8px;">
                    Agradecemos nuevamente tu confianza.
                </p>
                <p style="color: #475569; font-size: 15px; line-height: 1.7; margin: 0 0 28px;">
                    <strong style="color: #1e293b;">Saludos cordiales,</strong><br>
                    <span style="color: #047857;">{$clinicName}</span>
                </p>
            </td>
        </tr>

        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }
    /**
     * Genera el HTML para el correo de la orden / prefactura
     */
    public static function buildOrderEmail(array $orderData, string $patientName, string $rawDate, bool $isUpdate = false, array $patientExtra = [], string $catalogTypeName = ''): string
    {
        $settings = new SystemSetting();
        $companyName = htmlspecialchars($settings->get('company_name', $settings->get('smtp_from_name', 'Laboratorio / Institución')));
        $companyPhone = htmlspecialchars($settings->get('company_phone', 'Teléfono no configurado'));
        $companyEmail = htmlspecialchars($settings->get('company_email', 'Correo no configurado'));
        $clinicName = htmlspecialchars($settings->get('smtp_from_name', 'Portal Cmevi Pro'));
        $companyAddress = htmlspecialchars($settings->get('company_address', 'Dirección no configurada'));
        $companyWebsite = htmlspecialchars($settings->get('company_website', ''));
        $footerHtml = self::buildEmailFooter();

        $orderTypeText = 'orden';
        if (!empty($catalogTypeName)) {
            $orderTypeText = "orden de " . strtolower(htmlspecialchars($catalogTypeName));
        }

        $introText = $isUpdate ? "ha sido actualizada" : "ha sido registrada y confirmada correctamente";
        
        $timestamp = strtotime($rawDate);
        $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $dayName = $days[date('w', $timestamp)];
        $dateStr = $dayName . ', ' . date('d/m/Y H:i', $timestamp);
        
        $patientId = htmlspecialchars($patientExtra['id'] ?? '');
        $patientPhone = htmlspecialchars($patientExtra['phone'] ?? '');
        $patientEmail = htmlspecialchars($patientExtra['email'] ?? '');
        $orderId = str_pad((string)($orderData['id'] ?? '0'), 6, '0', STR_PAD_LEFT);

        $defaultTemplate = "
<div style=\"background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);\">
    <p>Estimado/a <strong>{patient_name}</strong>:</p>
    
    <p>Le informamos que su {order_type_text} <strong>{intro_text}</strong>.</p>
    
    <ul>
        <li><strong>Número de orden:</strong> {order_id}</li>
        <li><strong>Fecha de la cita:</strong> {date}</li>
        <li><strong>Nombre del paciente:</strong> {patient_name}</li>
        <li><strong>Identificación:</strong> {patient_id}</li>
        <li><strong>Teléfono:</strong> {patient_phone}</li>
        <li><strong>Correo:</strong> {patient_email}</li>
    </ul>

    <h3 style=\"color: #1a73e8; margin-top: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px;\">Indicaciones para la toma de muestras</h3>
    <p>De acuerdo con los exámenes solicitados, tenga en cuenta las siguientes recomendaciones:</p>

    <h4 style=\"margin-bottom: 5px; color: #0d47a1;\">Exámenes de sangre:</h4>
    <ul style=\"margin-top: 0; padding-left: 20px;\">
        <li>Presentarse en ayunas únicamente si la orden médica así lo requiere.</li>
        <li>En caso de ayuno, no consumir alimentos ni bebidas, excepto agua, durante el período indicado por el laboratorio.</li>
        <li>Evitar realizar actividad física intensa antes de la toma de muestra.</li>
        <li>Seguir cualquier indicación adicional proporcionada por el médico o el laboratorio.</li>
    </ul>

    <h4 style=\"margin-bottom: 5px; color: #0d47a1;\">Exámenes de orina:</h4>
    <ul style=\"margin-top: 0; padding-left: 20px;\">
        <li>Preferiblemente recolectar la primera orina de la mañana, salvo que exista una indicación médica diferente.</li>
        <li>Realizar previamente una adecuada higiene de la zona genital.</li>
        <li>Recolectar la muestra en el recipiente estéril proporcionado o indicado por el laboratorio.</li>
        <li>Entregar la muestra dentro del tiempo indicado por el laboratorio.</li>
    </ul>

    <h4 style=\"margin-bottom: 5px; color: #0d47a1;\">Exámenes de heces:</h4>
    <ul style=\"margin-top: 0; padding-left: 20px;\">
        <li>Recolectar la muestra en un recipiente limpio, seco y adecuado para este propósito.</li>
        <li>Evitar que la muestra entre en contacto con agua, orina u otros materiales.</li>
        <li>No colocar papel higiénico directamente dentro del recipiente.</li>
        <li>Entregar la muestra al laboratorio según las indicaciones y el tiempo establecido.</li>
    </ul>

    <div style=\"background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border-left: 4px solid #ffeeba; margin-top: 20px;\">
        <strong>Importante:</strong> Las indicaciones pueden variar según el examen solicitado. Si su médico o el laboratorio le proporcionó instrucciones específicas, estas tienen prioridad sobre las recomendaciones generales mencionadas anteriormente.
    </div>

    <p style=\"margin-top: 20px;\">Adjunto a este correo encontrará el PDF correspondiente al valor de la orden de laboratorio o examen, para su revisión y referencia.</p>

    <p>Si tiene alguna duda sobre la preparación o recolección de la muestra, comuníquese con el laboratorio antes de acudir a su cita.</p>

    <p style=\"margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0;\">
        Saludos cordiales,<br>
        <strong>{company_name}</strong><br>
        {company_address}<br>
        {company_phone} | {company_email}<br>
        {company_website}
    </p>
</div>
        ";
        
        $template = $settings->get('order_email_template', '');
        if (empty(trim($template)) || strlen(trim(strip_tags($template))) < 5) {
            $template = trim($defaultTemplate);
        }

        $replacements = [
            '{patient_name}' => $patientName,
            '{patient_id}' => $patientId,
            '{patient_phone}' => $patientPhone,
            '{patient_email}' => $patientEmail,
            '{order_id}' => $orderId,
            '{date}' => $dateStr,
            '{order_type_text}' => $orderTypeText,
            '{intro_text}' => $introText,
            '{clinic_name}' => $clinicName,
            '{company_name}' => $companyName,
            '{company_address}' => $companyAddress,
            '{company_phone}' => $companyPhone,
            '{company_email}' => $companyEmail,
            '{company_website}' => $companyWebsite,
            '{clinic_footer}' => $footerHtml
        ];

        $htmlContent = str_replace(array_keys($replacements), array_values($replacements), $template);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: 'Segoe UI', Arial, sans-serif; color: #333; line-height: 1.6; padding: 20px; max-width: 600px; margin: 0 auto; background-color: #f0f2f5;">
    {$htmlContent}
    <div style="margin-top: 20px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            {$footerHtml}
        </table>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Envía el correo de restablecimiento de contraseña.
     */
    public static function sendPasswordResetEmail(string $to, string $name, string $resetLink): bool
    {
        $settings = new SystemSetting();
        $companyName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
        $subject = "Restablecer su contraseña - {$companyName}";
        $body = self::buildPasswordResetEmail([
            'name' => $name,
            'resetLink' => $resetLink
        ]);
        return self::send($to, $subject, $body);
    }

    /**
     * Envía el código de autenticación en dos pasos (2FA).
     */
    public static function sendTwoFactorCode(string $to, string $code, string $recipientName = ''): bool
    {
        $settings = new SystemSetting();
        $companyName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
        $subject = "{$code} es su código de verificación de seguridad - {$companyName}";
        $body = self::buildTwoFactorEmail($code, $recipientName);
        return self::send($to, $subject, $body);
    }

    /**
     * Genera el HTML con el código de verificación 2FA.
     */
    public static function buildTwoFactorEmail(string $code, string $recipientName = ''): string
    {
        $footerHtml = self::buildEmailFooter();
        $settings = new SystemSetting();
        $companyName = htmlspecialchars($settings->get('company_name', $settings->get('smtp_from_name', 'Portal Cmevi Pro')));
        $safeName = !empty($recipientName) ? htmlspecialchars($recipientName) : 'Usuario';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; color: #333333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 30px 40px; text-align: center;">
                <div style="font-size: 40px; margin-bottom: 8px;">🔐</div>
                <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Verificación de Seguridad (2FA)</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 14px;">{$companyName}</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 35px 40px;">
                <p style="font-size: 16px; margin: 0 0 16px; color: #1e293b;">Hola, <strong>{$safeName}</strong></p>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 24px;">
                    Se ha solicitado el acceso a su cuenta. Para completar el inicio de sesión de manera segura, ingrese el siguiente código de verificación temporal:
                </p>

                <div style="background: #f8fafc; border: 2px dashed #3b82f6; border-radius: 12px; padding: 22px; text-align: center; margin: 25px 0;">
                    <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1.5px; color: #64748b; font-weight: 600; margin-bottom: 8px;">Código de Verificación</div>
                    <span style="font-size: 36px; font-weight: 800; letter-spacing: 10px; color: #1e40af; font-family: 'Courier New', Courier, monospace; display: inline-block;">{$code}</span>
                    <div style="font-size: 12px; color: #dc2626; margin-top: 8px; font-weight: 500;">⏱ Este código expira en 10 minutos</div>
                </div>

                <div style="background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 6px; padding: 14px; margin-top: 25px;">
                    <strong style="color: #991b1b; font-size: 13px; display: block; margin-bottom: 4px;">⚠️ ¿No solicitó este código?</strong>
                    <span style="color: #7f1d1d; font-size: 13px; line-height: 1.5;">Si usted no intentó ingresar al sistema, es posible que alguien conozca su contraseña. Le recomendamos cambiar su clave inmediatamente.</span>
                </div>

                <p style="font-size: 13px; color: #94a3b8; margin-top: 30px; text-align: center; line-height: 1.5;">
                    Por su seguridad, nunca comparta este código con ninguna persona. Ningún miembro de nuestro personal le solicitará este código.
                </p>
            </td>
        </tr>
        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Envía un correo de alerta de nuevo inicio de sesión.
     */
    public static function sendLoginAlert(string $to, string $recipientName, string $ip, string $userAgent = '', string $userType = ''): bool
    {
        $settings = new SystemSetting();
        $companyName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
        $subject = "Aviso de Seguridad: Nuevo inicio de sesión en su cuenta - {$companyName}";
        $dateTime = date('d/m/Y H:i:s');
        $body = self::buildLoginAlertEmail($recipientName, $ip, $dateTime, $userAgent, $userType);
        // Encolar en background — no bloquea el flujo de login del usuario
        return self::queue($to, $subject, $body);
    }

    /**
     * Genera el HTML para la notificación de alerta de nuevo acceso.
     */
    public static function buildLoginAlertEmail(string $recipientName, string $ip, string $dateTime, string $userAgent = '', string $userType = ''): string
    {
        $footerHtml = self::buildEmailFooter();
        $settings = new SystemSetting();
        $companyName = htmlspecialchars($settings->get('company_name', $settings->get('smtp_from_name', 'Portal Cmevi Pro')));
        $safeName = !empty($recipientName) ? htmlspecialchars($recipientName) : 'Usuario';
        $safeIp = htmlspecialchars($ip);
        $safeUserAgent = htmlspecialchars(!empty($userAgent) ? substr($userAgent, 0, 150) : 'Navegador Web');

        $typeLabels = [
            'admin' => 'Administrador / Recepcionista',
            'doctor' => 'Médico Especialista',
            'patient' => 'Paciente'
        ];
        $typeLabel = $typeLabels[$userType] ?? ucfirst($userType);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: #f0f2f5; color: #333333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #0f766e, #14b8a6); padding: 25px 40px; text-align: center;">
                <div style="font-size: 38px; margin-bottom: 6px;">🛡️</div>
                <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700;">Nuevo Inicio de Sesión</h1>
                <p style="color: rgba(255,255,255,0.85); margin: 6px 0 0; font-size: 14px;">Notificación de seguridad de su cuenta</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 35px 40px;">
                <p style="font-size: 16px; margin: 0 0 16px; color: #1e293b;">Hola, <strong>{$safeName}</strong></p>
                <p style="font-size: 14px; line-height: 1.6; color: #475569; margin: 0 0 20px;">
                    Le informamos que se ha detectado un inicio de sesión exitoso en su cuenta en <strong>{$companyName}</strong>.
                </p>

                <table width="100%" cellpadding="10" cellspacing="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; margin-bottom: 25px;">
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="color: #64748b; width: 35%; font-weight: 600;">📅 Fecha y Hora:</td>
                        <td style="color: #1e293b;">{$dateTime}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="color: #64748b; font-weight: 600;">🌐 Dirección IP:</td>
                        <td style="color: #1e293b;"><code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-size: 13px;">{$safeIp}</code></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <td style="color: #64748b; font-weight: 600;">👤 Perfil:</td>
                        <td style="color: #1e293b;">{$typeLabel}</td>
                    </tr>
                    <tr>
                        <td style="color: #64748b; font-weight: 600;">💻 Dispositivo:</td>
                        <td style="color: #64748b; font-size: 12px;">{$safeUserAgent}</td>
                    </tr>
                </table>

                <div style="background: #ecfdf5; border-left: 4px solid #10b981; border-radius: 6px; padding: 14px; margin-bottom: 20px;">
                    <strong style="color: #065f46; font-size: 13px; display: block; margin-bottom: 4px;">¿Fue usted?</strong>
                    <span style="color: #047857; font-size: 13px; line-height: 1.5;">Si reconoció este acceso, puede ignorar este mensaje con total tranquilidad.</span>
                </div>

                <div style="background: #fff1f2; border-left: 4px solid #f43f5e; border-radius: 6px; padding: 14px;">
                    <strong style="color: #9f1239; font-size: 13px; display: block; margin-bottom: 4px;">⚠️ ¿No reconoce esta actividad?</strong>
                    <span style="color: #be123c; font-size: 13px; line-height: 1.5;">Si usted no inició sesión, alguien más podría haber tenido acceso a sus credenciales. Le recomendamos ingresar de inmediato y cambiar su contraseña.</span>
                </div>
            </td>
        </tr>
        {$footerHtml}
    </table>
</body>
</html>
HTML;
    }

    /**
     * Envía la notificación de recepción de cita médica al paciente con sus credenciales de acceso y bienvenida.
     *
     * @param string $to    Correo del paciente
     * @param array  $data  Datos de la cita y credenciales
     */
    public static function sendPatientBookingNotification(string $to, array $data): bool
    {
        $info = self::getClinicInfo();
        $clinicName = !empty($data['clinic_name']) ? $data['clinic_name'] : $info['name'];
        $subject = !empty($data['is_new_user'])
            ? "¡Bienvenido a {$clinicName}! Credenciales de Acceso a la Plataforma"
            : "Hemos recibido tu solicitud de cita médica - {$clinicName}";
        $body = self::buildPatientBookingEmail($data);
        return self::queue($to, $subject, $body);
    }

    /**
     * Genera el HTML para el correo único de bienvenida, credenciales y resumen de cita médica.
     *
     * @param array $data [
     *   'patient_name'     => string,
     *   'username'         => string,
     *   'password'         => string,
     *   'portal_url'       => string,
     *   'clinic_name'      => string (opcional),
     *   'clinic_phone'     => string (opcional),
     *   'clinic_email'     => string (opcional),
     *   'is_new_user'      => bool (opcional),
     *   'appointment_id'   => int (opcional),
     *   'doctor_name'      => string (opcional),
     *   'specialty_name'   => string (opcional),
     *   'appointment_date' => string (opcional),
     *   'start_time'       => string (opcional),
     * ]
     */
    public static function buildPatientBookingEmail(array $data): string
    {
        $info = self::getClinicInfo();

        $patientName = htmlspecialchars($data['patient_name'] ?? 'Paciente');
        $username    = htmlspecialchars($data['username'] ?? '');
        $password    = $data['password'] ?? '';
        $portalUrl   = !empty($data['portal_url']) ? $data['portal_url'] : '';
        $portalUrlSafe = htmlspecialchars($portalUrl);

        $clinicName  = htmlspecialchars(!empty($data['clinic_name']) ? $data['clinic_name'] : $info['name']);
        $clinicPhone = htmlspecialchars(!empty($data['clinic_phone']) ? $data['clinic_phone'] : $info['phone']);
        $clinicEmail = htmlspecialchars(!empty($data['clinic_email']) ? $data['clinic_email'] : $info['email']);

        $appointmentId   = !empty($data['appointment_id']) ? (int)$data['appointment_id'] : null;
        $doctorName      = !empty($data['doctor_name']) ? htmlspecialchars($data['doctor_name']) : '';
        $specialtyName   = !empty($data['specialty_name']) ? htmlspecialchars($data['specialty_name']) : '';
        $appointmentDate = !empty($data['appointment_date']) ? htmlspecialchars($data['appointment_date']) : '';
        $startTime       = !empty($data['start_time']) ? htmlspecialchars(substr($data['start_time'], 0, 5)) : '';

        // Usuario nuevo con contraseña generada
        $isNewUser      = !empty($data['is_new_user']);
        $hasNewPassword = $isNewUser && !empty($password) && $password !== 'Tu contraseña habitual';

        if ($hasNewPassword) {
            $headerIcon     = '&#x1F389;'; // 🎉
            $headerTitle    = '&iexcl;Bienvenido/a a ' . $clinicName . '!';
            $headerSubtitle = 'Credenciales de Acceso al Portal del Paciente';
            $introGreeting  = 'Estimado/a <strong>' . $patientName . '</strong>:';
            $introText      = '<p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 14px;">
                                &iexcl;Te damos la m&aacute;s cordial bienvenida a nuestra plataforma de salud! Hemos registrado exitosamente tu solicitud de cita m&eacute;dica y hemos creado tu cuenta de paciente para que puedas gestionar tus citas, consultar resultados y dar seguimiento a tu atenci&oacute;n m&eacute;dica.
                               </p>
                               <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 18px;">
                                A continuaci&oacute;n encontrar&aacute;s tus credenciales de acceso para ingresar a la plataforma:
                               </p>';

            $passwordEscaped = htmlspecialchars($password);
            $credentialsBlock = '
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 20px;">
                    <tr><td style="padding: 18px 22px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="padding: 6px 0; color: #64748b; font-size: 14px; width: 38%; vertical-align: middle;"><strong>Usuario de acceso:</strong></td>
                                <td style="padding: 6px 0; color: #0f172a; font-size: 15px; font-weight: 700;">
                                    <code style="background: #e2e8f0; padding: 4px 10px; border-radius: 4px; font-family: Consolas, monospace; font-size: 14px; color: #1e40af;">' . $username . '</code>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; color: #64748b; font-size: 14px; vertical-align: middle;"><strong>Contrase&ntilde;a temporal:</strong></td>
                                <td style="padding: 6px 0; color: #0f172a; font-size: 15px; font-weight: 700;">
                                    <code style="background: #e2e8f0; padding: 4px 10px; border-radius: 4px; font-family: Consolas, monospace; font-size: 14px; color: #1e40af;">' . $passwordEscaped . '</code>
                                </td>
                            </tr>
                        </table>
                    </td></tr>
                </table>
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background: #fefce8; border-left: 4px solid #eab308; border-radius: 6px; margin-bottom: 22px;">
                    <tr><td style="padding: 12px 16px;">
                        <p style="margin: 0; color: #854d0e; font-size: 13px; line-height: 1.5;">
                            &#x1F6E1;&#xFE0F; Por tu seguridad, te recomendamos cambiar tu contrase&ntilde;a la primera vez que ingreses al portal.
                        </p>
                    </td></tr>
                </table>';
        } else {
            $headerIcon     = '&#x1F4CB;'; // 📋
            $headerTitle    = 'Solicitud de Cita M&eacute;dica';
            $headerSubtitle = 'Hemos recibido correctamente tu solicitud';
            $introGreeting  = 'Hola, <strong>' . $patientName . '</strong>:';
            $introText      = '<p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 16px;">
                                Hemos recibido correctamente tu solicitud de cita m&eacute;dica. Puedes consultar el estado de tu cita ingresando a tu perfil de paciente con tus credenciales habituales.
                               </p>';
            $credentialsBlock = !empty($username)
                ? '<p style="color:#334155;font-size:14px;margin:0 0 18px;">Ingresa con el usuario <strong style="color:#1e40af;">' . $username . '</strong> y tu contrase&ntilde;a habitual.</p>'
                : '';
        }

        // Bloque de resumen de cita (si está disponible)
        $appointmentSummaryBlock = '';
        if ($appointmentId) {
            $formattedId = '#' . str_pad((string)$appointmentId, 6, '0', STR_PAD_LEFT);
            $appointmentSummaryBlock = '
                <div style="background: #f1f5f9; border-radius: 10px; padding: 18px 22px; margin: 20px 0;">
                    <p style="margin: 0 0 12px; color: #1e293b; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                        &#x1F4C5; Resumen de la Cita Solicitada
                    </p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; color: #334155;">
                        <tr>
                            <td style="padding: 5px 0; color: #64748b; width: 38%;">N&uacute;mero de Cita:</td>
                            <td style="padding: 5px 0; font-weight: 600; color: #0f172a;">' . $formattedId . '</td>
                        </tr>';

            if (!empty($specialtyName)) {
                $appointmentSummaryBlock .= '
                        <tr>
                            <td style="padding: 5px 0; color: #64748b;">Especialidad:</td>
                            <td style="padding: 5px 0; font-weight: 600; color: #0f172a;">' . $specialtyName . '</td>
                        </tr>';
            }

            if (!empty($doctorName)) {
                $appointmentSummaryBlock .= '
                        <tr>
                            <td style="padding: 5px 0; color: #64748b;">M&eacute;dico:</td>
                            <td style="padding: 5px 0; font-weight: 600; color: #0f172a;">' . $doctorName . '</td>
                        </tr>';
            }

            if (!empty($appointmentDate)) {
                $dateDisplay = $appointmentDate . (!empty($startTime) ? ' a las ' . $startTime : '');
                $appointmentSummaryBlock .= '
                        <tr>
                            <td style="padding: 5px 0; color: #64748b;">Fecha y Hora:</td>
                            <td style="padding: 5px 0; font-weight: 600; color: #0f172a;">' . $dateDisplay . '</td>
                        </tr>';
            }

            $appointmentSummaryBlock .= '
                        <tr>
                            <td style="padding: 5px 0; color: #64748b;">Estado:</td>
                            <td style="padding: 5px 0; font-weight: 600; color: #d97706;">Pendiente de confirmaci&oacute;n</td>
                        </tr>
                    </table>
                </div>';
        }

        // Línea de contacto
        $contactParts = [];
        if (!empty($clinicPhone)) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $clinicPhone);
            $contactParts[] = '<a href="tel:' . $cleanPhone . '" style="color: #2563eb; text-decoration: none; font-weight: 500;">' . $clinicPhone . '</a>';
        }
        if (!empty($clinicEmail)) {
            $contactParts[] = '<a href="mailto:' . $clinicEmail . '" style="color: #2563eb; text-decoration: none; font-weight: 500;">' . $clinicEmail . '</a>';
        }
        $contactLine = !empty($contactParts) ? implode(' | ', $contactParts) : ($clinicPhone . ' | ' . $clinicEmail);

        $footerHtml = self::buildEmailFooter();

        return '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: \'Segoe UI\', Arial, sans-serif; background-color: #f0f2f5; color: #334155;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <tr>
            <td style="background: linear-gradient(135deg, #1a73e8, #0d47a1); padding: 32px 40px; text-align: center;">
                <div style="font-size: 42px; margin-bottom: 8px;">' . $headerIcon . '</div>
                <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.3px;">' . $headerTitle . '</h1>
                <p style="color: rgba(255,255,255,0.9); margin: 6px 0 0; font-size: 14px;">' . $headerSubtitle . '</p>
            </td>
        </tr>
        <tr>
            <td style="padding: 32px 40px;">
                <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
                    ' . $introGreeting . '
                </p>
                ' . $introText . '
                ' . $credentialsBlock . '
                <div style="text-align: center; margin: 24px 0 16px;">
                    <a href="' . $portalUrlSafe . '" style="display: inline-block; background: linear-gradient(135deg, #1a73e8, #0d47a1); color: #ffffff; text-decoration: none; padding: 13px 30px; border-radius: 8px; font-size: 15px; font-weight: 600; letter-spacing: 0.3px; box-shadow: 0 4px 12px rgba(26,115,232,0.25);">
                        &#x1F510; Ingresar al Portal del Paciente
                    </a>
                </div>
                <p style="text-align: center; margin: 0 0 20px; font-size: 13px;">
                    <a href="' . $portalUrlSafe . '" style="color: #1a73e8; text-decoration: underline; word-break: break-all;">' . $portalUrlSafe . '</a>
                </p>
                ' . $appointmentSummaryBlock . '
                <p style="color: #475569; font-size: 14px; line-height: 1.6; margin: 20px 0 24px;">
                    Si tienes alguna consulta o necesitas asistencia, puedes comunicarte con nuestro equipo de atenci&oacute;n.
                </p>
                <div style="border-top: 1px solid #e2e8f0; padding-top: 18px; margin-top: 20px;">
                    <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 0 0 4px;">Saludos cordiales,</p>
                    <p style="color: #1e293b; font-size: 15px; font-weight: 700; margin: 0 0 4px;">' . $clinicName . '</p>
                    <p style="color: #64748b; font-size: 13px; margin: 0;">' . $contactLine . '</p>
                </div>
            </td>
        </tr>
        ' . $footerHtml . '
    </table>
</body>
</html>';
    }
}

