<?php
namespace App\Helpers;

/**
 * View Helper
 *
 * Centraliza el renderizado de vistas, layouts, respuestas JSON y redirecciones.
 * FASE 4: Incluye el método asset() con soporte automático de CDN.
 */
class View
{
    /**
     * Render a view file with optional layout
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $appConfig = require CONFIG_PATH . '/app.php';
        $baseUrl   = $appConfig['base_url'];
        $csrfToken = Session::generateCsrf();

        // Cargar configuración de empresa (logo y nombre) disponible globalmente en vistas y layouts
        $companyLogo = '';
        $companyName = $appConfig['name'] ?? 'Portal Salud';
        try {
            $settingsModel = new \App\Models\SystemSetting();
            $companyLogo = $settingsModel->get('company_logo', '') ?? '';
            $storedName = $settingsModel->get('company_name', '');
            if (!empty($storedName)) {
                $companyName = $storedName;
            }
        } catch (\Throwable $e) {
            // Manejo silencioso en caso de inicialización o migraciones
        }

        extract($data, EXTR_SKIP);

        // Capture the view content
        ob_start();
        $viewFile = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<p>View not found: {$view}</p>";
        }
        $content = ob_get_clean();

        // Render inside layout
        $layoutFile = APP_PATH . '/Views/layouts/' . $layout . '.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Render a view without layout (for AJAX, modals, etc.)
     */
    public static function partial(string $view, array $data = []): void
    {
        $baseUrl = (require CONFIG_PATH . '/app.php')['base_url'];
        extract($data, EXTR_SKIP);
        $viewFile = APP_PATH . '/Views/' . str_replace('.', '/', $view) . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        }
    }

    /**
     * FASE 4: Genera la URL completa de un asset estático.
     *
     * Si la variable `cdn_url` está configurada en config/app.php, el asset se sirve
     * desde el CDN (ideal para CloudFront, Cloudflare, etc.).
     * Si no hay CDN configurado, se usa la base_url del servidor local.
     *
     * Uso en vistas:
     *   <link rel="stylesheet" href="<?= View::asset('/css/app.css') ?>">
     *   <script src="<?= View::asset('/js/main.js') ?>"></script>
     *   <img src="<?= View::asset('/images/logo.png') ?>">
     *
     * @param string $path Ruta relativa del asset (debe empezar con '/').
     * @return string URL completa del asset.
     */
    public static function asset(string $path): string
    {
        $appConfig = require CONFIG_PATH . '/app.php';

        // Normalizar: asegurar que el path empieza con '/'
        $path = '/' . ltrim($path, '/');

        // Si hay CDN configurado, anteponer su URL
        $cdnUrl = rtrim($appConfig['cdn_url'] ?? '', '/');
        if (!empty($cdnUrl)) {
            return $cdnUrl . $path;
        }

        // Sin CDN: servir desde el servidor local
        $baseUrl = rtrim($appConfig['base_url'], '/');
        return $baseUrl . $path;
    }

    /**
     * Send JSON response
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirect to URL
     */
    public static function redirect(string $url): void
    {
        $baseUrl = (require CONFIG_PATH . '/app.php')['base_url'];
        header("Location: {$baseUrl}{$url}");
        exit;
    }
}

