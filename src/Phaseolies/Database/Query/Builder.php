<?php

namespace Phaseolies\Database\Query;

use PDO;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class Builder
{
    /**
     * @param string $table
     * @param PDO $pdo
     * @param DriverInterface|null $driver
     */
    public function __construct(protected string $table, protected PDO $pdo, protected ?DriverInterface $driver = null)
    {
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
        if (!$this->tableExists()) {
            throw new \RuntimeException("Table {$this->table} does not exist");
        }

        if ($this->driver) {
            return $this->driver->truncate($this->pdo, $this->table, $resetAutoIncrement);
        }

        // Fallback to direct SQL if no driver is available
        $sql = $resetAutoIncrement
            ? "TRUNCATE TABLE {$this->table}"
            : "DELETE FROM {$this->table}";

        return (int) $this->execute($sql);
    }

    /**
     * Delete all records from the table
     *
     * @return int
     */
    public function delete(): int
    {
        if ($this->driver) {
            return $this->driver->deleteAll($this->pdo, $this->table);
        }
        return (int) $this->execute("DELETE FROM {$this->table}");
    }

    /**
     * Drop table from database
     *
     * @return int
     */
    public function drop(): int
    {
        if ($this->driver) {
            return $this->driver->dropTable($this->pdo, $this->table);
        }
        return (int) $this->execute("DROP TABLE {$this->table}");
    }

    /**
     * Check if table exists
     *
     * @return bool
     */
    protected function tableExists(): bool
    {
        if ($this->driver) {
            return $this->driver->tableExists($this->pdo, $this->table);
        }

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
