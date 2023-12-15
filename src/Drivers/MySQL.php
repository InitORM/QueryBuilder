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
namespace InitORM\QueryBuilder\Drivers;

class MySQL extends BaseDriver
{

    protected string $name = 'mysql';

    protected string $escapeChar = '`';

}
