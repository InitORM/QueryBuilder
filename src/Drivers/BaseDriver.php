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

namespace InitORM\QueryBuilder\Drivers;

class BaseDriver implements DriverInterface
{

    protected string $name;

    protected string $escapeChar = '';

    /**
     * @inheritDoc
     */
    public function escapeIdentify(string &$string): string
    {
        if (!empty($this->escapeChar)) {
            $string = preg_replace('/\b(?<!:)(?!(AND|and|OR|or|AS|as|ON|on)\b)([a-zA-Z_][a-zA-Z0-9_]*)\b/',$this->escapeChar . '$0' . $this->escapeChar, str_replace($this->escapeChar, $this->escapeChar . $this->escapeChar, trim($string, $this->escapeChar)));
        }

        return $string;
    }

    /**
     * @inheritDoc
     */
    public function getDriver(): ?string
    {
        return $this->name ?? null;
    }

}
