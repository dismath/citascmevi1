<?php
namespace App\Helpers;

class FirebaseService
{
    // En producción (cPanel), configurar FIREBASE_CREDENTIALS con la ruta absoluta fuera de public_html
    private static ?string $serviceAccountPath = null;

    private static function getCredentialsPath(): string
    {
        $custom = \App\Helpers\Env::get('FIREBASE_CREDENTIALS');
        if (!empty($custom)) {
            if (file_exists($custom)) {
                return $custom;
            }
            $rootRelative = dirname(__DIR__, 2) . '/' . ltrim($custom, '/\\');
            if (file_exists($rootRelative)) {
                return $rootRelative;
            }
        }

        $securePath = dirname(__DIR__, 2) . '/storage/credentials/google-service-account.json';
        if (file_exists($securePath)) {
            return $securePath;
        }

        return dirname(__DIR__, 2) . '/google-service-account.json';
    }

    /**
     * Obtiene el token de acceso OAuth2 para usar la API v1 de Firebase
     */
    private static function getAccessToken(): ?string
    {
        $path = self::getCredentialsPath();
        if (!file_exists($path)) {
            error_log("Firebase Error: Archivo de credenciales no encontrado en " . $path);
            return null;
        }
        $credentials = json_decode(file_get_contents($path), true);
        if (!$credentials) return null;

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        
        $now = time();
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials['token_uri'],
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = '';
        openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $credentials['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response, true);
        return $json['access_token'] ?? null;
    }

    /**
     * Envía una notificación Push a uno o varios FCM tokens
     */
    public static function sendPushNotification(string $fcmToken, string $title, string $body, array $data = []): bool
    {
        $accessToken = self::getAccessToken();
        if (!$accessToken) return false;

        $path = self::getCredentialsPath();
        $credentials = json_decode(file_get_contents($path), true);
        $projectId = $credentials['project_id'];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        // Convertir todos los valores de data a string (FCM v1 requiere strings estrictamente)
        $stringData = [];
        foreach ($data as $key => $value) {
            $stringData[$key] = (string)$value;
        }

        $message = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => empty($stringData) ? (object)[] : $stringData,
                'android' => [
                    'priority' => 'high'
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default'
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode !== 200) {
            error_log("Firebase FCM Error: " . $response);
            return false;
        }

        return true;
    }
}
