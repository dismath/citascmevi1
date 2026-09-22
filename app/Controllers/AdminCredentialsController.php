<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\HashId;
use App\Models\User;
use App\Models\SecurityAudit;

class AdminCredentialsController
{
    private User $userModel;

    public function __construct()
    {
        Auth::require();
        $this->userModel = new User();
    }

    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    /**
     * Obtiene la información de credenciales (nombre de usuario, email, etc.)
     */
    public function getCredentials(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $type = trim($_POST['type'] ?? '');
        $rawId = $_POST['id'] ?? '';
        $id = is_numeric($rawId) ? (int)$rawId : (HashId::decode((string)$rawId) ?? 0);

        if (!in_array($type, ['doctor', 'patient']) || $id <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Parámetros inválidos.'], 400);
        }

        // Si es médico, requiere permiso de doctores; si es paciente, permiso de pacientes
        if ($type === 'doctor' && !Auth::hasRole('admin') && !Auth::hasPermission('doctors_update')) {
            $this->sendJsonResponse(['success' => false, 'error' => 'No dispone de privilegios para gestionar credenciales médicas.'], 403);
        }
        if ($type === 'patient' && !Auth::hasRole('admin') && !Auth::hasPermission('patients_update') && !Auth::hasPermission('users_update')) {
            $this->sendJsonResponse(['success' => false, 'error' => 'No dispone de privilegios para gestionar credenciales de pacientes.'], 403);
        }

        try {
            $credentials = $this->userModel->getOrCreateCredentialsForEntity($type, $id);
            $this->sendJsonResponse([
                'success' => true,
                'data' => $credentials
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminCredentialsController::getCredentials] ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Genera una nueva contraseña para la entidad y la guarda cifrada.
     */
    public function generatePassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonResponse(['success' => false, 'error' => 'Método no permitido.'], 405);
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Session::validateCsrf($csrfToken)) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Token de seguridad inválido o expirado.'], 403);
        }

        $type = trim($_POST['type'] ?? '');
        $rawId = $_POST['id'] ?? '';
        $id = is_numeric($rawId) ? (int)$rawId : (HashId::decode((string)$rawId) ?? 0);

        if (!in_array($type, ['doctor', 'patient']) || $id <= 0) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Parámetros inválidos.'], 400);
        }

        if ($type === 'doctor' && !Auth::hasRole('admin') && !Auth::hasPermission('doctors_update')) {
            $this->sendJsonResponse(['success' => false, 'error' => 'No dispone de privilegios para generar contraseñas médicas.'], 403);
        }
        if ($type === 'patient' && !Auth::hasRole('admin') && !Auth::hasPermission('patients_update') && !Auth::hasPermission('users_update')) {
            $this->sendJsonResponse(['success' => false, 'error' => 'No dispone de privilegios para generar contraseñas de pacientes.'], 403);
        }

        try {
            // Asegurarse de tener el usuario vinculado y su username
            $entityCreds = $this->userModel->getOrCreateCredentialsForEntity($type, $id);
            $userId = (int)$entityCreds['user_id'];

            // Generar nueva contraseña y actualizar
            $newCreds = $this->userModel->generateAndSetNewPassword($userId);

            // Registrar en auditoría de seguridad
            SecurityAudit::log(
                $userId,
                SecurityAudit::EVENTO_PASSWORD_CAMBIADO,
                SecurityAudit::RESULTADO_EXITOSO,
                [
                    'admin_id'   => Session::get('user_id'),
                    'target_role'=> $type,
                    'action'     => 'admin_regenerate_password'
                ]
            );

            $this->sendJsonResponse([
                'success' => true,
                'message' => '¡Nueva contraseña generada y activada exitosamente!',
                'data' => [
                    'name'         => $entityCreds['name'],
                    'type'         => $type,
                    'username'     => $newCreds['username'],
                    'email'        => $newCreds['email'],
                    'new_password' => $newCreds['new_password']
                ]
            ]);
        } catch (\Throwable $e) {
            error_log('[AdminCredentialsController::generatePassword] ' . $e->getMessage());
            $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
