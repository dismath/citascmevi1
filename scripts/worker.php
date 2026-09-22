<?php
/**
 * Background Worker — FASE 4: Infraestructura y Futuro
 *
 * Procesa trabajos pendientes de la tabla `background_jobs` de forma asíncrona,
 * sin afectar el tiempo de respuesta del usuario en la web.
 *
 * IMPORTANTE: Este script está diseñado exclusivamente para ejecución en CLI (línea
 * de comandos). NO debe ser accesible desde el navegador web.
 *
 * Configuración en Windows (Tareas Programadas — cada minuto):
 *   php C:\xampp\htdocs\scripts\worker.php >> C:\xampp\htdocs\storage\logs\worker.log 2>&1
 *
 * Configuración en Linux/Mac (cron — cada minuto):
 *   * * * * * php /ruta/htdocs/scripts/worker.php >> /ruta/htdocs/storage/logs/worker.log 2>&1
 *
 * Tipos de jobs soportados:
 *   - 'send_email'     → Envío de correos electrónicos
 *   - 'generate_pdf'   → Generación de reportes PDF pesados
 *   - 'cleanup_cache'  → Limpieza de archivos de caché expirados
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Guardia CLI: Impedir ejecución desde navegador web
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso denegado: este script solo puede ejecutarse desde la línea de comandos.');
}

// ---------------------------------------------------------------------------
// Bootstrap del entorno (sin iniciar sesión ni enrutador web)
// ---------------------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Configurar zona horaria y reporte de errores para CLI
$appConfig = require CONFIG_PATH . '/app.php';
date_default_timezone_set($appConfig['timezone']);

// En worker: siempre mostrar errores para facilitar debugging desde logs
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

$logDir = ROOT_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
ini_set('error_log', $logDir . '/worker_errors.log');

// Autoloader PSR-4 (igual que en index.php pero sin Router ni Session)
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
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

// Inicializar conexión a base de datos
$dbConfig = require CONFIG_PATH . '/database.php';
require_once APP_PATH . '/Helpers/Database.php';
\App\Helpers\Database::init($dbConfig);

// ---------------------------------------------------------------------------
// Procesador de Trabajos
// ---------------------------------------------------------------------------
use App\Helpers\Database;

$db    = Database::getInstance();
$queue = $argv[1] ?? 'default'; // Permite especificar la cola como argumento: php worker.php emails

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Worker iniciado — Cola: '{$queue}'\n";

// Número máximo de jobs a procesar por invocación (evitar timeouts)
$maxJobs    = (int)($argv[2] ?? 10);
$processed  = 0;
$now        = time();

// ---------------------------------------------------------------------------
// Bucle principal: procesar hasta $maxJobs trabajos pendientes
// ---------------------------------------------------------------------------
while ($processed < $maxJobs) {
    $db->beginTransaction();

    try {
        // Seleccionar y bloquear el siguiente trabajo disponible (FOR UPDATE)
        $job = $db->fetch(
            "SELECT * FROM background_jobs
             WHERE queue = ?
               AND reserved_at IS NULL
               AND available_at <= ?
             ORDER BY id ASC
             LIMIT 1 FOR UPDATE",
            [$queue, $now]
        );

        if (!$job) {
            $db->commit();
            echo "No hay trabajos pendientes en la cola '{$queue}'.\n";
            break; // Salir del bucle, no hay más trabajos
        }

        // Reservar el trabajo para que otro proceso no lo tome
        $db->execute(
            "UPDATE background_jobs SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?",
            [$now, $job['id']]
        );
        $db->commit();

    } catch (\Exception $e) {
        $db->rollback();
        echo "[ERROR] No se pudo reservar el job: " . $e->getMessage() . "\n";
        break;
    }

    // ---------------------------------------------------------------------------
    // Procesar el Job (fuera de la transacción para no bloquear la tabla)
    // ---------------------------------------------------------------------------
    $payload = json_decode($job['payload'], true);
    $jobType = $payload['type'] ?? 'unknown';
    echo "[" . date('H:i:s') . "] Procesando Job #{$job['id']} (tipo: {$jobType})...\n";

    $success = false;
    $errorMsg = '';

    try {
        switch ($jobType) {

            // ---------------------------------------------------------------
            // Envío de Correo Electrónico
            // ---------------------------------------------------------------
            case 'send_email':
                if (empty($payload['to']) || empty($payload['subject']) || empty($payload['body'])) {
                    throw new \InvalidArgumentException("Payload de email incompleto.");
                }
                $sent = \App\Helpers\Mailer::send(
                    $payload['to'],
                    $payload['subject'],
                    $payload['body']
                );
                if (!$sent) {
                    throw new \RuntimeException("Mailer retornó false.");
                }
                echo "  → Email enviado a: {$payload['to']}\n";
                $success = true;
                break;

            // ---------------------------------------------------------------
            // Generación de PDF (para reportes pesados en background)
            // ---------------------------------------------------------------
            case 'generate_pdf':
                // Implementación futura: encolar generación de reportes PDF mensuales, etc.
                echo "  → PDF generation placeholder para: " . ($payload['report'] ?? 'N/A') . "\n";
                $success = true;
                break;

            // ---------------------------------------------------------------
            // Limpieza de caché expirado
            // ---------------------------------------------------------------
            case 'cleanup_cache':
                $cacheDir = ROOT_PATH . '/storage/cache';
                if (is_dir($cacheDir)) {
                    $count = 0;
                    foreach (glob($cacheDir . '/*.cache') as $file) {
                        $data = @json_decode(file_get_contents($file), true);
                        if ($data && $data['expires'] > 0 && $data['expires'] < time()) {
                            unlink($file);
                            $count++;
                        }
                    }
                    echo "  → Cache cleanup: {$count} archivo(s) expirado(s) eliminados.\n";
                }
                $success = true;
                break;

            default:
                throw new \UnexpectedValueException("Tipo de job desconocido: '{$jobType}'.");
        }

    } catch (\Exception $e) {
        $success  = false;
        $errorMsg = $e->getMessage();
        echo "[ERROR] Job #{$job['id']} falló: {$errorMsg}\n";
    }

    // ---------------------------------------------------------------------------
    // Post-procesamiento: borrar si exitoso, liberar si falla (para reintento)
    // ---------------------------------------------------------------------------
    try {
        if ($success) {
            $db->execute("DELETE FROM background_jobs WHERE id = ?", [$job['id']]);
            echo "  ✓ Job #{$job['id']} completado y eliminado.\n";
        } else {
            $maxAttempts = 3;
            if ((int)$job['attempts'] >= $maxAttempts) {
                // Demasiados intentos: marcar como fallido (moverlo a una "dead letter queue" lógica)
                $db->execute(
                    "UPDATE background_jobs SET reserved_at = NULL, queue = 'failed' WHERE id = ?",
                    [$job['id']]
                );
                echo "  ✗ Job #{$job['id']} movido a la cola 'failed' (máximo de intentos alcanzado).\n";
            } else {
                // Liberar el job y retrasar el siguiente intento (backoff exponencial: 2^attempts minutos)
                $backoffSeconds = (int)pow(2, $job['attempts']) * 60;
                $db->execute(
                    "UPDATE background_jobs SET reserved_at = NULL, available_at = ? WHERE id = ?",
                    [time() + $backoffSeconds, $job['id']]
                );
                echo "  ✗ Job #{$job['id']} liberado. Próximo intento en {$backoffSeconds}s.\n";
            }
        }
    } catch (\Exception $e) {
        error_log("[Worker] Error post-procesamiento Job #{$job['id']}: " . $e->getMessage());
    }

    $processed++;
}

$elapsed = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3);
echo "[" . date('H:i:s') . "] Worker finalizado — {$processed} job(s) procesados en {$elapsed}s.\n";
exit(0);
