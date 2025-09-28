<?php

namespace Tests\Unit;

use Phaseolies\Database\Query\RawExpression;
use PHPUnit\Framework\TestCase;

class RawExpressionTest extends TestCase
{
    public function testConstructorWithOnlyValue()
    {
        $expression = 'NOW()';
        $rawExpr = new RawExpression($expression);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals([], $rawExpr->getBindings());
    }

    public function testConstructorWithValueAndBindings()
    {
        $expression = 'COUNT(*) > ?';
        $bindings = [5];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testConstructorWithMultipleBindings()
    {
        $expression = 'price BETWEEN ? AND ?';
        $bindings = [10, 100];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testGetValue()
    {
        $expression = 'UPPER(name)';
        $rawExpr = new RawExpression($expression);

        $this->assertEquals($expression, $rawExpr->getValue());
    }

    public function testGetBindings()
    {
        $expression = 'age > ? AND status = ?';
        $bindings = [18, 'active'];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testGetBindingsWithEmptyArray()
    {
        $expression = 'RAND()';
        $rawExpr = new RawExpression($expression, []);

        $this->assertEquals([], $rawExpr->getBindings());
    }

    public function testToStringMagicMethod()
    {
        $expression = 'COALESCE(name, "Unknown")';
        $rawExpr = new RawExpression($expression);

        $this->assertEquals($expression, (string)$rawExpr);
    }

    public function testToStringWithComplexExpression()
    {
        $expression = '(SELECT COUNT(*) FROM users WHERE active = 1)';
        $rawExpr = new RawExpression($expression);

        $this->assertEquals($expression, (string)$rawExpr);
    }

    public function testExpressionWithNoBindings()
    {
        $expression = 'CURRENT_TIMESTAMP';
        $rawExpr = new RawExpression($expression);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEmpty($rawExpr->getBindings());
        $this->assertEquals($expression, (string)$rawExpr);
    }

    public function testExpressionWithNamedPlaceholders()
    {
        $expression = 'created_at > :start_date AND created_at < :end_date';
        $bindings = ['start_date' => '2023-01-01', 'end_date' => '2023-12-31'];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testExpressionWithMixedPlaceholders()
    {
        $expression = 'status = ? AND created_at > :date';
        $bindings = ['active', 'date' => '2023-01-01'];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testImmutability()
    {
        $expression = 'original';
        $bindings = ['original_binding'];
        $rawExpr = new RawExpression($expression, $bindings);

        // Verify that internal state cannot be modified from outside
        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());

        // Test that modifying the returned arrays doesn't affect the internal state
        $returnedBindings = $rawExpr->getBindings();
        $returnedBindings[] = 'new_binding';

        $this->assertCount(1, $rawExpr->getBindings()); // Original bindings should be unchanged
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testComplexSqlExpression()
    {
        $expression = 'EXISTS(SELECT 1 FROM orders WHERE user_id = users.id AND total > ?)';
        $bindings = [1000];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
        $this->assertEquals($expression, (string)$rawExpr);
    }

    public function testCaseExpression()
    {
        $expression = 'CASE WHEN status = ? THEN "active" ELSE "inactive" END';
        $bindings = [1];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testJsonExpression()
    {
        $expression = 'JSON_EXTRACT(metadata, "$.category") = ?';
        $bindings = ['premium'];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testMathematicalExpression()
    {
        $expression = '(price * ?) + ?';
        $bindings = [1.1, 5];
        $rawExpr = new RawExpression($expression, $bindings);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEquals($bindings, $rawExpr->getBindings());
    }

    public function testStringConcatenation()
    {
        $expression = 'CONCAT(first_name, " ", last_name)';
        $rawExpr = new RawExpression($expression);

        $this->assertEquals($expression, $rawExpr->getValue());
        $this->assertEmpty($rawExpr->getBindings());
    }
}
