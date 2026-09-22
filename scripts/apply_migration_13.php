<?php
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');

require_once APP_PATH . '/Helpers/Env.php';
\App\Helpers\Env::load(ROOT_PATH . '/.env');

// Autoload classes
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = APP_PATH . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

$dbConfig = require CONFIG_PATH . '/database.php';
\App\Helpers\Database::init($dbConfig);
$db = \App\Helpers\Database::getInstance();
$conn = $db->getConnection();

echo "Iniciando corrección de foreign keys y tablas...\n";

function constraintExists($conn, $table, $constraintName) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
    $stmt->execute([$table, $constraintName]);
    return (int)$stmt->fetchColumn() > 0;
}

try {
    // 1. medical_notes: eliminar FKs antiguas si existen
    if (constraintExists($conn, 'medical_notes', 'medical_notes_ibfk_2')) {
        echo "Eliminando medical_notes_ibfk_2 (apuntaba erróneamente a users)...\n";
        $conn->exec("ALTER TABLE `medical_notes` DROP FOREIGN KEY `medical_notes_ibfk_2`");
    }
    if (constraintExists($conn, 'medical_notes', 'medical_notes_ibfk_3')) {
        echo "Eliminando medical_notes_ibfk_3 (apuntaba erróneamente a users)...\n";
        $conn->exec("ALTER TABLE `medical_notes` DROP FOREIGN KEY `medical_notes_ibfk_3`");
    }

    // Agregar FKs correctas
    if (!constraintExists($conn, 'medical_notes', 'fk_medical_notes_patient')) {
        echo "Agregando fk_medical_notes_patient (hacia patients.id)...\n";
        $conn->exec("ALTER TABLE `medical_notes` ADD CONSTRAINT `fk_medical_notes_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    }
    if (!constraintExists($conn, 'medical_notes', 'fk_medical_notes_doctor')) {
        echo "Agregando fk_medical_notes_doctor (hacia doctors.id)...\n";
        $conn->exec("ALTER TABLE `medical_notes` ADD CONSTRAINT `fk_medical_notes_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE");
    }

    // 2. medical_history: eliminar FK antigua si apunta a users
    if (constraintExists($conn, 'medical_history', 'medical_history_ibfk_1')) {
        echo "Eliminando medical_history_ibfk_1 (apuntaba a users)...\n";
        $conn->exec("ALTER TABLE `medical_history` DROP FOREIGN KEY `medical_history_ibfk_1`");
    }
    if (!constraintExists($conn, 'medical_history', 'fk_medical_history_patient')) {
        echo "Agregando fk_medical_history_patient (hacia patients.id)...\n";
        $conn->exec("ALTER TABLE `medical_history` ADD CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE");
    }

    // 3. Crear tabla audit_log si no existe
    $conn->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `action` VARCHAR(50) NOT NULL,
        `entity` VARCHAR(50) NOT NULL,
        `entity_id` INT NULL,
        `old_value` JSON NULL,
        `new_value` JSON NULL,
        `ip_address` VARCHAR(45) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_audit_entity` (`entity`, `entity_id`),
        INDEX `idx_audit_user` (`user_id`),
        INDEX `idx_audit_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Tabla audit_log verificada/creada correctamente.\n";

    // 4. Crear tabla background_jobs si no existe
    $conn->exec("CREATE TABLE IF NOT EXISTS `background_jobs` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `queue` VARCHAR(50) NOT NULL DEFAULT 'default',
        `payload` LONGTEXT NOT NULL,
        `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `reserved_at` INT UNSIGNED NULL,
        `available_at` INT UNSIGNED NOT NULL,
        `created_at` INT UNSIGNED NOT NULL,
        INDEX `idx_jobs_queue` (`queue`, `reserved_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Tabla background_jobs verificada/creada correctamente.\n";

    echo "¡Migración completada exitosamente!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
