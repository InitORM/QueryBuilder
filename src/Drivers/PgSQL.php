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

class PgSQL extends BaseDriver
{

    protected string $escapeChar = '"';

    protected string $name = 'pgsql';

    /**
     * @inheritDoc
     */
    public function escapeIdentify(string &$string): string
    {
        parent::escapeIdentify($string);
        //$string = preg_replace('/(?<!:)\b\w*[A-Z]\w*\b/', $this->escapeChar . '$1' . $this->escapeChar, $string);

        return $string;
    }

}
