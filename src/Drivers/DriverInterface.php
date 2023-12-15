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

interface DriverInterface
{

    /**
     * @param string $string
     * @return string
     */
    public function escapeIdentify(string &$string): string;

    /**
     * @return string|null
     */
    public function getDriver(): ?string;

}
