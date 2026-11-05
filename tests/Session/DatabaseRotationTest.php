<?php

namespace Tests\Unit\Session;

require_once __DIR__ . '/RotationTest.php';

use PDO;
use Phaseolies\Session\Contracts\LockableSessionStore;
use Phaseolies\Session\Stores\DatabaseStore;

/**
 * Runs every rotation and concurrency scenario on the database store.
 */
class DatabaseRotationTest extends RotationTest
{
    private const TABLE = 'sessions';

    private string $database;

    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = sys_get_temp_dir() . '/doppar_rotation_db_' . bin2hex(random_bytes(6)) . '.sqlite';
        $this->pdo = new PDO('sqlite:' . $this->database, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec(
            'CREATE TABLE ' . self::TABLE . ' (id VARCHAR(128) PRIMARY KEY, user_id INTEGER NULL, ip_address VARCHAR(45) NULL, '
                . 'user_agent TEXT NULL, payload TEXT NOT NULL, last_activity INTEGER NOT NULL)'
        );
    }

    protected function tearDown(): void
    {
        unset($this->pdo);
        @unlink($this->database);

        parent::tearDown();
    }

    protected function store(): LockableSessionStore
    {
        $pdo = $this->pdo;

        return new DatabaseStore(static fn (): PDO => $pdo, self::TABLE, 7200);
    }

    protected function liveRecords(): array
    {
        $records = [];

        foreach ($this->pdo->query('SELECT id, payload FROM ' . self::TABLE)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $data = @unserialize((string) base64_decode($row['payload'], true));

            if (is_array($data) && !isset($data['_rotated_to'])) {
                $records[$row['id']] = $data;
            }
        }

        return $records;
    }
}
