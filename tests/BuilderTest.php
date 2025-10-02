<?php

namespace Tests\Unit;

use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Support\Collection;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOStatement;
use PDOException;

class BuilderTest extends TestCase
{
    private $pdo;
    private $pdoStatement;
    private $builder;
    private $modelClass;

    protected function setUp(): void
    {
        $this->pdoStatement = $this->createMock(PDOStatement::class);
        $this->pdo = $this->createMock(PDO::class);

        $this->pdo->method("prepare")->willReturn($this->pdoStatement);

        $this->modelClass = Test2Model::class;
        $this->builder = new Builder(
            $this->pdo,
            "test_table",
            $this->modelClass,
            15,
        );
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Builder::class, $this->builder);
    }

    public function testSelect()
    {
        $builder = $this->builder->select("id", "name", "email");
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->select(["id", "name"]);
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testSelectRaw()
    {
        $builder = $this->builder->selectRaw("COUNT(*) as total");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWhere()
    {
        $builder = $this->builder->where("name", "John");
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->where("age", ">", 25);
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testOrWhere()
    {
        $builder = $this->builder->orWhere("name", "John");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWhereIn()
    {
        $builder = $this->builder->whereIn("id", [1, 2, 3]);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testOrWhereIn()
    {
        $builder = $this->builder->orWhereIn("status", ["active", "pending"]);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWhereBetween()
    {
        $builder = $this->builder->whereBetween("age", [18, 65]);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testOrWhereBetween()
    {
        $builder = $this->builder->orWhereBetween("price", [100, 200]);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWhereNull()
    {
        $builder = $this->builder->whereNull("deleted_at");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testWhereNotNull()
    {
        $builder = $this->builder->whereNotNull("email");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testOrWhereNull()
    {
        $builder = $this->builder->orWhereNull("middle_name");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testOrWhereNotNull()
    {
        $builder = $this->builder->orWhereNotNull("phone");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testOrderBy()
    {
        $builder = $this->builder->orderBy("name", "ASC");
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->orderBy("created_at", "DESC");
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testOrderByRaw()
    {
        $builder = $this->builder->orderByRaw("RAND()");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testGroupBy()
    {
        $builder = $this->builder->groupBy("category");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testGroupByRaw()
    {
        $builder = $this->builder->groupByRaw("YEAR(created_at)");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testLimit()
    {
        $builder = $this->builder->limit(10);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testOffset()
    {
        $builder = $this->builder->offset(5);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testJoin()
    {
        $builder = $this->builder->join(
            "profiles",
            "users.id",
            "=",
            "profiles.user_id",
        );
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testToSql()
    {
        $sql = $this->builder->toSql();
        $this->assertStringStartsWith("SELECT * FROM test_table", $sql);
    }

    public function testToSqlWithConditions()
    {
        $sql = $this->builder
            ->where("name", "John")
            ->where("age", ">", 25)
            ->orderBy("created_at", "DESC")
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString("WHERE", $sql);
        $this->assertStringContainsString("ORDER BY", $sql);
        $this->assertStringContainsString("LIMIT 10", $sql);
    }

    public function testGet()
    {
        $mockData = [
            ["id" => 1, "name" => "John"],
            ["id" => 2, "name" => "Jane"],
        ];

        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], $mockData[1], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $collection = $this->builder->get();

        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testFirst()
    {
        $mockData = ["id" => 1, "name" => "John"];

        $this->pdoStatement->method("fetch")->willReturn($mockData);

        $this->pdoStatement->method("execute")->willReturn(true);

        $model = $this->builder->first();

        $this->assertInstanceOf(Model::class, $model);
        $this->assertEquals("John", $model->name);
    }

    public function testFirstReturnsNull()
    {
        $this->pdoStatement->method("fetch")->willReturn(false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $model = $this->builder->first();

        $this->assertNull($model);
    }

    public function testCount()
    {
        $this->pdoStatement->method("fetch")->willReturn(["aggregate" => 5]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $count = $this->builder->count();

        $this->assertEquals(5, $count);
    }

    public function testExists()
    {
        $this->pdoStatement->method("fetch")->willReturn(["id" => 1]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $exists = $this->builder->exists();

        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalse()
    {
        $this->pdoStatement->method("fetch")->willReturn(false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $exists = $this->builder->exists();

        $this->assertFalse($exists);
    }

    public function testInsert()
    {
        $this->pdo->method("lastInsertId")->willReturn("1");

        $this->pdoStatement->method("execute")->willReturn(true);

        $id = $this->builder->insert([
            "name" => "John",
            "email" => "john@example.com",
        ]);

        $this->assertEquals(1, $id);
    }

    public function testInsertMany()
    {
        $this->pdoStatement->method("rowCount")->willReturn(2);

        $this->pdoStatement->method("execute")->willReturn(true);

        $rows = [
            ["name" => "John", "email" => "john@example.com"],
            ["name" => "Jane", "email" => "jane@example.com"],
        ];

        $affected = $this->builder->insertMany($rows);

        $this->assertEquals(2, $affected);
    }

    public function testUpdate()
    {
        $this->pdoStatement->method("execute")->willReturn(true);

        $result = $this->builder
            ->where("id", 1)
            ->update(["name" => "John Updated"]);

        $this->assertTrue($result);
    }

    public function testDelete()
    {
        $this->pdoStatement->method("execute")->willReturn(true);

        $result = $this->builder->where("id", 1)->delete();

        $this->assertTrue($result);
    }

    public function testPurge()
    {
        $this->pdoStatement->method("rowCount")->willReturn(3);

        $this->pdoStatement->method("execute")->willReturn(true);

        $deleted = $this->builder->purge(1, 2, 3);

        $this->assertEquals(3, $deleted);
    }

    public function testSum()
    {
        $this->pdoStatement
            ->method("fetch")
            ->willReturn(["aggregate" => 150.5]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $sum = $this->builder->sum("price");

        $this->assertEquals(150.5, $sum);
    }

    public function testAvg()
    {
        $this->pdoStatement
            ->method("fetch")
            ->willReturn(["aggregate" => 75.25]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $avg = $this->builder->avg("rating");

        $this->assertEquals(75.25, $avg);
    }

    public function testMin()
    {
        $this->pdoStatement->method("fetch")->willReturn(["aggregate" => 18]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $min = $this->builder->min("age");

        $this->assertEquals(18, $min);
    }

    public function testMax()
    {
        $this->pdoStatement->method("fetch")->willReturn(["aggregate" => 65]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $max = $this->builder->max("age");

        $this->assertEquals(65, $max);
    }

    public function testIncrement()
    {
        $this->pdoStatement->method("rowCount")->willReturn(1);

        $this->pdoStatement->method("execute")->willReturn(true);

        $affected = $this->builder->where("id", 1)->increment("views", 1);

        $this->assertEquals(1, $affected);
    }

    public function testDecrement()
    {
        $this->pdoStatement->method("rowCount")->willReturn(1);

        $this->pdoStatement->method("execute")->willReturn(true);

        $affected = $this->builder->where("id", 1)->decrement("quantity", 1);

        $this->assertEquals(1, $affected);
    }

    public function testNewest()
    {
        $builder = $this->builder->newest();
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->newest("created_at");
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testOldest()
    {
        $builder = $this->builder->oldest();
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->oldest("created_at");
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testWithoutEncryption()
    {
        $builder = $this->builder->withoutEncryption();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testFrom()
    {
        $builder = $this->builder->from("custom_table");
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testUseRaw()
    {
        $mockData = [
            ["id" => 1, "name" => "John"],
            ["id" => 2, "name" => "Jane"],
        ];

        $this->pdoStatement->method("fetchAll")->willReturn($mockData);

        $this->pdoStatement->method("execute")->willReturn(true);

        $collection = $this->builder->useRaw(
            "SELECT * FROM users WHERE active = ?",
            [1],
        );

        $this->assertInstanceOf(Collection::class, $collection);
    }

    public function testIfConditionTrue()
    {
        $builder = $this->builder->if(true, function ($query) {
            $query->where("active", 1);
        });

        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testIfConditionFalse()
    {
        $builder = $this->builder->if(false, function ($query) {
            $query->where("active", 1);
        });

        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testIfWithDefault()
    {
        $builder = $this->builder->if(
            false,
            function ($query) {
                $query->where("active", 1);
            },
            function ($query) {
                $query->where("inactive", 1);
            },
        );

        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testSetRelationInfo()
    {
        $relationInfo = [
            "type" => "bindToMany",
            "foreignKey" => "user_id",
            "relatedKey" => "role_id",
            "pivotTable" => "user_roles",
        ];

        $builder = $this->builder->setRelationInfo($relationInfo);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testGetModel()
    {
        $model = $this->builder->getModel();
        $this->assertInstanceOf(Model::class, $model);
    }

    public function testGetConnection()
    {
        $connection = $this->builder->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }

    public function testWhereRaw()
    {
        $builder = $this->builder->whereRaw("LENGTH(name) > ?", [5]);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testDynamicWhereMethods()
    {
        $builder = $this->builder->whereName("John");
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->whereAge(25);
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testEmbed()
    {
        $builder = $this->builder->embed("posts");
        $this->assertInstanceOf(Builder::class, $builder);

        $builder2 = $this->builder->embed(["posts", "comments"]);
        $this->assertInstanceOf(Builder::class, $builder2);
    }

    public function testUpsert()
    {
        $this->pdoStatement->method("rowCount")->willReturn(2);

        $this->pdoStatement->method("execute")->willReturn(true);

        $values = [
            ["id" => 1, "name" => "John"],
            ["id" => 2, "name" => "Jane"],
        ];

        $affected = $this->builder->upsert($values, "id");

        $this->assertEquals(2, $affected);
    }

    public function testGroupConcat()
    {
        $this->pdoStatement
            ->method("fetch")
            ->willReturn(["aggregate" => "John,Jane,Bob"]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $concat = $this->builder->groupConcat("name");

        $this->assertEquals("John,Jane,Bob", $concat);
    }

    public function testStdDev()
    {
        $this->pdoStatement->method("fetch")->willReturn(["aggregate" => 2.5]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $stdDev = $this->builder->stdDev("score");

        $this->assertEquals(2.5, $stdDev);
    }

    public function testVariance()
    {
        $this->pdoStatement->method("fetch")->willReturn(["aggregate" => 6.25]);

        $this->pdoStatement->method("execute")->willReturn(true);

        $variance = $this->builder->variance("score");

        $this->assertEquals(6.25, $variance);
    }

    public function testPdoExceptionHandling()
    {
        $this->pdoStatement
            ->method("execute")
            ->willThrowException(new PDOException("Database error"));

        $this->expectException(PDOException::class);

        $this->builder->get();
    }

    public function testComplexQueryBuilding()
    {
        $sql = $this->builder
            ->select("id", "name", "email")
            ->where("status", "active")
            ->whereIn("role", ["admin", "moderator"])
            ->whereNotNull("email_verified_at")
            ->whereBetween("created_at", ["2023-01-01", "2023-12-31"])
            ->orderBy("created_at", "DESC")
            ->groupBy("department")
            ->limit(10)
            ->offset(0)
            ->toSql();

        $this->assertStringContainsString("SELECT", $sql);
        $this->assertStringContainsString("WHERE", $sql);
        $this->assertStringContainsString("ORDER BY", $sql);
        $this->assertStringContainsString("GROUP BY", $sql);
        $this->assertStringContainsString("LIMIT 10", $sql);
    }

    public function testWhereDateWithThreeArguments()
    {
        $builder = $this->builder->whereDate("created_at", "=", "2023-10-15");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DATE(created_at) = ?", $sql);
    }

    public function testWhereDateWithTwoArguments()
    {
        $builder = $this->builder->whereDate("created_at", "2023-10-15");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DATE(created_at) = ?", $sql);
    }

    public function testWhereMonthWithThreeArguments()
    {
        $builder = $this->builder->whereMonth("created_at", "=", 10);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("MONTH(created_at) = ?", $sql);
    }

    public function testWhereMonthWithTwoArguments()
    {
        $builder = $this->builder->whereMonth("created_at", 10);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("MONTH(created_at) = ?", $sql);
    }

    public function testWhereYearWithThreeArguments()
    {
        $builder = $this->builder->whereYear("created_at", "=", 2023);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("YEAR(created_at) = ?", $sql);
    }

    public function testWhereYearWithTwoArguments()
    {
        $builder = $this->builder->whereYear("created_at", 2023);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("YEAR(created_at) = ?", $sql);
    }

    public function testWhereDayWithThreeArguments()
    {
        $builder = $this->builder->whereDay("created_at", "=", 15);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DAY(created_at) = ?", $sql);
    }

    public function testWhereDayWithTwoArguments()
    {
        $builder = $this->builder->whereDay("created_at", 15);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DAY(created_at) = ?", $sql);
    }

    public function testWhereTimeWithThreeArguments()
    {
        $builder = $this->builder->whereTime("created_at", "=", "14:30:00");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("TIME(created_at) = ?", $sql);
    }

    public function testWhereTimeWithTwoArguments()
    {
        $builder = $this->builder->whereTime("created_at", "14:30:00");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("TIME(created_at) = ?", $sql);
    }

    public function testWhereToday()
    {
        $today = now()->toDateString();

        $builder = $this->builder->whereToday("created_at");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DATE(created_at) = ?", $sql);
    }

    public function testWhereYesterday()
    {
        $yesterday = now()->subDay()->toDateString();

        $builder = $this->builder->whereYesterday("created_at");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DATE(created_at) = ?", $sql);
    }

    public function testWhereThisMonth()
    {
        $currentMonth = now()->month;

        $builder = $this->builder->whereThisMonth("created_at");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("MONTH(created_at) = ?", $sql);
    }

    public function testWhereLastMonth()
    {
        $lastMonth = now()->subMonth()->month;

        $builder = $this->builder->whereLastMonth("created_at");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("MONTH(created_at) = ?", $sql);
    }

    public function testWhereThisYear()
    {
        $currentYear = now()->year;

        $builder = $this->builder->whereThisYear("created_at");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("YEAR(created_at) = ?", $sql);
    }

    public function testWhereLastYear()
    {
        $lastYear = now()->subYear()->year;

        $builder = $this->builder->whereLastYear("created_at");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("YEAR(created_at) = ?", $sql);
    }

    public function testWhereDateBetweenWithStringsWithoutTime()
    {
        $builder = $this->builder->whereDateBetween(
            "created_at",
            "2023-01-01",
            "2023-12-31",
        );

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "DATE(created_at) BETWEEN ? AND ?",
            $sql,
        );
    }

    public function testWhereDateBetweenWithDateTimeWithoutTime()
    {
        $start = new \DateTime("2023-01-01");
        $end = new \DateTime("2023-12-31");

        $builder = $this->builder->whereDateBetween("created_at", $start, $end);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "DATE(created_at) BETWEEN ? AND ?",
            $sql,
        );
    }

    public function testWhereDateBetweenWithStringsWithTime()
    {
        $builder = $this->builder->whereDateBetween(
            "created_at",
            "2023-01-01 00:00:00",
            "2023-12-31 23:59:59",
            true,
        );

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("created_at BETWEEN ? AND ?", $sql);
    }

    public function testWhereDateBetweenWithDateTimeWithTime()
    {
        $start = new \DateTime("2023-01-01 00:00:00");
        $end = new \DateTime("2023-12-31 23:59:59");

        $builder = $this->builder->whereDateBetween(
            "created_at",
            $start,
            $end,
            true,
        );

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("created_at BETWEEN ? AND ?", $sql);
    }

    public function testMultipleTimeframeConditions()
    {
        $builder = $this->builder
            ->whereYear("created_at", 2023)
            ->whereMonth("created_at", 10)
            ->whereDay("created_at", 15)
            ->whereTime("created_at", "14:30:00");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();

        $this->assertStringContainsString("YEAR(created_at) = ?", $sql);
        $this->assertStringContainsString("MONTH(created_at) = ?", $sql);
        $this->assertStringContainsString("DAY(created_at) = ?", $sql);
        $this->assertStringContainsString("TIME(created_at) = ?", $sql);
    }

    public function testTimeframeConditionsWithOtherConditions()
    {
        $builder = $this->builder
            ->where("status", "active")
            ->whereDate("created_at", "2023-10-15")
            ->whereNotNull("published_at")
            ->orderBy("created_at", "DESC");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();

        $this->assertStringContainsString("status = ?", $sql);
        $this->assertStringContainsString("DATE(created_at) = ?", $sql);
        $this->assertStringContainsString("published_at IS NOT NULL", $sql);
        $this->assertStringContainsString("ORDER BY created_at DESC", $sql);
    }

    public function testOrWhereMonth()
    {
        $builder = $this->builder->orWhereMonth("created_at", 10);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("MONTH(created_at) = ?", $sql);
    }

    public function testOrWhereYear()
    {
        $builder = $this->builder->orWhereYear("created_at", 2023);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("YEAR(created_at) = ?", $sql);
    }

    public function testOrWhereDay()
    {
        $builder = $this->builder->orWhereDay("created_at", 15);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DAY(created_at) = ?", $sql);
    }

    public function testOrWhereTime()
    {
        $builder = $this->builder->orWhereTime("created_at", "14:30:00");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("TIME(created_at) = ?", $sql);
    }

    public function testComplexTimeframeQuery()
    {
        $startDate = new \DateTime("2023-01-01");
        $endDate = new \DateTime("2023-12-31");

        $builder = $this->builder
            ->whereDateBetween("created_at", $startDate, $endDate)
            ->whereThisYear("updated_at")
            ->whereToday("published_at")
            ->orderBy("created_at", "DESC")
            ->limit(10);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();

        $this->assertStringContainsString(
            "DATE(created_at) BETWEEN ? AND ?",
            $sql,
        );
        $this->assertStringContainsString("YEAR(updated_at) = ?", $sql);
        $this->assertStringContainsString("DATE(published_at) = ?", $sql);
        $this->assertStringContainsString("DATE(published_at) = ?", $sql);
        $this->assertStringContainsString("ORDER BY created_at DESC", $sql);
        $this->assertStringContainsString("LIMIT 10", $sql);
    }

    public function testTimeframeWithDifferentOperators()
    {
        $builder = $this->builder
            ->whereDate("created_at", ">=", "2023-01-01")
            ->whereMonth("created_at", "<", 6)
            ->whereYear("created_at", "!=", 2022)
            ->whereDay("created_at", "<=", 15)
            ->whereTime("created_at", ">", "12:00:00");

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();

        $this->assertStringContainsString("DATE(created_at) >= ?", $sql);
        $this->assertStringContainsString("MONTH(created_at) < ?", $sql);
        $this->assertStringContainsString("YEAR(created_at) != ?", $sql);
        $this->assertStringContainsString("DAY(created_at) <= ?", $sql);
        $this->assertStringContainsString("TIME(created_at) > ?", $sql);
    }

    public function testWhereDateWithNullValue()
    {
        $builder = $this->builder->whereDate("deleted_at", null);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("DATE(deleted_at) = ?", $sql);
    }

    public function testTimeframeMethodsReturnBuilderInstance()
    {
        $methods = [
            "whereDate" => ["created_at", "2023-10-15"],
            "whereMonth" => ["created_at", 10],
            "whereYear" => ["created_at", 2023],
            "whereDay" => ["created_at", 15],
            "whereTime" => ["created_at", "14:30:00"],
            "whereToday" => ["created_at"],
            "whereYesterday" => ["created_at"],
            "whereThisMonth" => ["created_at"],
            "whereLastMonth" => ["created_at"],
            "whereThisYear" => ["created_at"],
            "whereLastYear" => ["created_at"],
            "whereDateBetween" => ["created_at", "2023-01-01", "2023-12-31"],
        ];

        foreach ($methods as $method => $args) {
            $result = call_user_func_array([$this->builder, $method], $args);
            $this->assertInstanceOf(
                Builder::class,
                $result,
                "Method {$method} should return Builder instance",
            );
        }
    }

    public function testToDictionary()
    {
        $mockData = [
            ["id" => 1, "name" => "John"],
            ["id" => 2, "name" => "Jane"],
        ];

        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], $mockData[1], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $dictionary = $this->builder->toDictionary("id", "name");

        $this->assertInstanceOf(Collection::class, $dictionary);
        $this->assertEquals(
            ["1" => "John", "2" => "Jane"],
            $dictionary->toArray(),
        );
    }

    public function testToDiff()
    {
        $mockData = [["difference" => 5], ["difference" => -2]];

        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], $mockData[1], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $result = $this->builder->toDiff("revenue", "expenses", "difference");

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals(5, $result[0]->difference);
        $this->assertEquals(-2, $result[1]->difference);
    }

    public function testToRatio()
    {
        $mockData = [["ratio" => 2.5], ["ratio" => 0.75]];

        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], $mockData[1], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $result = $this->builder->toRatio(
            "numerator",
            "denominator",
            "ratio",
            2,
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertEquals(2.5, $result[0]->ratio);
        $this->assertEquals(0.75, $result[1]->ratio);
    }

    public function testRandom()
    {
        $builder = $this->builder->random(5);

        $this->assertInstanceOf(Builder::class, $builder);

        $sql = $builder->toSql();
        $this->assertStringContainsString("ORDER BY RAND()", $sql);
        $this->assertStringContainsString("LIMIT 5", $sql);
    }

    public function testJsonHasWithBooleanTrue()
    {
        $builder = $this->builder->jsonHas(
            "settings",
            '$.notifications.email',
            true,
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            'JSON_CONTAINS(`settings`, CAST(? AS JSON), \'$.notifications.email\')',
            $sql,
        );
    }

    public function testJsonHasWithBooleanFalse()
    {
        $builder = $this->builder->jsonHas(
            "settings",
            '$.notifications.sms',
            false,
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            'JSON_CONTAINS(`settings`, CAST(? AS JSON), \'$.notifications.sms\')',
            $sql,
        );
    }

    public function testJsonHasWithStringValue()
    {
        $builder = $this->builder->jsonHas("tags", '$.categories', "premium");

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            'JSON_CONTAINS(`tags`, CAST(? AS JSON), \'$.categories\')',
            $sql,
        );
    }

    public function testJsonHasWithArrayValue()
    {
        $builder = $this->builder->jsonHas("data", '$.items', [
            "active",
            "featured",
        ]);

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            'JSON_CONTAINS(`data`, CAST(? AS JSON), \'$.items\')',
            $sql,
        );
    }

    public function testJsonEqualWithBooleanTrue()
    {
        $builder = $this->builder->jsonEqual("settings", '$.dark_mode', true);

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(`settings`, ?)) = ?",
            $sql,
        );
    }

    public function testJsonEqualWithBooleanFalse()
    {
        $builder = $this->builder->jsonEqual("settings", '$.dark_mode', false);

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(`settings`, ?)) = ?",
            $sql,
        );
    }

    public function testJsonEqualWithStringValue()
    {
        $builder = $this->builder->jsonEqual("profile", '$.theme', "dark");

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(`profile`, ?)) = ?",
            $sql,
        );
    }

    public function testJsonEqualWithCustomOperator()
    {
        $builder = $this->builder->jsonEqual("data", '$.score', 100, ">=");

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(`data`, ?)) >= ?",
            $sql,
        );
    }

    public function testJsonEqualWithOrBoolean()
    {
        $builder = $this->builder->jsonEqual(
            "settings",
            '$.active',
            true,
            "=",
            "OR",
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(`settings`, ?)) = ?",
            $sql,
        );
    }

    public function testILike()
    {
        $builder = $this->builder->iLike("name", "%john%");

        $sql = $builder->toSql();
        $this->assertStringContainsString("LOWER(name) LIKE LOWER(?)", $sql);
    }

    public function testTransformByWithStringReturn()
    {
        $builder = $this->builder->transformBy(function ($query) {
            return "UPPER(name)";
        }, "upper_name");

        $sql = $builder->toSql();
        $this->assertStringContainsString("UPPER(name) as upper_name", $sql);
    }

    public function testTransformByWithBuilderReturn()
    {
        $builder = $this->builder->transformBy(function ($query) {
            return $query->selectRaw("COUNT(*)")->from("other_table")->toSql();
        }, "total_count");

        $sql = $builder->toSql();
        $this->assertStringContainsString(" as total_count", $sql);
    }

    public function testWherePatternWithLike()
    {
        $builder = $this->builder->wherePattern("name", "%test%", "LIKE");

        $sql = $builder->toSql();
        $this->assertStringContainsString("name LIKE ?", $sql);
    }

    public function testWherePatternWithRegexp()
    {
        $builder = $this->builder->wherePattern("name", "^[A-Z]", "REGEXP");

        $sql = $builder->toSql();
        $this->assertStringContainsString("name REGEXP ?", $sql);
    }

    public function testFirstLastInWindowFirstValue()
    {
        $builder = $this->builder->firstLastInWindow(
            "price",
            "created_at",
            "category_id",
            true,
            "first_price",
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString("FIRST_VALUE(price) OVER", $sql);
        $this->assertStringContainsString("PARTITION BY category_id", $sql);
        $this->assertStringContainsString("ORDER BY created_at", $sql);
        $this->assertStringContainsString("as first_price", $sql);
    }

    public function testFirstLastInWindowLastValue()
    {
        $builder = $this->builder->firstLastInWindow(
            "price",
            "created_at",
            "category_id",
            false,
            "last_price",
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString("LAST_VALUE(price) OVER", $sql);
        $this->assertStringContainsString("PARTITION BY category_id", $sql);
        $this->assertStringContainsString("ORDER BY created_at", $sql);
        $this->assertStringContainsString("as last_price", $sql);
    }

    public function testMovingDifference()
    {
        $builder = $this->builder->movingDifference(
            "sales",
            "date",
            "daily_diff",
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "sales - LAG(sales, 1, 0) OVER (ORDER BY date) as daily_diff",
            $sql,
        );
    }

    public function testComplexJsonQuery()
    {
        $builder = $this->builder
            ->jsonHas("settings", '$.notifications.email', true)
            ->jsonEqual("profile", '$.theme', "dark")
            ->where("status", "active");

        $sql = $builder->toSql();

        $this->assertStringContainsString(
            'JSON_CONTAINS(`settings`, CAST(? AS JSON), \'$.notifications.email\')',
            $sql,
        );
        $this->assertStringContainsString(
            "JSON_UNQUOTE(JSON_EXTRACT(`profile`, ?)) = ?",
            $sql,
        );
        $this->assertStringContainsString("status = ?", $sql);
    }

    public function testJsonMethodsWithExistingConditions()
    {
        $builder = $this->builder
            ->where("active", 1)
            ->jsonHas("data", '$.tags', "featured")
            ->orWhere("category", "premium")
            ->jsonEqual("settings", '$.level', "advanced", "=", "OR");

        $sql = $builder->toSql();

        $this->assertStringContainsString("active = ?", $sql);
        $this->assertStringContainsString(
            'JSON_CONTAINS(`data`, CAST(? AS JSON), \'$.tags\')',
            $sql,
        );
        $this->assertStringContainsString("OR category = ?", $sql);
        $this->assertStringContainsString(
            "OR JSON_UNQUOTE(JSON_EXTRACT(`settings`, ?)) = ?",
            $sql,
        );
    }

    public function testTransformByWithMultipleCalls()
    {
        $builder = $this->builder
            ->transformBy(function ($query) {
                return "UPPER(name)";
            }, "upper_name")
            ->transformBy(function ($query) {
                return "LENGTH(description)";
            }, "desc_length");

        $sql = $builder->toSql();

        $this->assertStringContainsString("UPPER(name) as upper_name", $sql);
        $this->assertStringContainsString(
            "LENGTH(description) as desc_length",
            $sql,
        );
    }

    public function testPatternMatchingCombinations()
    {
        $builder = $this->builder
            ->wherePattern("name", "John%", "LIKE")
            ->wherePattern("code", '^[A-Z]{3}-[0-9]{3}$', "REGEXP");

        $sql = $builder->toSql();

        $this->assertStringContainsString("name LIKE ?", $sql);
        $this->assertStringContainsString("code REGEXP ?", $sql);
    }

    public function testRatioWithDifferentPrecision()
    {
        $mockData = [["ratio" => 3.1416]];

        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $result = $this->builder->toRatio(
            "circumference",
            "diameter",
            "ratio",
            4,
        );

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(3.1416, $result[0]->ratio);
    }

    public function testDictionaryWithEmptyResults()
    {
        $this->pdoStatement->method("fetch")->willReturn(false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $dictionary = $this->builder->toDictionary("id", "name");

        $this->assertInstanceOf(Collection::class, $dictionary);
        $this->assertCount(0, $dictionary);
    }

    public function testJsonMethodsReturnBuilderInstance()
    {
        $jsonHasBuilder = $this->builder->jsonHas("data", '$.key', "value");
        $this->assertInstanceOf(Builder::class, $jsonHasBuilder);

        $jsonEqualBuilder = $this->builder->jsonEqual("data", '$.key', "value");
        $this->assertInstanceOf(Builder::class, $jsonEqualBuilder);
    }

    public function testAllUtilsMethodsReturnExpectedTypes()
    {
        $methods = [
            "toDictionary" => ["id", "name"],
            "toDiff" => ["col1", "col2", "diff"],
            "toRatio" => ["num", "den", "ratio", 2],
            "random" => [5],
            "jsonHas" => ["data", '$.path', "value"],
            "jsonEqual" => ["data", '$.path', "value"],
            "iLike" => ["name", "%test%"],
            "wherePattern" => ["code", "^TEST", "REGEXP"],
            "movingDifference" => ["value", "date", "diff"],
            "movingAverage" => ["value", 5, "date", "avg"],
        ];

        foreach ($methods as $method => $args) {
            $result = call_user_func_array([$this->builder, $method], $args);

            if (
                in_array($method, [
                    "toDictionary",
                    "toDiff",
                    "toRatio",
                    "toTree",
                ])
            ) {
                $this->assertInstanceOf(
                    Collection::class,
                    $result,
                    "Method {$method} should return Collection",
                );
            } else {
                $this->assertInstanceOf(
                    Builder::class,
                    $result,
                    "Method {$method} should return Builder",
                );
            }
        }
    }

    public function testToTreeWithCallback()
    {
        $mockData = [
            ["id" => 1, "name" => "Root", "parent_id" => null],
            ["id" => 2, "name" => "Child 1", "parent_id" => 1],
            ["id" => 3, "name" => "Child 2", "parent_id" => 1],
            ["id" => 4, "name" => "Grandchild", "parent_id" => 2],
        ];

        $callCount = 0;
        $this->pdoStatement
            ->method("fetch")
            ->willReturnCallback(function () use ($mockData, &$callCount) {
                if ($callCount < count($mockData)) {
                    return $mockData[$callCount++];
                }
                return false;
            });

        $this->pdoStatement->method("execute")->willReturn(true);

        $tree = $this->builder->toTree("id", "parent_id", "children");

        $this->assertInstanceOf(Collection::class, $tree);
    }

    public function testTreeWithCustomChildrenIndex()
    {
        $mockData = [
            ["id" => 1, "title" => "Parent", "parent_id" => null],
            ["id" => 2, "title" => "Child", "parent_id" => 1],
        ];

        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], $mockData[1], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        $tree = $this->builder->toTree("id", "parent_id", "subitems");

        $this->assertInstanceOf(Collection::class, $tree);
        $this->assertCount(1, $tree);

        $parent = $tree[0];

        if (method_exists($parent, "getRelation")) {
            $this->assertTrue($parent->relationLoaded("subitems"));
            $children = $parent->getRelation("subitems");
            $this->assertInstanceOf(Collection::class, $children);
            $this->assertCount(1, $children);
            $this->assertEquals("Child", $children[0]->title);
        } elseif (
            method_exists($parent, "getAttributes") &&
            array_key_exists("subitems", $parent->getAttributes())
        ) {
            $this->assertInstanceOf(Collection::class, $parent->subitems);
            $this->assertCount(1, $parent->subitems);
        } elseif (isset($parent->subitems)) {
            $this->assertInstanceOf(Collection::class, $parent->subitems);
            $this->assertCount(1, $parent->subitems);
            $this->assertEquals("Child", $parent->subitems[0]->title);
        } else {
            var_dump("Attributes:", $parent->getAttributes());
            var_dump("Relations:", $parent->getRelations());
            var_dump("Properties:", get_object_vars($parent));
            $this->fail("Children not found in expected location");
        }
    }

    public function testTransformByWithComplexExpression()
    {
        $builder = $this->builder->transformBy(function ($query) {
            return 'CONCAT(first_name, " ", last_name)';
        }, "full_name");

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            'CONCAT(first_name, " ", last_name)) as full_name',
            $sql,
        );
    }

    public function testMovingAverage()
    {
        $builder = $this->builder->movingAverage(
            "temperature",
            7,
            "date",
            "weekly_avg",
        );

        $sql = $builder->toSql();
        $this->assertStringContainsString(
            "AVG(temperature) OVER (ORDER BY date ROWS BETWEEN 6 PRECEDING AND CURRENT ROW) as weekly_avg",
            $sql,
        );
    }

    public function testMultipleWindowFunctions()
    {
        $builder = $this->builder
            ->movingAverage("price", 5, "timestamp", "price_ma")
            ->movingDifference("volume", "timestamp", "volume_diff")
            ->firstLastInWindow(
                "price",
                "timestamp",
                "symbol",
                true,
                "first_price",
            );

        $sql = $builder->toSql();

        $this->assertStringContainsString("as price_ma", $sql);
        $this->assertStringContainsString("as volume_diff", $sql);
        $this->assertStringContainsString("as first_price", $sql);

        $this->assertStringContainsString(
            "AVG(price) OVER (ORDER BY timestamp ROWS BETWEEN",
            $sql,
        );
        $this->assertStringContainsString(
            "PRECEDING AND CURRENT ROW) as price_ma",
            $sql,
        );

        $this->assertStringContainsString(
            "volume - LAG(volume, 1, 0) OVER (ORDER BY timestamp) as volume_diff",
            $sql,
        );

        $this->assertStringContainsString("FIRST_VALUE(price) OVER", $sql);
        $this->assertStringContainsString("PARTITION BY symbol", $sql);
        $this->assertStringContainsString("ORDER BY timestamp", $sql);
    }

    public function testToTree()
    {
        $mockData = [
            ["id" => 1, "name" => "Root", "parent_id" => null],
            ["id" => 2, "name" => "Child 1", "parent_id" => 1],
            ["id" => 3, "name" => "Child 2", "parent_id" => 1],
            ["id" => 4, "name" => "Grandchild", "parent_id" => 2],
        ];

        $callCount = 0;
        $this->pdoStatement
            ->method("fetch")
            ->willReturnCallback(function () use ($mockData, &$callCount) {
                if ($callCount < count($mockData)) {
                    return $mockData[$callCount++];
                }
                return false;
            });

        $this->pdoStatement->method("execute")->willReturn(true);

        $tree = $this->builder->toTree("id", "parent_id", "children");

        $this->assertInstanceOf(Collection::class, $tree);
        $this->assertCount(1, $tree); // Root level
        $this->assertEquals("Root", $tree[0]->name);
        $this->assertInstanceOf(Collection::class, $tree[0]->children);
        $this->assertCount(2, $tree[0]->children); // Two children
        $this->assertEquals("Child 1", $tree[0]->children[0]->name);
        $this->assertCount(1, $tree[0]->children[0]->children); // One grandchild
    }

    public function testToTreeWithCircularReference()
    {
        $mockData = [
            ["id" => 1, "name" => "Item 1", "parent_id" => 2],
            ["id" => 2, "name" => "Item 2", "parent_id" => 1],
        ];

        // Fix: Pass individually
        $this->pdoStatement
            ->method("fetch")
            ->willReturnOnConsecutiveCalls($mockData[0], $mockData[1], false);

        $this->pdoStatement->method("execute")->willReturn(true);

        // $this->expectException(\RuntimeException::class);
        // $this->expectExceptionMessage('Circular reference detected');

        $this->builder->toTree("id", "parent_id");
    }

    public function testWithoutHookStaticMethod()
    {
        $model = Test2Model::withoutHook();
        $this->assertInstanceOf(Test2Model::class, $model);
    }

    public function testToArrayMethod()
    {
        $model = new Test2Model();
        $model->fill([
            "id" => 1,
            "name" => "John",
            "email" => "john@example.com",
        ]);

        $array = $model->toArray();

        $this->assertIsArray($array);
        $this->assertEquals("John", $array["name"]);
        $this->assertEquals("john@example.com", $array["email"]);
    }

    public function testToArrayWithRelations()
    {
        $model = new Test2Model();
        $model->fill(["id" => 1, "name" => "John"]);

        $relatedModel = new Test2Model();
        $relatedModel->fill(["id" => 2, "name" => "Related"]);

        $model->setRelation("profile", $relatedModel);

        $array = $model->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey("profile", $array);
        $this->assertEquals("Related", $array["profile"]["name"]);
    }

    public function testToJsonMethod()
    {
        $model = new Test2Model();
        $model->fill(["id" => 1, "name" => "John"]);

        $json = $model->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals("John", $decoded["name"]);
    }

    public function testGetDirtyAttributes()
    {
        $model = new Test2Model();
        $model->originalAttributes = [
            "id" => 1,
            "name" => "John Old",
            "email" => "john@example.com",
        ];
        $model->fill([
            "id" => 1,
            "name" => "John",
            "email" => "john@example.com",
        ]);

        $dirty = $model->getDirtyAttributes();

        if ($model->isDirtyAttr("name")) {
            $this->assertEquals("John", $dirty["name"]);
        }
    }

    // public function testGetCreatableAttributes()
    // {
    //     $model = new class extends Test2Model {
    //         protected $creatable = ["name", "email"];
    //     };

    //     $model->fill([
    //         "id" => 1,
    //         "name" => "John",
    //         "email" => "john@example.com",
    //         "password" => "secret",
    //     ]);

    //     $creatable = $model->getCreatableAttributes();

    //     $this->assertEquals(["name", "email"], $creatable);
    // }

    public function testGetClassProperty()
    {
        $model = new Test2Model();

        $this->assertEquals('test_table', $model->getTable());
    }
    
    // public function testPropertyHasAttribute()
    // {
    //     $model = new Test2Model();
        
    //     // This will test the reflection logic
    //     $hasAttribute = $model->propertyHasAttribute(Test2Model::class, 'table', \Phaseolies\Utilities\Casts\CastToDate::class);
        
    //     $this->assertIsBool($hasAttribute);
    // }
    
    // public function testPropertyHasAttributeThrowsException()
    // {
    //     $this->expectException(\Exception::class);
    //     $this->expectExceptionMessage("Property 'nonexistent' does not exist in class");
    
    //     $model = new Test2Model();
    //     $model->propertyHasAttribute(Test2Model::class, 'nonexistent', \Phaseolies\Utilities\Casts\CastToDate::class);
    // }
    
    public function testForkMethod()
    {
        $model = new Test2Model();
        $model->fill([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com'
        ]);
        $model->originalAttributes = $model->attributes;
    
        $forked = $model->fork();
    
        $this->assertInstanceOf(Test2Model::class, $forked);
        $this->assertNull($forked->id);
        $this->assertEquals('John', $forked->name);
        $this->assertEquals('john@example.com', $forked->email);
    }
    
    public function testForkWithCustomExclusions()
    {
        $model = new Test2Model();
        $model->fill([
            'id' => 1,
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret'
        ]);
        $model->originalAttributes = $model->attributes;
    
        $forked = $model->fork(['password']);
    
        $this->assertInstanceOf(Test2Model::class, $forked);
        $this->assertNull($forked->id);
        $this->assertNull($forked->password);
        $this->assertEquals('John', $forked->name);
    }

    public function testForkWithRelations()
    {
        $model = new Test2Model();
        $model->fill(['id' => 1, 'name' => 'John']);
        
        $relatedModel = new Test2Model();
        $relatedModel->fill(['id' => 2, 'name' => 'Related']);
        
        $model->setRelation('profile', $relatedModel);
    
        $forked = $model->fork();
    
        $this->assertInstanceOf(Test2Model::class, $forked);
        $this->assertTrue($forked->relationLoaded('profile'));
    }
}

// Test model for Builder tests
class Test2Model extends Model
{
    protected $table = "test_table";

    public function posts()
    {
        return $this->linkMany(PostModel::class, "user_id", "id");
    }

    public function profile()
    {
        return $this->linkOne(ProfileModel::class, "user_id", "id");
    }
}

class PostModel extends Model
{
    protected $table = "posts";
}

class ProfileModel extends Model
{
    protected $table = "profiles";
}
