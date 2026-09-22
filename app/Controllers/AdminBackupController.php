<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\BackupService;

class AdminBackupController
{
    public function __construct()
    {
        Auth::require();
    }

    /**
     * Listado y panel de control de respaldos
     */
    public function index()
    {
        Auth::requirePermission('backups_read');
        // Failsafe: verificar si pasaron las 19:00 y falta el respaldo diario de hoy
        BackupService::checkAndRunDailyScheduledBackup();

        $backups = BackupService::listBackups();

        // Calcular estadísticas
        $totalBackups = count($backups);
        $totalBytes = 0;
        $lastBackupDate = 'Sin respaldos';

        if (!empty($backups)) {
            $lastBackupDate = $backups[0]['date'];
            foreach ($backups as $b) {
                $totalBytes += $b['size_bytes'];
            }
        }

        // Determinar fecha/hora de la próxima ejecución programada (7:00 PM)
        date_default_timezone_set('America/Guayaquil');
        $now = time();
        $targetToday = strtotime(date('Y-m-d 19:00:00'));
        if ($now < $targetToday) {
            $nextScheduled = 'Hoy a las 7:00 PM (19:00)';
        } else {
            $nextScheduled = 'Mañana a las 7:00 PM (19:00)';
        }

        $stats = [
            'total_count'    => $totalBackups,
            'total_size'     => BackupService::formatSize($totalBytes),
            'last_backup'    => $lastBackupDate,
            'next_scheduled' => $nextScheduled,
            'auto_status'    => 'Activo (Diario 19:00)'
        ];

        View::render('admin.backups.index', [
            'title'   => 'Gestión de Respaldos de Base de Datos',
            'backups' => $backups,
            'stats'   => $stats,
            'csrf_token' => Session::generateCsrf()
        ], 'admin');
    }

    /**
     * Crear un nuevo respaldo manual a petición del administrador
     */
    public function create()
    {
        Auth::requirePermission('backups_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida o token de seguridad expirado.');
            View::redirect('/admin/backups');
            return;
        }

        try {
            $res = BackupService::createBackup('manual');
            Session::flash('success', "¡Respaldo creado exitosamente! Archivo: {$res['filename']} ({$res['size_formatted']}, {$res['tables_count']} tablas).");
        } catch (\Throwable $e) {
            Session::flash('error', 'Error al generar el respaldo: ' . $e->getMessage());
        }

        View::redirect('/admin/backups');
    }

    /**
     * Descargar un archivo de respaldo de forma segura y controlada
     * Soporta ?format=sql para descomprimir al vuelo si el usuario necesita importar el .sql plano en phpMyAdmin
     */
    public function download(string $filename)
    {
        Auth::requirePermission('backups_read');
        $path = BackupService::getBackupPath($filename);

        if (!$path || !file_exists($path)) {
            Session::flash('error', 'El archivo de respaldo solicitado no existe o fue eliminado.');
            View::redirect('/admin/backups');
            return;
        }

        $format = strtolower($_GET['format'] ?? 'gz');

        // Si se solicita descomprimido (.sql)
        if ($format === 'sql' && str_ends_with($filename, '.gz')) {
            $downloadName = preg_replace('/\.gz$/i', '', $filename);

            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            $gz = gzopen($path, 'rb');
            if ($gz) {
                while (!gzeof($gz)) {
                    echo gzread($gz, 65536);
                }
                gzclose($gz);
            }
            exit;
        }

        // Descarga comprimida nativa (.sql.gz)
        $fileSize = filesize($path);
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile($path);
        exit;
    }

    /**
     * Eliminar un archivo de respaldo
     */
    public function delete(string $filename)
    {
        Auth::requirePermission('backups_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida o token de seguridad expirado.');
            View::redirect('/admin/backups');
            return;
        }

        if (BackupService::deleteBackup($filename)) {
            Session::flash('success', "El archivo de respaldo '{$filename}' ha sido eliminado correctamente.");
        } else {
            Session::flash('error', "No se pudo eliminar el archivo de respaldo o el archivo no existe.");
        }

        View::redirect('/admin/backups');
    }
}
