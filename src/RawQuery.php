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
 *
 * Three input forms are accepted:
 *   - a string — used as-is;
 *   - a {@see Closure} — invoked with a fresh {@see QueryBuilder}; the
 *     closure may either return the SQL string itself or build it up via
 *     the supplied builder (the resulting SQL is then captured via
 *     {@code __toString()});
 *   - any other value — cast to string.
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

    /**
     * Replace the stored SQL fragment. See class docblock for accepted input
     * forms.
     */
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

    /**
     * The stored SQL fragment. The constructor always calls {@see self::set()},
     * which always assigns `$this->raw` in every branch, so the property is
     * guaranteed to be initialised by the time this getter runs.
     */
    public function get(): string
    {
        return $this->raw;
    }

    /**
     * Convenience static factory — equivalent to {@code new RawQuery($rawQuery)}.
     */
    public static function raw(mixed $rawQuery): self
    {
        return new self($rawQuery);
    }
}
