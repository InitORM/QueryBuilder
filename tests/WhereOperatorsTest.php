<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

/**
 * Exercise the WHERE comparison-operator matrix, NULL-check helpers, and the
 * REGEXP / SOUNDEX / FIND_IN_SET families. Generic driver — focuses on the
 * shape of the emitted SQL rather than dialect quoting.
 */
class WhereOperatorsTest extends AbstractQueryBuilderUnit
{
    // ---- comparison matrix ----------------------------------------------

    /**
     * @return array<string, array{0:string, 1:string}>
     */
    public static function comparisonOperatorProvider(): array
    {
        return [
            'eq'  => ['=',  '='],
            'neq' => ['!=', '!='],
            'gt'  => ['>',  '>'],
            'lt'  => ['<',  '<'],
            'gte' => ['>=', '>='],
            'lte' => ['<=', '<='],
            'ne2' => ['<>', '<>'],
        ];
    }

    /**
     * @dataProvider comparisonOperatorProvider
     */
    public function testComparisonOperatorsCompileToColOpPlaceholder(string $operator, string $emitted): void
    {
        $this->db->from('user')->where('age', $operator, 18);

        $expected = "SELECT * FROM user WHERE age {$emitted} 18";
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndWhereChainsWithExplicitAndConnector(): void
    {
        $this->db->from('user')
            ->where('age', '>=', 18)
            ->andWhere('country', 'TR');

        $expected = 'SELECT * FROM user WHERE age >= 18 AND country = :country';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testOrWherePushesToOrBucket(): void
    {
        $this->db->from('user')->orWhere('email', 'a@b.test');
        $structure = $this->db->exportQB();

        $this->assertEmpty($structure['where']['AND']);
        $this->assertNotEmpty($structure['where']['OR']);
    }

    // ---- NULL checks ----------------------------------------------------

    public function testWhereIsNullCompilesToIsNull(): void
    {
        $this->db->from('post')->whereIsNull('deleted_at');
        $expected = 'SELECT * FROM post WHERE deleted_at IS NULL';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testWhereIsNotNullCompilesToIsNotNull(): void
    {
        $this->db->from('post')->whereIsNotNull('published_at');
        $expected = 'SELECT * FROM post WHERE published_at IS NOT NULL';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndOrIsNullVariantsHitCorrectBucket(): void
    {
        $this->db->from('post')
            ->andWhereIsNull('deleted_at')
            ->orWhereIsNull('archived_at');

        $structure = $this->db->exportQB();
        $this->assertSame(['deleted_at IS NULL'], $structure['where']['AND']);
        $this->assertSame(['archived_at IS NULL'], $structure['where']['OR']);
    }

    public function testAndOrIsNotNullVariantsHitCorrectBucket(): void
    {
        $this->db->from('post')
            ->andWhereIsNotNull('published_at')
            ->orWhereIsNotNull('updated_at');

        $structure = $this->db->exportQB();
        $this->assertSame(['published_at IS NOT NULL'], $structure['where']['AND']);
        $this->assertSame(['updated_at IS NOT NULL'], $structure['where']['OR']);
    }

    // ---- REGEXP ---------------------------------------------------------

    public function testRegexpEmitsRegexpKeyword(): void
    {
        $this->db->from('user')->regexp('username', '^[a-z]+$');
        $expected = 'SELECT * FROM user WHERE username REGEXP :username';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndOrRegexpHitCorrectBuckets(): void
    {
        $this->db->from('user')
            ->andRegexp('username', '^[a-z]+$')
            ->orRegexp('email', '@example\\.test$');

        $structure = $this->db->exportQB();
        $this->assertCount(1, $structure['where']['AND']);
        $this->assertCount(1, $structure['where']['OR']);
        $this->assertStringContainsString('REGEXP', $structure['where']['AND'][0]);
        $this->assertStringContainsString('REGEXP', $structure['where']['OR'][0]);
    }

    // ---- SOUNDEX --------------------------------------------------------

    public function testSoundexExpandsToConcatLikeForm(): void
    {
        $this->db->from('user')->soundex('name', 'Robert');
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString('SOUNDEX(name)', $sql);
        $this->assertStringContainsString("TRIM(TRAILING '0' FROM SOUNDEX(:name))", $sql);
    }

    public function testAndOrSoundexHitCorrectBuckets(): void
    {
        $this->db->from('user')
            ->andSoundex('name', 'Robert')
            ->orSoundex('surname', 'Smith');

        $structure = $this->db->exportQB();
        $this->assertCount(1, $structure['where']['AND']);
        $this->assertCount(1, $structure['where']['OR']);
    }

    // ---- FIND_IN_SET ----------------------------------------------------

    public function testFindInSetEmitsFunctionCallForm(): void
    {
        $this->db->from('user')->findInSet('roles', $this->db->raw(':role'));
        $expected = 'SELECT * FROM user WHERE FIND_IN_SET(:role, roles)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testNotFindInSetPrependsNot(): void
    {
        $this->db->from('user')->notFindInSet('roles', $this->db->raw(':role'));
        $expected = 'SELECT * FROM user WHERE NOT FIND_IN_SET(:role, roles)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndOrFindInSetVariantsHitCorrectBuckets(): void
    {
        $this->db->from('user')
            ->andFindInSet('roles', $this->db->raw(':r1'))
            ->orFindInSet('groups', $this->db->raw(':g1'));

        $structure = $this->db->exportQB();
        $this->assertCount(1, $structure['where']['AND']);
        $this->assertCount(1, $structure['where']['OR']);
    }

    public function testAndOrNotFindInSetVariantsHitCorrectBuckets(): void
    {
        $this->db->from('user')
            ->andNotFindInSet('roles', $this->db->raw(':r1'))
            ->orNotFindInSet('groups', $this->db->raw(':g1'));

        $structure = $this->db->exportQB();
        $this->assertCount(1, $structure['where']['AND']);
        $this->assertCount(1, $structure['where']['OR']);
        $this->assertStringStartsWith('NOT FIND_IN_SET', $structure['where']['AND'][0]);
        $this->assertStringStartsWith('NOT FIND_IN_SET', $structure['where']['OR'][0]);
    }

    /**
     * B28: raw string values must be parameterized, not inlined.
     */
    public function testFindInSetParameterizesRawStringValue(): void
    {
        $this->db->from('user')->findInSet('roles', 'admin');
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertEquals('SELECT * FROM user WHERE FIND_IN_SET(:roles, roles)', $sql);
        $this->assertSame('admin', $params[':roles']);
    }

    /**
     * B28: RawQuery placeholder values must be passed through verbatim.
     */
    public function testFindInSetKeepsRawQueryPlaceholderVerbatim(): void
    {
        $this->db->from('user')->findInSet('roles', $this->db->raw(':role'));
        $sql = $this->db->generateSelectQuery();

        $this->assertEquals('SELECT * FROM user WHERE FIND_IN_SET(:role, roles)', $sql);
    }

    // ---- value shortcut + non-string operator ---------------------------

    public function testInvalidLogicalThrows(): void
    {
        $this->expectException(\InitORM\QueryBuilder\Exceptions\QueryBuilderInvalidArgumentException::class);
        $this->db->where('a', '=', 1, 'XOR');
    }

    public function testValueShortcutSwapsOperatorAndValue(): void
    {
        $this->db->from('post')->where('id', 'php');
        $expected = 'SELECT * FROM post WHERE id = :id';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }
}
