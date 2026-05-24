<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

/**
 * Exhaustive coverage of the BETWEEN, IN and LIKE families. Beyond what
 * {@see BugfixRegressionTest} already exercises, this file covers the
 * positive paths for every AND/OR/NOT permutation.
 */
class BetweenInLikeTest extends AbstractQueryBuilderUnit
{
    // ---- BETWEEN --------------------------------------------------------

    public function testBetweenWithSeparateBounds(): void
    {
        $this->db->from('post')->between('id', 10, 20);
        $expected = 'SELECT * FROM post WHERE id BETWEEN 10 AND 20';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testBetweenWithBoundsArray(): void
    {
        $this->db->from('post')->between('id', [10, 20]);
        $expected = 'SELECT * FROM post WHERE id BETWEEN 10 AND 20';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testBetweenWithStringBoundsParameterizes(): void
    {
        $this->db->from('post')->between('date', '2026-01-01', '2026-12-31');
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertStringContainsString('BETWEEN :date AND :date_1', $sql);
        $this->assertSame('2026-01-01', $params[':date']);
        $this->assertSame('2026-12-31', $params[':date_1']);
    }

    public function testBetweenMixedRawAndStringInlinesFunctionsAndParameterizesStrings(): void
    {
        $this->db->from('post')->between('date', '2026-01-01', $this->db->raw('NOW()'));
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString('BETWEEN :date AND NOW()', $sql);
    }

    public function testNotBetweenInsertsNotKeyword(): void
    {
        $this->db->from('post')->notBetween('id', 10, 20);
        $expected = 'SELECT * FROM post WHERE id NOT BETWEEN 10 AND 20';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndBetweenAliasMatchesBetween(): void
    {
        $this->db->from('post')->andBetween('id', 1, 5);
        $expected = 'SELECT * FROM post WHERE id BETWEEN 1 AND 5';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndNotBetweenAliasMatchesNotBetween(): void
    {
        $this->db->from('post')->andNotBetween('id', 1, 5);
        $expected = 'SELECT * FROM post WHERE id NOT BETWEEN 1 AND 5';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    // ---- IN -------------------------------------------------------------

    public function testWhereInWithNumericArray(): void
    {
        $this->db->from('user')->whereIn('id', [1, 2, 3]);
        $expected = 'SELECT * FROM user WHERE id IN (1, 2, 3)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testWhereInWithStringArrayParameterizes(): void
    {
        $this->db->from('user')->whereIn('country', ['TR', 'US', 'DE']);
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertStringContainsString('country IN (:country, :country_1, :country_2)', $sql);
        $this->assertSame('TR', $params[':country']);
        $this->assertSame('US', $params[':country_1']);
        $this->assertSame('DE', $params[':country_2']);
    }

    public function testWhereInDeduplicatesArray(): void
    {
        $this->db->from('user')->whereIn('id', [1, 2, 2, 3, 1]);
        $expected = 'SELECT * FROM user WHERE id IN (1, 2, 3)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testWhereNotInPrependsNot(): void
    {
        $this->db->from('user')->whereNotIn('id', [1, 2]);
        $expected = 'SELECT * FROM user WHERE id NOT IN (1, 2)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testWhereInAcceptsRawSubQueryVerbatim(): void
    {
        $this->db->from('user')->whereIn('id', $this->db->raw('(SELECT user_id FROM bans)'));
        $expected = 'SELECT * FROM user WHERE id IN (SELECT user_id FROM bans)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testOrWhereInPushesToOrBucket(): void
    {
        $this->db->from('user')->orWhereIn('id', [1, 2, 3]);
        $structure = $this->db->exportQB();

        $this->assertEmpty($structure['where']['AND']);
        $this->assertSame(['id IN (1, 2, 3)'], $structure['where']['OR']);
    }

    public function testAndWhereInPushesToAndBucket(): void
    {
        $this->db->from('user')->andWhereIn('id', [1, 2]);
        $structure = $this->db->exportQB();

        $this->assertSame(['id IN (1, 2)'], $structure['where']['AND']);
        $this->assertEmpty($structure['where']['OR']);
    }

    // ---- LIKE family ----------------------------------------------------

    public function testLikeBothBookendsWithWildcards(): void
    {
        $this->db->from('user')->like('name', 'fak');
        $params = $this->db->getParameter()->all();
        $this->db->generateSelectQuery();
        $this->assertSame('%fak%', $params[':name']);
    }

    public function testNotLikeIncludesNotKeyword(): void
    {
        $this->db->from('user')->notLike('name', 'spam');
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertStringContainsString('name NOT LIKE :name', $sql);
        $this->assertSame('%spam%', $params[':name']);
    }

    public function testStartLikeWildcardSuffixOnly(): void
    {
        $this->db->from('user')->startLike('name', 'Mu');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();
        $this->assertSame('Mu%', $params[':name']);
    }

    public function testEndLikeWildcardPrefixOnly(): void
    {
        $this->db->from('user')->endLike('name', 'AK');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();
        $this->assertSame('%AK', $params[':name']);
    }

    public function testNotStartLikeWildcardSuffixOnlyWithNotKeyword(): void
    {
        $this->db->from('user')->notStartLike('name', 'Mu');
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertStringContainsString(' NOT LIKE ', $sql);
        $this->assertSame('Mu%', $params[':name']);
    }

    public function testNotEndLikeWildcardPrefixOnlyWithNotKeyword(): void
    {
        $this->db->from('user')->notEndLike('name', 'AK');
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertStringContainsString(' NOT LIKE ', $sql);
        $this->assertSame('%AK', $params[':name']);
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:string, 3:string}>
     */
    public static function likeMethodProvider(): array
    {
        return [
            //                  method,            value, expected pattern, must contain
            'orLike'         => ['orLike',         'php', '%php%',          ' LIKE '],
            'andLike'        => ['andLike',        'php', '%php%',          ' LIKE '],
            'orNotLike'      => ['orNotLike',      'php', '%php%',          ' NOT LIKE '],
            'andNotLike'     => ['andNotLike',     'php', '%php%',          ' NOT LIKE '],
            'orStartLike'    => ['orStartLike',    'Mu',  'Mu%',            ' LIKE '],
            'andStartLike'   => ['andStartLike',   'Mu',  'Mu%',            ' LIKE '],
            'orStartNotLike' => ['orStartNotLike', 'Mu',  'Mu%',            ' NOT LIKE '],
            'andStartNotLike' => ['andStartNotLike','Mu',  'Mu%',            ' NOT LIKE '],
            'orEndLike'      => ['orEndLike',      'AK',  '%AK',            ' LIKE '],
            'andEndLike'     => ['andEndLike',     'AK',  '%AK',            ' LIKE '],
            'orEndNotLike'   => ['orEndNotLike',   'AK',  '%AK',            ' NOT LIKE '],
            'andEndNotLike'  => ['andEndNotLike',  'AK',  '%AK',            ' NOT LIKE '],
        ];
    }

    /**
     * @dataProvider likeMethodProvider
     */
    public function testLikeAliasesProduceExpectedPatternAndKeyword(string $method, string $value, string $expectedPattern, string $mustContain): void
    {
        $this->db->from('user')->{$method}('name', $value);
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertStringContainsString($mustContain, $sql);
        $this->assertSame($expectedPattern, $params[':name']);
    }
}
