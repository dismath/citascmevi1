<?php
namespace App\Helpers;

/**
 * Database Singleton using PDO
 * Compatible with MySQL and PostgreSQL
 */
class Database
{
    private static ?self $instance = null;
    private \PDO $pdo;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
        $dsn = match ($config['driver']) {
            'mysql'  => "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}",
            'pgsql'  => "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
            default  => throw new \RuntimeException("Unsupported driver: {$config['driver']}"),
        };

        try {
            $this->pdo = new \PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (\PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            
            $port = $config['port'] ?? 3306;
            $host = $config['host'] ?? '127.0.0.1';
            
            if ($e->getCode() == 2002 || str_contains($e->getMessage(), '2002')) {
                throw new \RuntimeException(
                    "No se pudo conectar al servidor de base de datos ({$host}:{$port}). " .
                    "Verifique que el servicio MySQL/MariaDB esté INICIADO en el Panel de Control de XAMPP.",
                    2002,
                    $e
                );
            }

            throw new \RuntimeException("Error al conectar con la base de datos: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function init(array $config): void
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Database not initialized. Call Database::init() first.');
        }
        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a query and return PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch single row
     */
    public function fetch(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    /**
     * Execute and return affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
}
