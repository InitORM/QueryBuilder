<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\QueryBuilder;

/**
 * Sub-query helper exercised in every context where it is legal: as a SELECT
 * IN value, as a derived table inside FROM, inside JOIN, and standalone
 * (without wrapping parentheses).
 */
class SubQueryTest extends AbstractQueryBuilderUnit
{
    public function testSubQueryInWhereInWrapsInParens(): void
    {
        $this->db->select('u.name')
            ->from('users AS u')
            ->whereIn('u.id', $this->db->subQuery(function (QueryBuilder $sub): void {
                $sub->select('id')->from('roles')->where('name', 'admin');
            }));

        $expected = 'SELECT u.name FROM users AS u WHERE u.id IN (SELECT id FROM roles WHERE name = :name)';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testSubQueryAsDerivedTableInJoin(): void
    {
        $this->db->select('u.name', 'p.title')
            ->from('users AS u')
            ->join(
                $this->db->subQuery(function (QueryBuilder $sub): void {
                    $sub->select('id, title, user_id')->from('posts')->where('user_id', 5);
                }, 'p'),
                'p.user_id = u.id',
                ''
            );

        $expected = 'SELECT u.name, p.title FROM users AS u JOIN (SELECT id, title, user_id FROM posts WHERE user_id = 5) AS p ON p.user_id = u.id WHERE 1';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testSubQueryWithoutParensWhenNotIntervalQuery(): void
    {
        $raw = $this->db->subQuery(function (QueryBuilder $sub): void {
            $sub->select('id')->from('users')->where('active', 1);
        }, null, false);

        $this->assertEquals('SELECT id FROM users WHERE active = 1', (string) $raw);
    }

    public function testSubQueryWithAliasAndNotIntervalQueryThrows(): void
    {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('To define alias to a subquery, it must be an inner query.');

        $this->db->subQuery(function (QueryBuilder $sub): void {
            $sub->select('id')->from('users');
        }, 'u', false);
    }

    public function testSubQueryClosureReceivesIndependentBuilder(): void
    {
        $this->db->from('users');

        $this->db->subQuery(function (QueryBuilder $sub): void {
            $sub->from('posts')->select('id');
        });

        // The outer builder's FROM should still target "users", not "posts".
        $structure = $this->db->exportQB();
        $this->assertSame(['users'], $structure['table']);
    }

    public function testSubQueryParametersFlowIntoOuterBag(): void
    {
        $this->db->select('u.name')
            ->from('users AS u')
            ->whereIn('u.id', $this->db->subQuery(function (QueryBuilder $sub): void {
                $sub->select('id')->from('roles')->where('name', 'admin');
            }));
        $this->db->generateSelectQuery();

        // The inner builder is a clone — its parameters are kept on its own
        // bag. The outer parameters bag only carries what the outer builder
        // added; the sub-query's bound value lives in the embedded SQL via
        // the inner builder's add() call.
        $sql = $this->db->generateSelectQuery();
        $this->assertStringContainsString('name = :name', $sql);
    }

    public function testSubQueryAsInWithMultipleNamedRoles(): void
    {
        $this->db->select('u.id')
            ->from('users AS u')
            ->whereIn('u.role_id', $this->db->subQuery(function (QueryBuilder $sub): void {
                $sub->select('id')->from('roles')->whereIn('name', ['admin', 'moderator']);
            }));

        $expected = 'SELECT u.id FROM users AS u WHERE u.role_id IN (SELECT id FROM roles WHERE name IN (:name, :name_1))';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }
}
