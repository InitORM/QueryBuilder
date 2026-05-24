<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Clause;

use InitORM\QueryBuilder\Exceptions\QueryBuilderInvalidArgumentException;
use InitORM\QueryBuilder\Operator\Operators;
use InitORM\QueryBuilder\RawQuery;

/**
 * Projection / ordering / pagination helpers. All select* sugar lives here:
 * scalar projections, aggregate functions, string functions, COALESCE,
 * DISTINCT, GROUP BY, ORDER BY, LIMIT and OFFSET.
 */
trait SelectClauseTrait
{
    public function select(string|RawQuery ...$columns): static
    {
        foreach ($columns as $column) {
            if (is_string($column)) {
                $column = $this->driver->escapeIdentifier($column);
            }
            $this->structure['select'][] = (string) $column;
        }

        return $this;
    }

    public function clearSelect(): static
    {
        $this->structure['select'] = [];

        return $this;
    }

    public function selectCount(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('COUNT', $column, $alias);
    }

    public function selectCountDistinct(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('COUNT(DISTINCT ', $column, $alias, ')');
    }

    public function selectMax(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('MAX', $column, $alias);
    }

    public function selectMin(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('MIN', $column, $alias);
    }

    public function selectAvg(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('AVG', $column, $alias);
    }

    public function selectAs(RawQuery|string $column, string $alias): static
    {
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $this->structure['select'][] = $column . ' AS ' . $this->driver->escapeIdentifier($alias);

        return $this;
    }

    public function selectUpper(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('UPPER', $column, $alias);
    }

    public function selectLower(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('LOWER', $column, $alias);
    }

    public function selectLength(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('LENGTH', $column, $alias);
    }

    public function selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null): static
    {
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $this->structure['select'][] = 'MID(' . $column . ', ' . $offset . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this;
    }

    public function selectLeft(RawQuery|string $column, int $length, ?string $alias = null): static
    {
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $this->structure['select'][] = 'LEFT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this;
    }

    public function selectRight(RawQuery|string $column, int $length, ?string $alias = null): static
    {
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $this->structure['select'][] = 'RIGHT(' . $column . ', ' . $length . ')'
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this;
    }

    public function selectDistinct(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('DISTINCT', $column, $alias);
    }

    public function selectCoalesce(RawQuery|string $column, mixed $default = '0', ?string $alias = null): static
    {
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        if (is_string($default) && !is_numeric($default)) {
            $default = $this->driver->escapeIdentifier($default);
        }
        $this->structure['select'][] = 'COALESCE(' . $column . ', ' . $default . ')'
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this;
    }

    public function selectSum(RawQuery|string $column, ?string $alias = null): static
    {
        return $this->pushSelectFunction('SUM', $column, $alias);
    }

    public function selectConcat(array $columns, ?string $alias = null): static
    {
        $escaped = [];
        foreach ($columns as $column) {
            if (is_string($column)) {
                $column = $this->driver->escapeIdentifier($column);
            }
            $escaped[] = (string) $column;
        }
        $this->structure['select'][] = 'CONCAT(' . implode(', ', $escaped) . ')'
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this;
    }

    public function groupBy(string|RawQuery|array ...$columns): static
    {
        foreach ($columns as $column) {
            if (is_array($column)) {
                $this->groupBy(...$column);
                continue;
            }
            if (is_string($column)) {
                $column = $this->driver->escapeIdentifier($column);
            }
            $value = (string) $column;
            if (!in_array($value, $this->structure['group_by'], true)) {
                $this->structure['group_by'][] = $value;
            }
        }

        return $this;
    }

    /**
     * @throws QueryBuilderInvalidArgumentException
     */
    public function orderBy(RawQuery|string $column, string $soft = 'ASC'): static
    {
        $soft = trim(strtoupper($soft));
        if (!in_array($soft, Operators::SORT_DIRECTIONS, true)) {
            throw new QueryBuilderInvalidArgumentException('It can only sort as ASC or DESC.');
        }
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }

        $orderBy = trim((string) $column) . ' ' . $soft;
        if (!in_array($orderBy, $this->structure['order_by'], true)) {
            $this->structure['order_by'][] = $orderBy;
        }

        return $this;
    }

    public function offset(int $offset = 0): static
    {
        $this->structure['offset'] = (int) abs($offset);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->structure['limit'] = (int) abs($limit);

        return $this;
    }

    /**
     * Generic single-argument SQL function projection — used by COUNT, MAX,
     * MIN, AVG, SUM, UPPER, LOWER, LENGTH, DISTINCT and (with a custom open)
     * COUNT(DISTINCT …).
     */
    private function pushSelectFunction(string $function, RawQuery|string $column, ?string $alias, string $tail = ')'): static
    {
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $open = str_ends_with($function, '(') ? $function : $function . '(';
        $this->structure['select'][] = $open . $column . $tail
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this;
    }
}
