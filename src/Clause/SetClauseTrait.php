<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Clause;

use InitORM\QueryBuilder\Helper\SqlValueDetector;
use InitORM\QueryBuilder\RawQuery;

/**
 * SET-clause builders, used by INSERT and UPDATE compilers. Each call appends
 * a row's worth of column/value pairs to {@code structure['set']}; multiple
 * calls produce batch INSERT / UPDATE shapes.
 */
trait SetClauseTrait
{
    public function set(RawQuery|array|string $column, mixed $value = null, bool $strict = true): static
    {
        return $this->addSet($column, $value, $strict);
    }

    public function addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true): static
    {
        unset($strict); // reserved for future use; kept for backwards-compatible signature

        if (is_array($column) && $value === null) {
            $row = [];
            foreach ($column as $name => $entry) {
                $name = (string) $name;
                $entry = SqlValueDetector::isSqlParameterOrFunction($entry)
                    ? $entry
                    : $this->parameters->add($name, $entry);
                $name = $this->driver->escapeIdentifier($name);
                $row[$name] = $entry;
            }
            $this->structure['set'][] = $row;

            return $this;
        }

        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $column = (string) $column;
        $value = SqlValueDetector::isSqlParameterOrFunction($value)
            ? $value
            : $this->parameters->add($column, $value);

        $this->structure['set'][][$column] = $value;

        return $this;
    }
}
