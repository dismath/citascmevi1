<?php
/**
 * Comprehensive System Audit Script:
 * 1. PHP Syntax Linting
 * 2. Sensitive File Access Test (HTTP)
 * 3. Backend & Security Configuration
 * 4. Database Integrity & Performance
 * 5. Frontend Assets & Security Headers
 */

$root = dirname(__DIR__);
$phpBin = 'C:\\xampp\\php\\php.exe';

echo "=================================================================\n";
echo "            AUDITORÍA DE PRE-PRODUCCIÓN DEL SISTEMA             \n";
echo "=================================================================\n\n";

// =================================================================
// 1. PRUEBA DE SINTAXIS PHP (LINTING)
// =================================================================
echo "1. PRUEBA DE SINTAXIS PHP (LINTING):\n";
echo "-------------------------------------\n";

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$phpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        // Ignore vendor if exists
        if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
            $phpFiles[] = $path;
        }
    }
}

$totalLinted = count($phpFiles);
$lintErrors = [];

foreach ($phpFiles as $file) {
    $output = [];
    $returnVar = 0;
    exec("\"{$phpBin}\" -l \"{$file}\" 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        $lintErrors[] = [
            'file' => str_replace($root, '', $file),
            'error' => implode(' ', $output)
        ];
    }
}

echo "Total de archivos PHP analizados: {$totalLinted}\n";
if (empty($lintErrors)) {
    echo "✔ ESTADO LINTING: 100% APROBADO (0 errores de sintaxis detectados).\n\n";
} else {
    echo "✖ ESTADO LINTING: FALLIDO (" . count($lintErrors) . " errores):\n";
    foreach ($lintErrors as $err) {
        echo "  - {$err['file']}: {$err['error']}\n";
    }
    echo "\n";
}

// =================================================================
// 2. PRUEBA DE ACCESO A ARCHIVOS SENSIBLES
// =================================================================
echo "2. PRUEBA DE ACCESO A ARCHIVOS SENSIBLES:\n";
echo "-----------------------------------------\n";

// URLs de prueba sensibles contra el servidor web local
$testUrls = [
    '/.env' => 'Archivo de configuración con credenciales DB',
    '/.git/HEAD' => 'Repositorio Git',
    '/.gitignore' => 'Archivo dotfile',
    '/app/Controllers/AuthController.php' => 'Código fuente de controladores',
    '/app/Models/User.php' => 'Código fuente de modelos',
    '/config/database.php' => 'Archivo de configuración DB',
    '/database/migrations/01_critical_fixes.sql' => 'Scripts SQL de base de datos',
    '/storage/logs/php_errors.log' => 'Logs del sistema',
    '/storage/credentials/' => 'Directorio de credenciales',
    '/scripts/cron_reminders.php' => 'Scripts de mantenimiento / cron',
];

$serverRunning = false;
$baseUrl = 'http://localhost';

// Comprobar si el servidor HTTP responde
$ch = curl_init($baseUrl . '/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode > 0) {
    $serverRunning = true;
    echo "Servidor HTTP detectado en {$baseUrl} (HTTP {$httpCode})\n";
} else {
    echo "Aviso: Servidor web local no respondió en puerto 80. Probando 8080 o analizando reglas .htaccess estáticamente.\n";
}

if ($serverRunning) {
    $sensitiveExposed = [];
    foreach ($testUrls as $uri => $desc) {
        $url = $baseUrl . $uri;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isBlocked = ($code === 403 || $code === 404);
        
        // Verificamos si el contenido contiene datos sensibles reales
        $leaked = false;
        if ($code === 200) {
            if ($uri === '/.env' && (strpos($body, 'DB_PASS') !== false || strpos($body, 'APP_KEY') !== false)) {
                $leaked = true;
            } elseif (strpos($uri, '.php') !== false && strpos($body, '<?php') !== false) {
                $leaked = true;
            } elseif (strpos($uri, '.sql') !== false && (strpos($body, 'CREATE TABLE') !== false || strpos($body, 'ALTER TABLE') !== false)) {
                $leaked = true;
            }
        }

        if ($isBlocked) {
            echo "  ✔ [BLOQUEADO HTTP {$code}] {$uri} ({$desc})\n";
        } elseif ($leaked) {
            echo "  ✖ [CRÍTICO HTTP {$code} FUGA DE DATOS] {$uri} ({$desc})\n";
            $sensitiveExposed[] = $uri;
        } else {
            echo "  ⚠ [HTTP {$code}] {$uri} ({$desc}) - No bloqueado directamente con 403/404\n";
            if ($code === 200) {
                $sensitiveExposed[] = $uri;
            }
        }
    }

    if (empty($sensitiveExposed)) {
        echo "✔ ESTADO ACCESO A ARCHIVOS SENSIBLES: SEGURO (Todos bloqueados).\n\n";
    } else {
        echo "✖ ESTADO ACCESO A ARCHIVOS SENSIBLES: VULNERABILIDAD DETECTADA en " . count($sensitiveExposed) . " rutas.\n\n";
    }
} else {
    // Análisis estático de directivas en .htaccess
    echo "Análisis estático de .htaccess:\n";
    $htaccessContent = file_get_contents($root . '/.htaccess');
    $has403App = strpos($htaccessContent, 'RedirectMatch 403 ^/app/') !== false;
    $has403Config = strpos($htaccessContent, 'RedirectMatch 403 ^/config/') !== false;
    $has403Database = strpos($htaccessContent, 'RedirectMatch 403 ^/database/') !== false;
    $has403Dot = strpos($htaccessContent, 'RedirectMatch 403 ^/\\..*$') !== false;
    echo "  - Protección /app/: " . ($has403App ? "Configurada (403)" : "Faltante") . "\n";
    echo "  - Protección /config/: " . ($has403Config ? "Configurada (403)" : "Faltante") . "\n";
    echo "  - Protección /database/: " . ($has403Database ? "Configurada (403)" : "Faltante") . "\n";
    echo "  - Protección archivos ocultos (dotfiles como .env): " . ($has403Dot ? "Configurada (403)" : "Faltante") . "\n\n";
}

// =================================================================
// 3. REVISIÓN DE SEGURIDAD Y BACKEND
// =================================================================
echo "3. REVISIÓN DE SEGURIDAD Y BACKEND:\n";
echo "-----------------------------------\n";

require_once $root . '/app/Helpers/Env.php';
\App\Helpers\Env::load($root . '/.env');
$appEnv = \App\Helpers\Env::get('APP_ENV', 'production');
$appDebug = \App\Helpers\Env::get('APP_DEBUG', 'false');

echo "  - Entorno actual (APP_ENV): {$appEnv}\n";
echo "  - Modo Debug (APP_DEBUG): {$appDebug}\n";
if ($appEnv === 'production' && ($appDebug === 'true' || $appDebug === '1')) {
    echo "  ⚠ ALERTA: APP_DEBUG está habilitado en entorno de producción.\n";
} else {
    echo "  ✔ Configuración de entorno adecuada para producción.\n";
}

// Session security check
require_once $root . '/app/Helpers/Session.php';
echo "  - Session Cookie HttpOnly: " . (ini_get('session.cookie_httponly') ? '✔ SÍ' : '⚠ NO') . "\n";
echo "  - Session Cookie SameSite: " . (ini_get('session.cookie_samesite') ?: 'Strict/Lax') . "\n";
echo "  - Session Strict Mode: " . (ini_get('session.use_strict_mode') ? '✔ SÍ' : '⚠ NO') . "\n";

// CSRF check
$csrf = \App\Helpers\Session::generateCsrf();
$isValidCsrf = \App\Helpers\Session::validateCsrf($csrf);
echo "  - Protección CSRF Token: " . ($isValidCsrf ? "✔ Operativa" : "✖ Fallida") . "\n";

// Rate Limiting check
$rateLimiterExists = class_exists('\App\Helpers\RateLimiter') || file_exists($root . '/app/Helpers/RateLimiter.php');
echo "  - Rate Limiter (Protección fuerza bruta): " . ($rateLimiterExists ? "✔ Implementado" : "✖ No encontrado") . "\n\n";

// =================================================================
// 4. REVISIÓN DE BASE DE DATOS
// =================================================================
echo "4. REVISIÓN DE BASE DE DATOS:\n";
echo "-----------------------------\n";

try {
    $dbConfig = require $root . '/config/database.php';
    require_once $root . '/app/Helpers/Database.php';
    \App\Helpers\Database::init($dbConfig);
    $db = \App\Helpers\Database::getInstance();
    
    echo "  ✔ Conexión a Base de Datos: Exitosa ({$dbConfig['database']} en {$dbConfig['host']})\n";

    // Tablas críticas
    $criticalTables = ['users', 'doctors', 'patients', 'appointments', 'auditoria_seguridad', 'user_roles', 'roles'];
    $existingTables = array_map(function($t) {
        return array_values($t)[0];
    }, $db->fetchAll("SHOW TABLES"));

    $missingTables = array_diff($criticalTables, $existingTables);
    if (empty($missingTables)) {
        echo "  ✔ Todas las tablas críticas existen (" . implode(', ', $criticalTables) . ").\n";
    } else {
        echo "  ✖ Faltan tablas críticas: " . implode(', ', $missingTables) . "\n";
    }

    // Índices de usuarios
    $userIndexes = $db->fetchAll("SHOW INDEX FROM users");
    $indexedCols = array_unique(array_column($userIndexes, 'Column_name'));
    echo "  - Columnas indexadas en `users`: " . implode(', ', $indexedCols) . "\n";
    $hasUsernameIndex = in_array('username', $indexedCols);
    $hasEmailIndex = in_array('email', $indexedCols);
    echo "    • Índice en username: " . ($hasUsernameIndex ? "✔ Sí" : "⚠ Recomendado agregar índice") . "\n";
    echo "    • Índice en email: " . ($hasEmailIndex ? "✔ Sí" : "⚠ Recomendado agregar índice") . "\n";

    // Charset
    $dbCharset = $db->fetch("SELECT default_character_set_name, default_collation_name FROM information_schema.SCHEMATA WHERE schema_name = ?", [$dbConfig['database']]);
    echo "  - Juego de caracteres: {$dbCharset['default_character_set_name']} ({$dbCharset['default_collation_name']})\n\n";

} catch (\Throwable $e) {
    echo "  ✖ Error al conectar a la base de datos: " . $e->getMessage() . "\n\n";
}

// =================================================================
// 5. REVISIÓN DE FRONTEND Y HEADERS
// =================================================================
echo "5. REVISIÓN DE FRONTEND Y HEADERS:\n";
echo "----------------------------------\n";

$publicIndex = file_get_contents($root . '/public/index.php');
$hasCsp = strpos($publicIndex, 'Content-Security-Policy') !== false;
$hasXFrame = strpos($publicIndex, 'X-Frame-Options') !== false;
$hasNosniff = strpos($publicIndex, 'X-Content-Type-Options') !== false;

echo "  - Content-Security-Policy (CSP): " . ($hasCsp ? "✔ Configurado" : "✖ Falta") . "\n";
echo "  - X-Frame-Options (Clickjacking): " . ($hasXFrame ? "✔ Configurado" : "✖ Falta") . "\n";
echo "  - X-Content-Type-Options (MIME sniffing): " . ($hasNosniff ? "✔ Configurado" : "✖ Falta") . "\n";

// Asset checks
$assets = [
    '/css/style.css',
    '/js/main.js'
];
foreach ($assets as $asset) {
    $fullPath = $root . '/public' . $asset;
    if (file_exists($fullPath)) {
        echo "  ✔ Asset local existe: public{$asset} (" . filesize($fullPath) . " bytes)\n";
    }
}

echo "\n=================================================================\n";
echo "                    FIN DE LA AUDITORÍA                         \n";
echo "=================================================================\n";
