<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Helper\BucketCompiler;
use InitORM\QueryBuilder\Helper\SqlValueDetector;
use InitORM\QueryBuilder\QueryBuilder;
use InitORM\QueryBuilder\RawQuery;
use PHPUnit\Framework\TestCase;

/**
 * Covers the QueryBuilder facade methods that the clause-trait tests do not
 * exercise directly: __toString heuristic dispatch, isBatch, single-column
 * set(), and the small helper classes.
 */
class QueryBuilderBehaviorTest extends TestCase
{
    public function testToStringWithoutSetEmitsSelect(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users');
        $this->assertStringStartsWith('SELECT ', (string) $qb);
    }

    public function testToStringWithSetAndNoWhereEmitsInsert(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users')->set(['name' => 'a']);
        $this->assertStringStartsWith('INSERT INTO ', (string) $qb);
    }

    public function testToStringWithSetAndWhereEmitsUpdate(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users')->where('id', 1)->set(['name' => 'a']);
        $this->assertStringStartsWith('UPDATE ', (string) $qb);
    }

    public function testToStringWithBatchSetAndNoWhereEmitsBatchInsert(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users')
            ->set(['name' => 'a', 'email' => 'a@a.test'])
            ->set(['name' => 'b', 'email' => 'b@b.test']);

        $sql = (string) $qb;
        $this->assertStringStartsWith('INSERT INTO ', $sql);
        // Two rows in VALUES → batch.
        $this->assertStringContainsString('), (', $sql);
    }

    public function testIsBatchTrueWhenAnyRowHasMultipleColumns(): void
    {
        $qb = new QueryBuilder();
        $qb->set(['a' => 1, 'b' => 2]);
        $this->assertTrue($qb->isBatch());
    }

    public function testIsBatchFalseWhenAllRowsAreSingleColumn(): void
    {
        $qb = new QueryBuilder();
        $qb->set('a', 1);
        $this->assertFalse($qb->isBatch());
    }

    public function testSetSingleColumnFormAddsRow(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users')->set('name', 'fred');

        $structure = $qb->exportQB();
        $this->assertCount(1, $structure['set']);
        // single-column form stores [[name => :name]]
        $this->assertArrayHasKey('name', $structure['set'][0]);
    }

    public function testSetSingleColumnWithIntegerInlinesValue(): void
    {
        $qb = new QueryBuilder();
        $qb->from('counter')->set('value', 42);
        $structure = $qb->exportQB();

        // Integer value is inlined as is (SqlValueDetector::isSqlParameterOrFunction(42) === true).
        $this->assertSame(42, $structure['set'][0]['value']);
    }

    public function testSetSingleColumnWithStringParameterizesValue(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users')->set('name', 'fred');
        $structure = $qb->exportQB();

        $this->assertSame(':name', $structure['set'][0]['name']);
        $this->assertSame('fred', $qb->getParameter()->get('name'));
    }

    public function testGetDriverReturnsActiveDriver(): void
    {
        $qb = new QueryBuilder('mysql');
        $this->assertSame('mysql', $qb->getDriver()->getName());
    }

    // ---- SqlValueDetector boundary tests --------------------------------

    /**
     * @return array<string, array{0:mixed, 1:bool}>
     */
    public static function sqlParameterProvider(): array
    {
        return [
            'positional'   => ['?',          true],
            'named'        => [':id',        true],
            'just colon'   => [':',          false],
            'word'         => ['foo',        false],
            'int'          => [5,            false],
            'null'         => [null,         false],
            'rawQuery'     => [new RawQuery(':id'), false], // RawQuery NOT a placeholder per isSqlParameter
        ];
    }

    /**
     * @dataProvider sqlParameterProvider
     */
    public function testIsSqlParameterIdentifiesPlaceholders(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, SqlValueDetector::isSqlParameter($value));
    }

    /**
     * @return array<string, array{0:mixed, 1:bool}>
     */
    public static function sqlParameterOrFunctionProvider(): array
    {
        return [
            'positional'  => ['?',                  true],
            'named'       => [':id',                true],
            'function'    => ['NOW()',              true],
            'dotted col'  => ['users.id',           true],
            'rawQuery'    => [new RawQuery('NOW()'),true],
            'integer'     => [5,                    true],
            'string lit'  => ['admin',              false],
            'null'        => [null,                 false],
            'array'       => [[1, 2],               false],
        ];
    }

    /**
     * @dataProvider sqlParameterOrFunctionProvider
     */
    public function testIsSqlParameterOrFunctionIdentifiesInlinableValues(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, SqlValueDetector::isSqlParameterOrFunction($value));
    }

    // ---- BucketCompiler tests -------------------------------------------

    public function testBucketCompilerReturnsNullForEmptyBuckets(): void
    {
        $this->assertNull(BucketCompiler::compile([
            'where' => ['AND' => [], 'OR' => []],
        ], 'where'));
    }

    public function testBucketCompilerJoinsAndOnlyWithAnd(): void
    {
        $structure = ['where' => ['AND' => ['a = 1', 'b = 2'], 'OR' => []]];
        $this->assertSame('a = 1 AND b = 2', BucketCompiler::compile($structure, 'where'));
    }

    public function testBucketCompilerJoinsOrOnlyWithOr(): void
    {
        $structure = ['where' => ['AND' => [], 'OR' => ['a = 1', 'b = 2']]];
        $this->assertSame('a = 1 OR b = 2', BucketCompiler::compile($structure, 'where'));
    }

    public function testBucketCompilerMixedAndOrUsesOrConnector(): void
    {
        // Post-B26 fix: when both buckets are non-empty, the AND-bucket and
        // the OR-bucket are joined with " OR " — SQL precedence
        // (AND > OR) gives "a AND b OR c" the parse "(a AND b) OR c",
        // which matches the natural reading of where(a).orWhere(c).
        $structure = ['where' => ['AND' => ['a = 1', 'b = 2'], 'OR' => ['c = 3']]];
        $this->assertSame('a = 1 AND b = 2 OR c = 3', BucketCompiler::compile($structure, 'where'));
    }

    public function testBucketCompilerSimpleMixedAndOr(): void
    {
        $structure = ['where' => ['AND' => ['a = 1'], 'OR' => ['b = 2']]];
        $this->assertSame('a = 1 OR b = 2', BucketCompiler::compile($structure, 'where'));
    }
}
