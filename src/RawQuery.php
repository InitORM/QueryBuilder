<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

use Closure;

/**
 * A SQL fragment that should be inlined verbatim and NOT escaped or
 * parameterized. Use sparingly — values that originate from user input must
 * pass through {@see ParameterInterface::add()} instead.
 */
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
        } elseif ($rawQuery instanceof Closure) {
            $builder = new QueryBuilder();
            $result = $rawQuery($builder);
            if (is_string($result)) {
                $this->raw = $result;
            } elseif (is_object($result) && method_exists($result, '__toString')) {
                $this->raw = $result->__toString();
            } else {
                $this->raw = $builder->__toString();
            }
        } else {
            $this->raw = (string) $rawQuery;
        }

        return $this;
    }

    public function get(): string
    {
        return $this->raw ?? '';
    }

    public static function raw(mixed $rawQuery): self
    {
        return new self($rawQuery);
    }
}
