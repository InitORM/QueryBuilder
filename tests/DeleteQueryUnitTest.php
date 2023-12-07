<?php
/**
 * InitORM QueryBuilder
 *
 * This file is part of InitORM QueryBuilder.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2023 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     1.0
 * @link        https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);
namespace Test\InitORM\QueryBuilder;

use Test\InitORM\QueryBuilder\AbstractQueryBuilderUnit;

class DeleteQueryUnitTest extends AbstractQueryBuilderUnit
{
    public function testDeleteStatementBuild()
    {

        $this->db->from('post')
            ->where('authorId', '=', 5)
            ->limit(100);

        $expected = 'DELETE FROM post WHERE authorId = 5 LIMIT 100';

        $this->assertEquals($expected, $this->db->generateDeleteQuery());
        $this->db->resetStructure();
    }
}