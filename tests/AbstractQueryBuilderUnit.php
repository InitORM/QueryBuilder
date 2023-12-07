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

use InitORM\QueryBuilder\QueryBuilderFactory;
use InitORM\QueryBuilder\QueryBuilderInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractQueryBuilderUnit extends TestCase
{

    protected QueryBuilderInterface $db;

    protected function setUp(): void
    {
        $factory = new QueryBuilderFactory();
        $this->db = $factory->createQueryBuilder();
        parent::setUp();
    }

}
