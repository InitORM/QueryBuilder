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
namespace InitORM\QueryBuilder;

interface ParameterInterface
{

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function set(string $key, mixed $value): self;

    /**
     * @param string|RawQuery $key
     * @param mixed $value
     * @return string
     */
    public function add(RawQuery|string $key, mixed $value): string;


    /**
     * @param array|ParameterInterface ...$arrays
     * @return self
     */
    public function merge(array|ParameterInterface ...$arrays): self;

    /**
     * @param string|null $key
     * @param mixed|null $default
     * @return array|mixed|null
     */
    public function get(?string $key = null, mixed $default = null): mixed;

    /**
     * @return array
     */
    public function all(): array;

    /**
     * @return self
     */
    public function reset(): self;

}
