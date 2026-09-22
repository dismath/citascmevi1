<?php
/**
 * Cron Job: Respaldo Automático de Base de Datos Diario (7:00 PM / 19:00)
 *
 * Ejecución vía Programador de Tareas de Windows (Task Scheduler):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\scripts\cron_backup.php
 *
 * Configuración en Windows Task Scheduler:
 *   schtasks /create /tn "PortalCmevi_DB_Backup_7PM" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\scripts\cron_backup.php" /sc daily /st 19:00 /f
 */

declare(strict_types=1);

// Definir rutas base
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Cargar variables de entorno
require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(ROOT_PATH . '/.env');

// Cargar configuración de la aplicación
$appConfig = require CONFIG_PATH . '/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'America/Guayaquil');

// Registrar autoloader
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

// Inicializar conexión a la base de datos
$dbConfig = require CONFIG_PATH . '/database.php';
require_once APP_PATH . '/Helpers/Database.php';
\App\Helpers\Database::init($dbConfig);

// Preparar archivo de log
$logDir = ROOT_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0750, true);
}
$logFile = $logDir . '/backup.log';

$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

echo "[{$timestamp}] Iniciando respaldo automático de base de datos (7:00 PM)...\n";

try {
    $result = \App\Helpers\BackupService::createBackup('auto_7pm');
    
    $elapsed = round(microtime(true) - $startTime, 2);
    $logMsg = "[{$timestamp}] ÉXITO: Respaldo diario completado en {$elapsed}s. Archivo: {$result['filename']} | Tamaño: {$result['size_formatted']} | Tablas: {$result['tables_count']} | Registros: {$result['rows_count']}\n";
    
    file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
    echo $logMsg;
    exit(0);

} catch (\Throwable $e) {
    $elapsed = round(microtime(true) - $startTime, 2);
    $logMsg = "[{$timestamp}] ERROR: Falló el respaldo automático tras {$elapsed}s: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    
    file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
    echo $logMsg;
    exit(1);
}
