<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Support\Facades\DB;
use Phaseolies\Database\Database;
use Phaseolies\Database\Migration\Grammars\Grammar;
use Phaseolies\Database\Migration\Grammars\GrammarFactory;
use PDO;

class Schema
{
    /**
     * The database connection name
     *
     * @var string|null
     */
    protected ?string $connection = null;

    /**
     * Create a new Schema instance with a specific connection
     *
     * @param string|null $connection
     */
    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * Create a new database table
     *
     * @param string $table
     * @param callable $callback
     */
    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);

        $callback($blueprint);

        $this->run($blueprint);
    }

    /**
     * Modify an existing database table
     *
     * @param string $table
     * @param callable $callback
     */
    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, null, false);

        $callback($blueprint);

        $this->run($blueprint);
    }

    /**
     * Execute the blueprint one statement at a time. Not every driver can run
     * several statements in a single call (SQLite silently runs only the first).
     *
     * @param Blueprint $blueprint
     * @return void
     */
    protected function run(Blueprint $blueprint): void
    {
        foreach ($blueprint->toStatements() as $statement) {
            DB::connection($this->connection)->execute($statement);
        }
    }

    /**
     * Drop a table
     *
     * @param string $table
     */
    public function drop(string $table): void
    {
        DB::connection($this->connection)->execute($this->grammar()->compileDropTable($table));
    }

    /**
     * Drop a table if it exists
     *
     * @param string $table
     */
    public function dropIfExists(string $table): void
    {
        DB::connection($this->connection)->execute("DROP TABLE IF EXISTS {$table}");
    }

    /**
     * Rename a table
     *
     * @param string $from
     * @param string $to
     */
    public function rename(string $from, string $to): void
    {
        DB::connection($this->connection)->execute($this->grammar()->compileRenameTable($from, $to));
    }

    /**
     * Drop the given columns from a table
     *
     * @param string $table
     * @param string|array $columns
     */
    public function dropColumns(string $table, string|array $columns): void
    {
        $this->table($table, fn(Blueprint $blueprint) => $blueprint->dropColumn($columns));
    }

    /**
     * Rename a column
     *
     * @param string $table
     * @param string $from
     * @param string $to
     */
    public function renameColumn(string $table, string $from, string $to): void
    {
        $this->table($table, fn(Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
    }

    /**
     * Determine if a table has a column
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function hasColumn(string $table, string $column): bool
    {
        return in_array(strtolower($column), array_map('strtolower', $this->getColumnListing($table)), true);
    }

    /**
     * Determine if a table has all of the given columns
     *
     * @param string $table
     * @param array $columns
     * @return bool
     */
    public function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!$this->hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the column names of a table
     *
     * @param string $table
     * @return array
     */
    public function getColumnListing(string $table): array
    {
        return $this->fetchNames($this->grammar()->compileGetColumns(), $table);
    }

    /**
     * Determine if a table has an index (or unique constraint) with the given
     * name, or one that was created on exactly the given column list.
     *
     * @param string $table
     * @param string|array $index
     * @return bool
     */
    public function hasIndex(string $table, string|array $index): bool
    {
        $grammar = $this->grammar();
        $existing = array_map('strtolower', $this->fetchNames($grammar->compileGetIndexes(), $table));

        $names = is_array($index)
            ? [$grammar->indexName($table, $index), $grammar->indexName($table, $index, 'unique')]
            : [$index];

        foreach ($names as $name) {
            if (in_array(strtolower($name), $existing, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        return (bool) DB::connection($this->connection)->tableExists($table);
    }

    /**
     * Disable foreign key constraints
     *
     * @return void
     */
    public function disableForeignKeyConstraints(): void
    {
        DB::connection($this->connection)->disableForeignKeyConstraints();
    }

    /**
     * Enable foreign key constraints
     *
     * @return void
     */
    public function enableForeignKeyConstraints(): void
    {
        DB::connection($this->connection)->enableForeignKeyConstraints();
    }

    /**
     * Get the grammar of the current connection
     *
     * @return Grammar
     */
    protected function grammar(): Grammar
    {
        return GrammarFactory::make(DB::connection($this->connection)->getDriver());
    }

    /**
     * Run an introspection query bound to a table name
     *
     * @param string $sql
     * @param string $table
     * @return array
     */
    protected function fetchNames(string $sql, string $table): array
    {
        $statement = Database::getPdoInstance($this->connection)->prepare($sql);
        $statement->execute([$table]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get a new Schema instance for the specified connection
     *
     * @param string|null $connection
     * @return static
     */
    public static function connection(?string $connection): self
    {
        return new static($connection);
    }
}
