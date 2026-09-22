<?php
require __DIR__ . '/../vendor/autoload.php'; // If exists, or just manual
// Actually we can just load the config manually
define('APP_PATH', __DIR__ . '/../app');
define('CONFIG_PATH', __DIR__ . '/../config');

// Try to load env if available, otherwise rely on defaults
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

require APP_PATH . '/Helpers/Database.php';

$config = require CONFIG_PATH . '/database.php';
\App\Helpers\Database::init($config);
$db = \App\Helpers\Database::getInstance();

$sql = file_get_contents(__DIR__ . '/../database/migration_api_tokens.sql');
try {
    // split by ';' and execute
    $statements = explode(';', $sql);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $db->getConnection()->exec($statement);
        }
    }
    echo "Migration completed successfully.\n";
} catch (\Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
