<?php
/**
 * Front Controller - Entry point for all requests
 */
declare(strict_types=1);

// Detectar si es una petición a la API móvil
$requestUrl = $_GET['url'] ?? $_SERVER['REQUEST_URI'] ?? '';
$isApiRequest = (strpos($requestUrl, 'api/v1') !== false);

// ---------------------------------------------------------------------------
// FIX-SEC-08: HTTP Security Headers (Aplicadas a todas las respuestas)
// ---------------------------------------------------------------------------
if (!headers_sent()) {
    @set_time_limit(30);
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net https://cdn.datatables.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self';");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    header_remove('X-Powered-By');
    header_remove('Server');
}

// ---------------------------------------------------------------------------
// Mitigación DoS y Escáneres Automatizados
// ---------------------------------------------------------------------------
if (strlen($requestUrl) > 2048) {
    http_response_code(414);
    header('Content-Type: text/plain; charset=utf-8');
    echo '414 URI Too Long';
    exit;
}

$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($ua !== '' && preg_match('/(sqlmap|nikto|masscan|wpscan|acunetix|havij)/i', $ua)) {
    http_response_code(403);
    exit;
}

// Define base paths first
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', __DIR__);
define('UPLOAD_PATH', __DIR__ . '/uploads');

// Load Env variables
require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(ROOT_PATH . '/.env');

// ---------------------------------------------------------------------------
// FIX-SEC-01: Error reporting controlado por entorno
// ---------------------------------------------------------------------------
$appEnv = \App\Helpers\Env::get('APP_ENV', 'production');

if ($appEnv === 'development' && !$isApiRequest) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    // Producción o API: nunca mostrar errores como HTML
    error_reporting($appEnv === 'development' ? E_ALL : 0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
}

// Load configurations
$appConfig = require CONFIG_PATH . '/app.php';
$dbConfig  = require CONFIG_PATH . '/database.php';

// Set timezone
date_default_timezone_set($appConfig['timezone']);

// Autoload classes
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

// Start secure session using our helper
\App\Helpers\Session::start();


// Initialize Database connection
require_once APP_PATH . '/Helpers/Database.php';
try {
    App\Helpers\Database::init($dbConfig);
} catch (\Throwable $e) {
    error_log("Database Init Failed: " . $e->getMessage());

    if ($isApiRequest) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error de conexión a la base de datos',
            'error'   => ($appEnv === 'development') ? $e->getMessage() : 'Servicio temporalmente no disponible'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(503);
    if ($appEnv === 'development') {
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Error de Base de Datos</title>";
        echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f8fafc;color:#1e293b;padding:40px;display:flex;justify-content:center;}";
        echo ".card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:30px;max-width:650px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);}";
        echo "h2{color:#e11d48;margin-top:0;} pre{background:#f1f5f9;padding:12px;border-radius:6px;overflow-x:auto;font-size:14px;color:#0f172a;}";
        echo "ol{line-height:1.8;} .badge{background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:bold;}";
        echo "</style></head><body>";
        echo "<div class='card'>";
        echo "<h2>⚠️ Error de Conexión a la Base de Datos</h2>";
        echo "<p><span class='badge'>HTTP 503 Service Unavailable</span></p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<h3>¿Cómo solucionarlo?</h3>";
        echo "<ol>";
        echo "<li>Abra el <strong>Panel de Control de XAMPP</strong>.</li>";
        echo "<li>En la fila de <strong>MySQL</strong>, haga clic en el botón <strong>Start</strong>.</li>";
        echo "<li>Verifique que el puerto sea el <strong>3306</strong> (o ajústelo en su archivo <code>.env</code> en <code>DB_PORT</code>).</li>";
        echo "</ol>";
        echo "</div></body></html>";
        exit;
    }

    echo "<h1>Servicio temporalmente no disponible</h1><p>No se pudo conectar a la base de datos. Intente nuevamente en unos minutos.</p>";
    exit;
}

// Load and run Router
require_once APP_PATH . '/Helpers/Router.php';
$router = new App\Helpers\Router($appConfig['base_url']);

// Include routes
require_once CONFIG_PATH . '/routes.php';

// Dispatch the request
$router->dispatch();
