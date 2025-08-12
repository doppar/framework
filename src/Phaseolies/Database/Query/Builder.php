<?php

namespace Phaseolies\Database\Query;

use PDO;

class Builder
{
    /**
     * @var string
     */
    protected string $table;

    /**
     * @var PDO
     */
    protected PDO $pdo;

    /**
     * @param string $table
     * @param PDO $pdo
     */
    public function __construct(string $table, PDO $pdo)
    {
        $this->table = $table;
        $this->pdo = $pdo;
    }

    /**
     * Truncate the table
     *
     * @param bool $resetAutoIncrement Whether to reset auto-increment values
     * @return int
     * @throws \RuntimeException When table doesn't exist
     */
    public function truncate(bool $resetAutoIncrement = true): int
    {
        $sql = "TRUNCATE TABLE {$this->table}";

        if (!$resetAutoIncrement) {
            $sql = "DELETE FROM {$this->table}";
        }

        if (!$this->tableExists()) {
            throw new \RuntimeException("Table {$this->table} does not exist");
        }

        return (int) $this->execute($sql);
    }

    /**
     * Delete all records from the table
     *
     * @return int
     */
    public function delete(): int
    {
        return (int) $this->execute("DELETE FROM {$this->table}");
    }

    /**
     * Drop table from database
     *
     * @return int
     */
    public function drop(): int
    {
        return (int) $this->execute("DROP TABLE {$this->table}");
    }

    /**
     * Check if table exists
     * 
     * @return bool
     */
    protected function tableExists(): bool
    {
        try {
            $result = $this->pdo->query("SELECT 1 FROM {$this->table} LIMIT 1");
            return $result !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Execute SQL statement
     *
     * @param string $sql
     * @param array $params
     * @return int
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Get the PDO instance
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
