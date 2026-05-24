<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

/**
 * Cover every projection helper in {@see \InitORM\QueryBuilder\Clause\SelectClauseTrait}
 * — aggregate functions, string functions, COALESCE, AS, DISTINCT and the
 * substring / left / right / mid helpers.
 */
class SelectHelpersTest extends AbstractQueryBuilderUnit
{
    /**
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function singleArgProjectionProvider(): array
    {
        return [
            'count'            => ['selectCount',         'id',   'COUNT(id)'],
            'countDistinct'    => ['selectCountDistinct', 'id',   'COUNT(DISTINCT id)'],
            'max'              => ['selectMax',           'age',  'MAX(age)'],
            'min'              => ['selectMin',           'age',  'MIN(age)'],
            'avg'              => ['selectAvg',           'age',  'AVG(age)'],
            'sum'              => ['selectSum',           'amt',  'SUM(amt)'],
            'upper'            => ['selectUpper',         'name', 'UPPER(name)'],
            'lower'            => ['selectLower',         'name', 'LOWER(name)'],
            'length'           => ['selectLength',        'bio',  'LENGTH(bio)'],
            'distinct'         => ['selectDistinct',      'name', 'DISTINCT(name)'],
        ];
    }

    /**
     * @dataProvider singleArgProjectionProvider
     */
    public function testSingleArgumentProjection(string $method, string $column, string $expectedFragment): void
    {
        $this->db->{$method}($column);
        $this->db->from('users');
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString($expectedFragment, $sql);
    }

    /**
     * @dataProvider singleArgProjectionProvider
     */
    public function testSingleArgumentProjectionWithAlias(string $method, string $column, string $expectedFragment): void
    {
        $this->db->{$method}($column, 'alias');
        $this->db->from('users');
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString($expectedFragment . ' AS alias', $sql);
    }

    public function testSelectAsEmitsAsKeyword(): void
    {
        $this->db->selectAs('name', 'username')->from('users');
        $expected = 'SELECT name AS username FROM users WHERE 1';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testSelectConcatJoinsColumnsWithCommas(): void
    {
        $this->db->selectConcat(['first_name', 'last_name'], 'full_name')->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('CONCAT(first_name, last_name) AS full_name', $sql);
    }

    public function testSelectCoalesceWithNumericDefaultInlinesDirectly(): void
    {
        $this->db->selectCoalesce('view_count', 0, 'views')->from('post');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('COALESCE(view_count, 0) AS views', $sql);
    }

    public function testSelectCoalesceWithNonNumericStringDefaultEscapesAsIdentifier(): void
    {
        // GenericDriver doesn't actually quote, but the escapeIdentifier
        // pass-through is still exercised.
        $this->db->selectCoalesce('a', 'fallback_col')->from('post');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('COALESCE(a, fallback_col)', $sql);
    }

    public function testSelectMidEmitsMidFunction(): void
    {
        $this->db->selectMid('name', 1, 5, 'first5')->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('MID(name, 1, 5) AS first5', $sql);
    }

    public function testSelectMidWithoutAlias(): void
    {
        $this->db->selectMid('name', 1, 5)->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('MID(name, 1, 5)', $sql);
        $this->assertStringNotContainsString(' AS ', $sql);
    }

    public function testSelectLeftEmitsLeftFunction(): void
    {
        $this->db->selectLeft('name', 3, 'prefix')->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('LEFT(name, 3) AS prefix', $sql);
    }

    public function testSelectLeftWithoutAlias(): void
    {
        $this->db->selectLeft('name', 3)->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('LEFT(name, 3)', $sql);
    }

    public function testSelectRightEmitsRightFunction(): void
    {
        $this->db->selectRight('name', 3, 'suffix')->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('RIGHT(name, 3) AS suffix', $sql);
    }

    public function testSelectRightWithoutAlias(): void
    {
        $this->db->selectRight('name', 3)->from('users');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('RIGHT(name, 3)', $sql);
    }

    public function testGroupByAcceptsNestedArrayRecursively(): void
    {
        $this->db->select('id')->from('post')
            ->groupBy(['a', ['b', 'c']]);
        $structure = $this->db->exportQB();

        $this->assertSame(['a', 'b', 'c'], $structure['group_by']);
    }

    public function testGroupByDeduplicates(): void
    {
        $this->db->select('id')->from('post')
            ->groupBy('a')
            ->groupBy('a');
        $this->assertSame(['a'], $this->db->exportQB()['group_by']);
    }

    public function testOrderByAcceptsLowerCaseAndUpperCase(): void
    {
        $this->db->from('post')
            ->orderBy('id', 'asc')
            ->orderBy('name', 'DESC');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('ORDER BY id ASC, name DESC', $sql);
    }

    public function testSelectConcatWithRawElementInArray(): void
    {
        $this->db->selectConcat([
            'first_name',
            $this->db->raw("' '"),
            'last_name',
        ], 'full')->from('users');

        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString("CONCAT(first_name, ' ', last_name) AS full", $sql);
    }
}
