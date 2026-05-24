<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Compile-time edge cases for INSERT, UPDATE and DELETE — the paths that
 * raise {@see QueryBuilderException} as well as the rare "no WHERE clause"
 * shapes.
 */
class CrudEdgeCasesTest extends AbstractQueryBuilderUnit
{
    // ---- INSERT ---------------------------------------------------------

    public function testInsertWithoutSetThrows(): void
    {
        $this->db->from('post');
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('The data set for the insert could not be found.');
        $this->db->generateInsertQuery();
    }

    public function testBatchInsertWithoutSetThrows(): void
    {
        $this->db->from('post');
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('The data set for the insert could not be found.');
        $this->db->generateBatchInsertQuery();
    }

    public function testInsertWithoutTableThrows(): void
    {
        $this->db->set(['name' => 'x']);
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('Table name not found when query.');
        $this->db->generateInsertQuery();
    }

    public function testBatchInsertMissingColumnRowFillsWithNullLiteral(): void
    {
        $this->db->from('post')
            ->set(['title' => 'a', 'body' => 'A body'])
            ->set(['title' => 'b']);

        $sql = $this->db->generateBatchInsertQuery();
        // The second row lacks "body" — must compile to NULL.
        $this->assertStringContainsString('(:title_1, NULL)', $sql);
    }

    // ---- UPDATE ---------------------------------------------------------

    public function testUpdateWithoutSetThrows(): void
    {
        $this->db->from('post')->where('id', 1);
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('The data set for the update could not be found.');
        $this->db->generateUpdateQuery();
    }

    public function testUpdateWithoutWhereCompilesToWhere1(): void
    {
        $this->db->from('post')->set(['title' => 'fresh']);
        $sql = $this->db->generateUpdateQuery();
        $this->assertStringContainsString('WHERE 1', $sql);
    }

    public function testUpdateBatchReferenceColumnMissingThrows(): void
    {
        $this->db->from('post')
            ->set(['id' => 5, 'title' => 'a'])
            ->set(['title' => 'b']); // no "id" — invalid for batch update.

        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('The reference column does not exist in one or more of the set arrays.');
        $this->db->generateUpdateBatchQuery('id');
    }

    public function testUpdateBatchAppendsWhereInFilter(): void
    {
        $this->db->from('post')
            ->set(['id' => 1, 'title' => 'a'])
            ->set(['id' => 2, 'title' => 'b']);

        $sql = $this->db->generateUpdateBatchQuery('id');
        $this->assertStringContainsString('WHERE id IN (1, 2)', $sql);
    }

    // ---- DELETE ---------------------------------------------------------

    public function testDeleteWithoutTableThrows(): void
    {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('Table name not found when query.');
        $this->db->generateDeleteQuery();
    }

    public function testDeleteWithoutWhereCompilesToWhere1(): void
    {
        $this->db->from('post');
        $sql = $this->db->generateDeleteQuery();
        $this->assertEquals('DELETE FROM post WHERE 1', $sql);
    }

    public function testDeleteWithMultipleWhereConditions(): void
    {
        $this->db->from('post')
            ->where('status', 1)
            ->where('author_id', 5);
        $sql = $this->db->generateDeleteQuery();

        $this->assertEquals('DELETE FROM post WHERE status = 1 AND author_id = 5', $sql);
    }

    public function testDeleteWithLimit(): void
    {
        $this->db->from('post')->where('status', 1)->limit(10);
        $sql = $this->db->generateDeleteQuery();

        $this->assertStringContainsString(' LIMIT 10', $sql);
    }

    // ---- SELECT edge cases ---------------------------------------------

    public function testGroupByWithHavingProducesGroupAndHavingClauses(): void
    {
        $this->db->select('author_id')
            ->selectCount('id', 'post_count')
            ->from('post')
            ->groupBy('author_id')
            ->having('post_count', '>', 5);

        $expected = 'SELECT author_id, COUNT(id) AS post_count FROM post WHERE 1 GROUP BY author_id HAVING post_count > 5';
        $this->assertEquals($expected, $this->db->generateSelectQuery());
    }

    public function testSelectWithoutTableThrowsImplicitlyMissingFrom(): void
    {
        // SELECT without FROM compiles to "SELECT * FROM  WHERE 1" — the
        // compiler does not raise here (only the schemaName-required
        // compilers do). Documents the actual behavior.
        $this->db->select('NOW()');
        $sql = $this->db->generateSelectQuery();
        $this->assertEquals('SELECT NOW() FROM  WHERE 1', $sql);
    }

    public function testGenerateSelectQueryWithSelectorAndConditionsShortcut(): void
    {
        $this->db->from('post');
        $sql = $this->db->generateSelectQuery(
            ['id', 'title'],
            ['status' => 1]
        );
        $this->assertEquals('SELECT id, title FROM post WHERE status = 1', $sql);
    }
}
