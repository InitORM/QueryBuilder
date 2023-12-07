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

class InsertQueryUnitTest extends AbstractQueryBuilderUnit
{

    public function testInsertStatementBuild()
    {
        $this->db->from('post');

        $data = [
            'title'     => 'Post Title',
            'content'   => 'Post Content',
            'author'    => 5,
            'status'    => true,
        ];
        $this->db->set($data);


        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, 5, :status);';
        $this->assertEquals($expected, $this->db->generateInsertQuery());
        $this->db->resetStructure();
    }

    public function testInsertBatchStatementBuild()
    {

        $this->db->from('post');

        $this->db->set([
            'title'     => 'Post Title #1',
            'content'   => 'Post Content #1',
            'author'    => 5,
            'status'    => true,
        ])
            ->set([
                'title'     => 'Post Title #2',
                'content'   => 'Post Content #2',
                'status'    => false,
            ]);

        $expected = 'INSERT INTO post (title, content, author, status) VALUES (:title, :content, 5, :status), (:title_1, :content_1, NULL, :status_1);';
        $this->assertEquals($expected, $this->db->generateBatchInsertQuery());
        $this->db->resetStructure();
    }

}
