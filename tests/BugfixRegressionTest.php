<?php
/**
 * InitORM QueryBuilder
 *
 * Regression tests for bugs fixed during the v2.0.0 quality push (Aşama 1).
 *
 * Each test maps to a bug identifier from the review document:
 *   B1/B7 — andWhereNotIn
 *   B2    — orLike / andLike swap
 *   B3    — orBetween parameter order
 *   B4    — RawQuery `use Closure;`
 *   B5/B6 — selfJoin / naturalJoin signatures
 *   B8    — preg_match() === 1
 *   B9    — operator type safety
 *   B16   — startLike / endLike semantics
 *   B20   — UPDATE exception message
 *   B23   — abstract test base classes
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @license     ./LICENSE  MIT
 */

declare(strict_types=1);
namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\QueryBuilder;
use InitORM\QueryBuilder\RawQuery;

class BugfixRegressionTest extends AbstractQueryBuilderUnit
{
    // -----------------------------------------------------------------------
    // B1 / B7 — andWhereNotIn must produce NOT IN (was producing IN)
    // -----------------------------------------------------------------------

    public function testAndWhereNotInProducesNotIn(): void
    {
        $this->db->from('post')
            ->andWhereNotIn('id', [1, 2, 3]);

        $expected = 'SELECT * FROM post WHERE id NOT IN (1, 2, 3)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testAndWhereInProducesIn(): void
    {
        $this->db->from('post')
            ->andWhereIn('id', [1, 2, 3]);

        $expected = 'SELECT * FROM post WHERE id IN (1, 2, 3)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testOrWhereNotInPushesToOrBucket(): void
    {
        // The OR-bucket vs AND-bucket join is handled by __generateStructure;
        // here we only verify dispatch. See B26 (Aşama 2).
        $this->db->from('post')->orWhereNotIn('id', [4, 5]);
        $structure = $this->db->exportQB();

        $this->assertEmpty($structure['where']['AND']);
        $this->assertNotEmpty($structure['where']['OR']);
        $this->assertSame('id NOT IN (4, 5)', $structure['where']['OR'][0]);
    }

    // -----------------------------------------------------------------------
    // B2 — orLike / andLike were swapped
    // -----------------------------------------------------------------------

    public function testAndLikeUsesAndLogical(): void
    {
        $this->db->from('post')
            ->where('status', 1)
            ->andLike('title', 'php');

        $expected = "SELECT * FROM post WHERE status = 1 AND title LIKE :title";
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testOrLikePushesToOrBucket(): void
    {
        // See B26: structure compiler joins AND/OR buckets with " AND ".
        // Here we only verify that orLike dispatches into the OR bucket.
        $this->db->from('post')->orLike('title', 'php');
        $structure = $this->db->exportQB();

        $this->assertEmpty($structure['where']['AND']);
        $this->assertNotEmpty($structure['where']['OR']);
        $this->assertSame('title LIKE :title', $structure['where']['OR'][0]);
    }

    // -----------------------------------------------------------------------
    // B3 — orBetween parameter order was broken
    //
    // Note: the existing structure compiler joins the AND-bucket and the
    // OR-bucket with " AND " (see QueryBuilder::__generateStructure). That
    // is the established behavior — these tests therefore exercise that the
    // OR clause is dispatched into the OR bucket (was previously corrupted
    // to garbage) and that integer values are inlined as expected.
    // -----------------------------------------------------------------------

    public function testOrBetweenPushesToOrBucket(): void
    {
        $this->db->from('post')->orBetween('id', 10, 20);
        $structure = $this->db->exportQB();

        $this->assertEmpty($structure['where']['AND']);
        $this->assertNotEmpty($structure['where']['OR']);
        $this->assertSame('id BETWEEN 10 AND 20', $structure['where']['OR'][0]);
    }

    public function testAndBetweenPushesToAndBucket(): void
    {
        $this->db->from('post')->andBetween('id', 10, 20);
        $structure = $this->db->exportQB();

        $this->assertNotEmpty($structure['where']['AND']);
        $this->assertEmpty($structure['where']['OR']);
        $this->assertSame('id BETWEEN 10 AND 20', $structure['where']['AND'][0]);
    }

    public function testOrNotBetweenPushesToOrBucket(): void
    {
        $this->db->from('post')->orNotBetween('id', 10, 20);
        $structure = $this->db->exportQB();

        $this->assertEmpty($structure['where']['AND']);
        $this->assertNotEmpty($structure['where']['OR']);
        $this->assertSame('id NOT BETWEEN 10 AND 20', $structure['where']['OR'][0]);
    }

    public function testOrBetweenParametersForStringBounds(): void
    {
        $this->db->from('post')->orBetween('date', '2026-01-01', '2026-12-31');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame('2026-01-01', $params[':date']);
        $this->assertSame('2026-12-31', $params[':date_1']);
    }

    // -----------------------------------------------------------------------
    // B4 — RawQuery::set() must handle Closure arguments
    // -----------------------------------------------------------------------

    public function testRawQueryAcceptsClosure(): void
    {
        $raw = new RawQuery(function (QueryBuilder $qb) {
            $qb->select('id')->from('users')->where('active', 1);
        });

        $expected = 'SELECT id FROM users WHERE active = 1';
        $this->assertEquals($expected, (string) $raw);
    }

    public function testRawQueryClosureCanReturnString(): void
    {
        $raw = new RawQuery(function () {
            return 'NOW()';
        });

        $this->assertEquals('NOW()', (string) $raw);
    }

    // -----------------------------------------------------------------------
    // B5 / B6 — selfJoin null guard + naturalJoin signature
    // -----------------------------------------------------------------------

    public function testNaturalJoinDoesNotRequireOnStatement(): void
    {
        $this->db->select('*')
            ->from('orders')
            ->naturalJoin('customers');

        $expected = 'SELECT * FROM orders NATURAL JOIN customers WHERE 1';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testSelfJoinRejectsNullViaTypeSystem(): void
    {
        // PHP's type system already enforces non-null for $onStmt.
        $this->expectException(\TypeError::class);
        /** @noinspection PhpStrictTypeCheckingInspection */
        $this->db->selfJoin('user', null);
    }

    // -----------------------------------------------------------------------
    // B16 — startLike / endLike pattern semantics
    // -----------------------------------------------------------------------

    public function testStartLikeMatchesStringsBeginningWithValue(): void
    {
        $this->db->from('user')->startLike('name', 'Mu');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        // startLike('Mu') must produce LIKE 'Mu%'
        $this->assertEquals('Mu%', $params[':name']);
    }

    public function testEndLikeMatchesStringsEndingWithValue(): void
    {
        $this->db->from('user')->endLike('name', 'AK');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        // endLike('AK') must produce LIKE '%AK'
        $this->assertEquals('%AK', $params[':name']);
    }

    public function testLikeBothBookendsWithWildcards(): void
    {
        $this->db->from('user')->like('name', 'fak');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertEquals('%fak%', $params[':name']);
    }

    public function testNotStartLikePatternIsValuePercent(): void
    {
        $this->db->from('user')->notStartLike('name', 'Mu');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertEquals('Mu%', $params[':name']);
    }

    public function testNotEndLikePatternIsPercentValue(): void
    {
        $this->db->from('user')->notEndLike('name', 'AK');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertEquals('%AK', $params[':name']);
    }

    public function testStartLikeProducesNotLikeKeywordOnlyForNotVariant(): void
    {
        $this->db->from('user')->startLike('name', 'A');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString(' LIKE ', $sql);
        $this->assertStringNotContainsString(' NOT LIKE ', $sql);
    }

    public function testNotStartLikeIncludesNotLikeKeyword(): void
    {
        $this->db->from('user')->notStartLike('name', 'A');
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString(' NOT LIKE ', $sql);
    }

    // -----------------------------------------------------------------------
    // B9 / B27 — operator type safety + strict in_array for the value-shortcut
    // -----------------------------------------------------------------------

    public function testWhereShortcutWithIntegerValue(): void
    {
        $this->db->from('post')->where('id', 5);
        $expected = 'SELECT * FROM post WHERE id = 5';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testWhereShortcutWithBoolTrueValue(): void
    {
        // Prior to B27, in_array(true, [...string ops...]) loose-matched
        // and skipped the swap, producing "WHERE active" with no comparison.
        $this->db->from('post')->where('active', true);
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString('active = :active', $sql);
        $this->assertSame(true, $this->db->getParameter()->all()[':active']);
    }

    public function testWhereShortcutWithBoolFalseValue(): void
    {
        $this->db->from('post')->where('active', false);
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString('active = :active', $sql);
        $this->assertSame(false, $this->db->getParameter()->all()[':active']);
    }

    public function testWhereShortcutWithZeroValue(): void
    {
        $this->db->from('post')->where('priority', 0);
        $expected = 'SELECT * FROM post WHERE priority = 0';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    // -----------------------------------------------------------------------
    // B20 — UPDATE missing-data exception text
    // -----------------------------------------------------------------------

    public function testUpdateWithoutSetThrowsUpdateMessage(): void
    {
        $this->db->from('post')->where('id', 1);

        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('The data set for the update could not be found.');
        $this->db->generateUpdateQuery();
    }

    // -----------------------------------------------------------------------
    // B23 — abstract test bases
    // -----------------------------------------------------------------------

    public function testAbstractQueryBuilderUnitIsAbstract(): void
    {
        $ref = new \ReflectionClass(AbstractQueryBuilderUnit::class);
        $this->assertTrue($ref->isAbstract());
    }

    public function testAbstractQueryBuilderDriverUnitIsAbstract(): void
    {
        $ref = new \ReflectionClass(AbstractQueryBuilderDriverUnit::class);
        $this->assertTrue($ref->isAbstract());
    }
}
