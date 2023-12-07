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

class RawQuery
{

    private string $raw;

    public function __construct(mixed $rawQuery)
    {
        $this->set($rawQuery);
    }

    public function __toString(): string
    {
        return $this->get();
    }

    public function set(mixed $rawQuery): self
    {
        if (is_string($rawQuery)) {
            $this->raw = $rawQuery;
        } else if ($rawQuery instanceof Closure) {
            $builder = new QueryBuilder();
            $res = call_user_func_array($rawQuery, [&$builder]);
            if (is_string($res)) {
                $this->raw = $res;
            } else if (is_object($res) && method_exists($res, '__toString')) {
                $this->raw = $res->__toString();
            } else {
                $this->raw = $builder->__toString();
            }
        } else {
            $this->raw = (string)$rawQuery;
        }

        return $this;
    }

    public function get(): string
    {
        return $this->raw ?? '';
    }

    public static function raw($rawQuery): self
    {
        return new self($rawQuery);
    }

}
