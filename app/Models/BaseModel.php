<?php
namespace App\Models;

use App\Helpers\Database;

abstract class BaseModel
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * FIX-SEC-09: Sanitizar cláusula ORDER BY
     */
    protected function sanitizeOrderBy(string $orderBy): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*(?:\s+(?:ASC|DESC))?$/i', trim($orderBy))) {
            return $this->primaryKey . ' DESC';
        }
        return $orderBy;
    }

    public function findAll(string $orderBy = 'id DESC', int $limit = 0): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy);
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy}";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        return $this->db->fetchAll($sql);
    }

    public function paginate(int $page = 1, int $perPage = 25, string $orderBy = 'id DESC', string $where = '1=1', array $params = []): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $orderBy = $this->sanitizeOrderBy($orderBy);
        
        $data = $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        
        $total = $this->count($where, $params);
        $totalPages = ceil($total / $perPage);
        
        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $total,
            'total_pages' => $totalPages
        ];
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        );
    }

    public function create(array $data): int
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $this->db->execute(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );
        
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): int
    {
        $set = implode(', ', array_map(fn($col) => "{$col} = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        
        return $this->db->execute(
            "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?",
            $values
        );
    }

    public function delete(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
            [$id]
        );
    }

    public function count(string $where = '1=1', array $params = []): int
    {
        $result = $this->db->fetch(
            "SELECT COUNT(*) as total FROM {$this->table} WHERE {$where}",
            $params
        );
        return (int) ($result['total'] ?? 0);
    }

    public function findWhere(string $where, array $params = [], string $orderBy = 'id DESC'): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy);
        return $this->db->fetchAll(
            "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderBy}",
            $params
        );
    }

    public function findOneWhere(string $where, array $params = []): array|false
    {
        return $this->db->fetch(
            "SELECT * FROM {$this->table} WHERE {$where} LIMIT 1",
            $params
        );
    }
}
