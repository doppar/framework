<?php

namespace Phaseolies\Support\Facades;

use PDO;
use PDOStatement;
use Phaseolies\Facade\BaseFacade;
use Phaseolies\Database\Query\Builder;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Support\Collection;

/**
 * @method static PDO getPdoInstance(?string $connection = null)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 * @method static int transactionLevel()
 * @method static array getTableColumns(?string $table = null)
 * @method static int dropAllTables()
 * @method static Builder table(string $table)
 * @method static array getTables()
 * @method static bool tableExists(string $table)
 * @method static string getTable(Model $model)
 * @method static PDO getConnection()
 * @method static \Phaseolies\Database\Procedure\ProcedureResult procedure(string $procedureName,array $params = [],array $outputParams = [])
 * @method static array view(string $viewName, array $where = [], array $params = [])
 * @method static Collection query(string $sql, array $params = [])
 * @method static int execute(string $sql, array $params = [])
 * @method static PDOStatement statement(string $sql, array $params = [])
 * @method static \Phaseolies\Database\Database connection(?string $name = null)
 * @method static \Phaseolies\Database\Entity\Query\Builder bucket(string $table)
 * @method static bool disconnect(?string $connection = null)
 * @method static PDO reconnect(?string $connection = null)
 * @method static bool isConnected(?string $connection = null)
 * @method static PDO getFreshConnection(?string $connection = null)
 * @method static void cleanupAllConnections()
 * @method static void disableForeignKeyConstraints()
 * @method static void enableForeignKeyConstraints()
 *
 * @see \Phaseolies\Database\Database
 */
class DB extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'db';
    }
}
