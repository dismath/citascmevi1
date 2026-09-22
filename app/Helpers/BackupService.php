<?php
namespace App\Helpers;

/**
 * BackupService
 * Genera respaldos comprimidos (.sql.gz) de la base de datos MySQL
 * de forma ultra ligera y segura, con almacenamiento protegido.
 */
class BackupService
{
    private static function initTimezone(): void
    {
        date_default_timezone_set('America/Guayaquil');
    }

    private static function getBackupDir(): string
    {
        self::initTimezone();
        $dir = defined('ROOT_PATH') ? ROOT_PATH . '/storage/backups' : dirname(__DIR__, 2) . '/storage/backups';
        self::ensureProtectedDirectory($dir);
        return $dir;
    }

    /**
     * Asegura la existencia del directorio y sus protecciones contra accesos web directos
     */
    public static function ensureProtectedDirectory(?string $dir = null): void
    {
        if ($dir === null) {
            $dir = defined('ROOT_PATH') ? ROOT_PATH . '/storage/backups' : dirname(__DIR__, 2) . '/storage/backups';
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        // 1. Archivo .htaccess para bloquear cualquier petición HTTP
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "# Denegar todo acceso web a los respaldos de la base de datos\n"
                     . "Order Deny,Allow\n"
                     . "Deny from all\n"
                     . "<IfModule mod_authz_core.c>\n"
                     . "    Require all denied\n"
                     . "</IfModule>\n";
            @file_put_contents($htaccess, $content);
        }

        // 2. Archivo index.html silencioso
        $indexHtml = $dir . '/index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>");
        }
    }

    /**
     * Crea un respaldo completo de la base de datos comprimido con GZIP (.sql.gz)
     * 
     * @param string $type 'auto_7pm' o 'manual'
     * @return array
     */
    public static function createBackup(string $type = 'manual'): array
    {
        $backupDir = self::getBackupDir();
        $db = Database::getInstance();
        $pdo = $db->getConnection();

        // Configuración de nombre
        $dateStr = date('Y-m-d_H-i-s');
        $tag = ($type === 'auto_7pm') ? 'auto_19h' : 'manual';
        $filename = "backup_portal_salud_{$dateStr}_{$tag}.sql.gz";
        $filepath = $backupDir . '/' . $filename;

        // Abrir archivo comprimido GZIP en nivel máximo 9
        $gz = gzopen($filepath, 'w9');
        if (!$gz) {
            throw new \RuntimeException("No se pudo crear el archivo de respaldo comprimido en: {$filepath}");
        }

        $now = date('Y-m-d H:i:s');
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();

        // Cabecera del respaldo SQL
        $header  = "-- ==========================================================\n";
        $header .= "-- PORTAL CMEVI - RESPALDO AUTOMÁTICO DE BASE DE DATOS\n";
        $header .= "-- Fecha de Generación: {$now}\n";
        $header .= "-- Tipo de Respaldo: " . ($type === 'auto_7pm' ? 'Programado Diario (7:00 PM)' : 'Manual Administrador') . "\n";
        $header .= "-- Versión MySQL: {$version}\n";
        $header .= "-- Compresión: GZIP Nivel 9 (.sql.gz)\n";
        $header .= "-- ==========================================================\n\n";
        $header .= "SET NAMES utf8mb4;\n";
        $header .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $header .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

        gzwrite($gz, $header);

        // Obtener todas las tablas base
        $tablesStmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = $tablesStmt->fetchAll(\PDO::FETCH_NUM);

        $totalTables = count($tables);
        $totalRows = 0;

        foreach ($tables as $tRow) {
            $table = $tRow[0];

            gzwrite($gz, "\n-- --------------------------------------------------------\n");
            gzwrite($gz, "-- Estructura de tabla: `{$table}`\n");
            gzwrite($gz, "-- --------------------------------------------------------\n");
            gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n");

            // Estructura de la tabla
            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createRow = $createStmt->fetch(\PDO::FETCH_NUM);
            gzwrite($gz, $createRow[1] . ";\n\n");

            // Volcado de datos en lotes
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $tableRowCount = (int)$countStmt->fetchColumn();
            $totalRows += $tableRowCount;

            if ($tableRowCount > 0) {
                gzwrite($gz, "-- Datos de tabla: `{$table}` ({$tableRowCount} registros)\n");

                $selectStmt = $pdo->query("SELECT * FROM `{$table}`");
                $batchSize = 250;
                $batchValues = [];

                while ($row = $selectStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $escapedValues = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $escapedValues[] = 'NULL';
                        } elseif (is_numeric($val) && !preg_match('/^0[0-9]/', (string)$val)) {
                            $escapedValues[] = $val;
                        } else {
                            $escapedValues[] = $pdo->quote($val);
                        }
                    }
                    $batchValues[] = '(' . implode(', ', $escapedValues) . ')';

                    if (count($batchValues) >= $batchSize) {
                        gzwrite($gz, "INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batchValues) . ";\n");
                        $batchValues = [];
                    }
                }

                if (!empty($batchValues)) {
                    gzwrite($gz, "INSERT INTO `{$table}` VALUES \n" . implode(",\n", $batchValues) . ";\n");
                }
                gzwrite($gz, "\n");
            }
        }

        // Pie del respaldo
        $footer  = "\n-- --------------------------------------------------------\n";
        $footer .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $footer .= "-- Fin del respaldo. Generado correctamente.\n";
        gzwrite($gz, $footer);

        gzclose($gz);

        $compressedSize = filesize($filepath);

        // Limpieza automática: mantener solo los últimos 30 respaldos para no saturar espacio
        self::cleanupOldBackups(30);

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size_bytes' => $compressedSize,
            'size_formatted' => self::formatSize($compressedSize),
            'tables_count' => $totalTables,
            'rows_count' => $totalRows,
            'created_at' => $now,
            'type' => $type
        ];
    }

    /**
     * Lista todos los archivos de respaldo disponibles en el directorio protegido
     */
    public static function listBackups(): array
    {
        $backupDir = self::getBackupDir();
        $files = glob($backupDir . '/*.sql*');
        $backups = [];

        if (!$files) {
            return [];
        }

        foreach ($files as $file) {
            $filename = basename($file);
            // Ignorar archivos que no sean respaldos esperados
            if (!preg_match('/^backup_portal_salud_([0-9]{4}-[0-9]{2}-[0-9]{2})_([0-9]{2}-[0-9]{2}-[0-9]{2})_(.+)\.sql(\.gz)?$/', $filename, $m)) {
                continue;
            }

            $date = $m[1];
            $time = str_replace('-', ':', $m[2]);
            $tag  = $m[3];
            $isGzip = (str_ends_with($filename, '.gz'));

            $typeLabel = (str_contains($tag, 'auto') || str_contains($tag, '19h')) 
                ? 'Programado (7:00 PM)' 
                : 'Manual Admin';

            $size = filesize($file);

            $backups[] = [
                'filename'       => $filename,
                'date'           => $date . ' ' . $time,
                'timestamp'      => filemtime($file),
                'size_bytes'     => $size,
                'size_formatted' => self::formatSize($size),
                'is_gzip'        => $isGzip,
                'type'           => $typeLabel,
                'tag'            => $tag
            ];
        }

        // Ordenar por fecha descendente (más reciente primero)
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Obtiene la ruta absoluta segura de un archivo de respaldo validando contra Path Traversal
     */
    public static function getBackupPath(string $filename): ?string
    {
        $safeName = basename($filename);
        if (!preg_match('/^backup_portal_salud_[a-zA-Z0-9_\-\.]+\.sql(\.gz)?$/', $safeName)) {
            return null;
        }

        $backupDir = self::getBackupDir();
        $fullPath = $backupDir . '/' . $safeName;

        return file_exists($fullPath) ? $fullPath : null;
    }

    /**
     * Elimina un archivo de respaldo específico
     */
    public static function deleteBackup(string $filename): bool
    {
        $path = self::getBackupPath($filename);
        if ($path && file_exists($path)) {
            return @unlink($path);
        }
        return false;
    }

    /**
     * Retiene los últimos N respaldos y elimina los más antiguos para ahorrar disco
     */
    public static function cleanupOldBackups(int $keepLast = 30): int
    {
        $backups = self::listBackups();
        if (count($backups) <= $keepLast) {
            return 0;
        }

        $toDelete = array_slice($backups, $keepLast);
        $deletedCount = 0;

        foreach ($toDelete as $b) {
            if (self::deleteBackup($b['filename'])) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Verifica si ya pasaron las 19:00 (7:00 PM) y hoy todavía no se ha generado el respaldo diario.
     * Si no existe, lo crea automáticamente (Failsafe inteligente).
     */
    public static function checkAndRunDailyScheduledBackup(): ?array
    {
        $currentHour = (int)date('H');
        // Si aún no son las 19:00, no corresponde todavía
        if ($currentHour < 19) {
            return null;
        }

        $today = date('Y-m-d');
        $backups = self::listBackups();

        // Verificar si ya existe al menos un respaldo hoy con fecha de hoy
        foreach ($backups as $b) {
            if (str_starts_with($b['date'], $today)) {
                return null; // Ya existe respaldo del día de hoy
            }
        }

        // Si son las 19:00 o más y hoy no hay respaldo, ejecutarlo inmediatamente
        try {
            return self::createBackup('auto_7pm');
        } catch (\Throwable $e) {
            error_log("Error en checkAndRunDailyScheduledBackup: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Formatea tamaño en bytes a KB o MB legibles
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
