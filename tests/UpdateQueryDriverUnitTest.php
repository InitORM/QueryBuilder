<?php
/**
 * InitORM QueryBuilder
 *
 * This file is part of InitORM QueryBuilder.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2023 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     1.0.1
 * @link        https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);
namespace Test\InitORM\QueryBuilder;

class UpdateQueryDriverUnitTest extends AbstractQueryBuilderDriverUnit
{

    public function testUpdateStatementBuild()
    {

        $this->db->from('post')
            ->where('status', '=', true)
            ->limit(5);

        $data = [
            'title'     => 'New Title',
            'status'    => false,
        ];
        $this->db->set($data);

        $expected = 'UPDATE `post` SET `title` = :title, `status` = :status_1 WHERE `status` = :status LIMIT 5';

        $this->assertEquals($expected, $this->db->generateUpdateQuery());
        $this->db->resetStructure();
    }

    public function testUpdateBatchStatementBuild()
    {

        $this->db->from('post')
            ->where('status', '=', true);

        $this->db->set([
            'id'        => 5,
            'title'     => 'New Title #5',
            'content'   => 'New Content #5',
        ])->set([
            'id'        => 10,
            'title'     => 'New Title #10',
        ]);

        $expected = 'UPDATE `post` SET `title` = CASE WHEN `id` = 5 THEN :title WHEN `id` = 10 THEN :title_1 ELSE `title` END, `content` = CASE WHEN `id` = 5 THEN :content ELSE `content` END WHERE `status` = :status AND `id` IN (5, 10)';

        $this->assertEquals($expected, $this->db->generateUpdateBatchQuery('id'));
        $this->db->resetStructure();
    }

}
