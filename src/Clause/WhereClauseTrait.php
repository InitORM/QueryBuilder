<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Clause;

use Closure;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\Exceptions\QueryBuilderInvalidArgumentException;
use InitORM\QueryBuilder\Helper\BucketCompiler;
use InitORM\QueryBuilder\Helper\SqlValueDetector;
use InitORM\QueryBuilder\Operator\Operators;
use InitORM\QueryBuilder\RawQuery;

/**
 * WHERE / HAVING / ON clause builders together with their syntactic sugar
 * (BETWEEN, IN, LIKE family, NULL checks, REGEXP, SOUNDEX, FIND_IN_SET) and
 * the high-level helpers {@see self::group()} and {@see self::subQuery()}.
 */
trait WhereClauseTrait
{
    public function where(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static
    {
        $this->prepareLogical($operator, $value, $logical);
        $this->structure['where'][$logical][] = $this->prepareStatement($column, $operator, $value);

        return $this;
    }

    public function having(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static
    {
        $this->prepareLogical($operator, $value, $logical);
        $this->structure['having'][$logical][] = $this->prepareStatement($column, $operator, $value);

        return $this;
    }

    public function on(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static
    {
        $this->prepareLogical($operator, $value, $logical);

        if (is_string($value) && str_contains($value, '.')) {
            $value = $this->raw($this->driver->escapeIdentifier($value));
        }
        $this->structure['on'][$logical][] = $this->prepareStatement($column, $operator, $value);

        return $this;
    }

    public function andWhere(RawQuery|string $column, mixed $operator = '=', mixed $value = null): static
    {
        return $this->where($column, $operator, $value);
    }

    public function orWhere(RawQuery|string $column, mixed $operator = '=', mixed $value = null): static
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    public function between(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): static
    {
        $value = (is_array($firstValue) && count($firstValue) === 2 && $lastValue === null)
            ? $firstValue
            : [$firstValue, $lastValue];

        return $this->where($column, 'BETWEEN', $value, $logical);
    }

    public function orBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): static
    {
        return $this->between($column, $firstValue, $lastValue, 'OR');
    }

    public function andBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): static
    {
        return $this->between($column, $firstValue, $lastValue);
    }

    public function notBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): static
    {
        $value = (is_array($firstValue) && count($firstValue) === 2 && $lastValue === null)
            ? $firstValue
            : [$firstValue, $lastValue];

        return $this->where($column, 'NOT BETWEEN', $value, $logical);
    }

    public function orNotBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): static
    {
        return $this->notBetween($column, $firstValue, $lastValue, 'OR');
    }

    public function andNotBetween(RawQuery|string $column, mixed $firstValue = null, mixed $lastValue = null): static
    {
        return $this->notBetween($column, $firstValue, $lastValue);
    }

    public function findInSet(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->where($column, 'FIND_IN_SET', $value, $logical);
    }

    public function andFindInSet(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'FIND_IN_SET', $value);
    }

    public function orFindInSet(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'FIND_IN_SET', $value, 'OR');
    }

    public function notFindInSet(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->where($column, 'NOT FIND_IN_SET', $value, $logical);
    }

    public function andNotFindInSet(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'NOT FIND_IN_SET', $value);
    }

    public function orNotFindInSet(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'NOT FIND_IN_SET', $value, 'OR');
    }

    public function whereIn(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->where($column, 'IN', $value, $logical);
    }

    public function whereNotIn(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->where($column, 'NOT IN', $value, $logical);
    }

    public function orWhereIn(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'IN', $value, 'OR');
    }

    public function orWhereNotIn(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'NOT IN', $value, 'OR');
    }

    public function andWhereIn(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'IN', $value);
    }

    public function andWhereNotIn(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'NOT IN', $value);
    }

    public function regexp(RawQuery|string $column, RawQuery|string $value, string $logical = 'AND'): static
    {
        return $this->where($column, 'REGEXP', $value, $logical);
    }

    public function andRegexp(RawQuery|string $column, RawQuery|string $value): static
    {
        return $this->where($column, 'REGEXP', $value);
    }

    public function orRegexp(RawQuery|string $column, RawQuery|string $value): static
    {
        return $this->where($column, 'REGEXP', $value, 'OR');
    }

    public function soundex(RawQuery|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->where($column, 'SOUNDEX', $value, $logical);
    }

    public function andSoundex(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'SOUNDEX', $value);
    }

    public function orSoundex(RawQuery|string $column, mixed $value = null): static
    {
        return $this->where($column, 'SOUNDEX', $value, 'OR');
    }

    public function whereIsNull(RawQuery|string $column, string $logical = 'AND'): static
    {
        return $this->where($column, 'IS', null, $logical);
    }

    public function orWhereIsNull(RawQuery|string $column): static
    {
        return $this->where($column, 'IS', null, 'OR');
    }

    public function andWhereIsNull(RawQuery|string $column): static
    {
        return $this->where($column, 'IS');
    }

    public function whereIsNotNull(RawQuery|string $column, string $logical = 'AND'): static
    {
        return $this->where($column, 'IS NOT', null, $logical);
    }

    public function orWhereIsNotNull(RawQuery|string $column): static
    {
        return $this->where($column, 'IS NOT', null, 'OR');
    }

    public function andWhereIsNotNull(RawQuery|string $column): static
    {
        return $this->where($column, 'IS NOT');
    }

    public function like(RawQuery|array|string $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): static
    {
        $operator = match (strtolower($type)) {
            'before', 'start' => 'START LIKE',
            'after', 'end'    => 'END LIKE',
            default           => 'LIKE',
        };

        return $this->where($column, $operator, $value, $logical);
    }

    public function orLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): static
    {
        return $this->like($column, $value, $type, 'OR');
    }

    public function andLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): static
    {
        return $this->like($column, $value, $type, 'AND');
    }

    public function notLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): static
    {
        $operator = match (strtolower($type)) {
            'before', 'start' => 'NOT START LIKE',
            'after', 'end'    => 'NOT END LIKE',
            default           => 'NOT LIKE',
        };

        return $this->where($column, $operator, $value, $logical);
    }

    public function orNotLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): static
    {
        return $this->notLike($column, $value, $type, 'OR');
    }

    public function andNotLike(RawQuery|array|string $column, mixed $value = null, string $type = 'both'): static
    {
        return $this->notLike($column, $value, $type);
    }

    public function startLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->like($column, $value, 'before', $logical);
    }

    public function orStartLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->like($column, $value, 'before', 'OR');
    }

    public function andStartLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->like($column, $value, 'before');
    }

    public function notStartLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->notLike($column, $value, 'before', $logical);
    }

    public function orStartNotLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->notLike($column, $value, 'before', 'OR');
    }

    public function andStartNotLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->notLike($column, $value, 'before');
    }

    public function endLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->like($column, $value, 'after', $logical);
    }

    public function orEndLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->like($column, $value, 'after', 'OR');
    }

    public function andEndLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->like($column, $value, 'after');
    }

    public function notEndLike(RawQuery|array|string $column, mixed $value = null, string $logical = 'AND'): static
    {
        return $this->notLike($column, $value, 'after', $logical);
    }

    public function orEndNotLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->notLike($column, $value, 'after', 'OR');
    }

    public function andEndNotLike(RawQuery|array|string $column, mixed $value = null): static
    {
        return $this->notLike($column, $value, 'after');
    }

    /**
     * Build a sub-query (SELECT) using a fresh QueryBuilder. The result is
     * returned as a {@see RawQuery} so it can be embedded in JOINs, IN
     * clauses, etc.
     *
     * @throws QueryBuilderException
     */
    public function subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true): RawQuery
    {
        $sub = $this->clone()->resetStructure();
        $closure($sub);
        if ($alias !== null && $isIntervalQuery !== true) {
            throw new QueryBuilderException('To define alias to a subquery, it must be an inner query.');
        }

        $rawQuery = ($isIntervalQuery ? '(' : '')
            . $sub->generateSelectQuery()
            . ($isIntervalQuery ? ')' : '')
            . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');

        return $this->raw($rawQuery);
    }

    /**
     * Group multiple where/having/on clauses inside parentheses, joined by
     * AND or OR.
     *
     * @throws QueryBuilderException
     */
    public function group(Closure $closure, string $logical = 'AND'): static
    {
        $logical = strtoupper(strtr($logical, Operators::LOGICAL_ALIASES));
        if (!in_array($logical, Operators::LOGICAL, true)) {
            throw new QueryBuilderException('Logical operator OR, AND, && or || it could be.');
        }

        $sub = $this->clone()->resetStructure();
        $closure($sub);
        $subStructure = $sub->exportQB();

        foreach (['where', 'on', 'having'] as $stmt) {
            $statement = BucketCompiler::compile($subStructure, $stmt);
            if (!empty($statement)) {
                $this->structure[$stmt][$logical][] = '(' . $statement . ')';
            }
        }

        return $this;
    }

    public function raw(mixed $rawQuery): RawQuery
    {
        return new RawQuery($rawQuery);
    }

    // ---- internal helpers ------------------------------------------------

    /**
     * Normalize the logical connector and apply the value-shortcut: when no
     * value is supplied and the operator slot was actually a value, swap them.
     *
     * @throws QueryBuilderInvalidArgumentException
     */
    private function prepareLogical(mixed &$operator, mixed &$value, string &$logical): void
    {
        $logical = strtoupper(strtr($logical, Operators::LOGICAL_ALIASES));
        if (!in_array($logical, Operators::LOGICAL, true)) {
            throw new QueryBuilderInvalidArgumentException(
                'Logical operator OR, AND, && or || it could be.'
            );
        }

        if ($value === null && !in_array($operator, Operators::VALUE_SHORTCUT_BYPASS, true)) {
            $value = $operator;
            $operator = '=';
        }
    }

    /**
     * Build a single condition fragment ("col op value", "col IN (…)", etc.).
     */
    private function prepareStatement(mixed $column, mixed $operator, mixed $value): string
    {
        $operator = is_string($operator) ? trim($operator) : '=';
        if (is_string($column)) {
            $column = $this->driver->escapeIdentifier($column);
        }
        $column = (string) $column;

        if ($value !== null && in_array($operator, array_merge(Operators::COMPARISON, Operators::ARITHMETIC), true)) {
            $value = SqlValueDetector::isSqlParameterOrFunction($value)
                ? $value
                : $this->parameters->add($column, $value);

            return $column . ' ' . $operator . ' ' . $value;
        }
        $upperCaseOperator = strtoupper($operator);
        $searchOperator = str_replace([' ', '_'], '', $upperCaseOperator);
        if ($value === null && !in_array($searchOperator, ['IS', 'ISNOT'], true)) {
            return $column;
        }

        switch ($searchOperator) {
            case 'IS':
                return $column . ' IS '
                    . ($value === null
                        ? 'NULL'
                        : (SqlValueDetector::isSqlParameterOrFunction($value)
                            ? $value
                            : $this->parameters->add($column, $value)));
            case 'ISNOT':
                return $column . ' IS NOT '
                    . ($value === null
                        ? 'NULL'
                        : (SqlValueDetector::isSqlParameterOrFunction($value)
                            ? $value
                            : $this->parameters->add($column, $value)));
            case 'LIKE':
            case 'NOTLIKE':
            case 'STARTLIKE':
            case 'NOTSTARTLIKE':
            case 'ENDLIKE':
            case 'NOTENDLIKE':
                if (!SqlValueDetector::isSqlParameter($value)) {
                    $prefix = in_array($searchOperator, Operators::LIKE_PREFIX_WILDCARD, true) ? '%' : '';
                    $suffix = in_array($searchOperator, Operators::LIKE_SUFFIX_WILDCARD, true) ? '%' : '';
                    $value = $prefix . $value . $suffix;
                    $value = $this->parameters->add($column, $value);
                }

                return $column
                    . (in_array($searchOperator, Operators::LIKE_NEGATED, true) ? ' NOT' : '')
                    . ' LIKE ' . $value;
            case 'BETWEEN':
            case 'NOTBETWEEN':
                return $column . ' '
                    . ($searchOperator === 'NOTBETWEEN' ? 'NOT ' : '')
                    . 'BETWEEN '
                    . (SqlValueDetector::isSqlParameterOrFunction($value[0])
                        ? $value[0]
                        : $this->parameters->add($column, $value[0]))
                    . ' AND '
                    . (SqlValueDetector::isSqlParameterOrFunction($value[1])
                        ? $value[1]
                        : $this->parameters->add($column, $value[1]));
            case 'IN':
            case 'NOTIN':
                if (is_array($value)) {
                    $values = [];
                    foreach (array_unique($value) as $item) {
                        if (is_numeric($item)) {
                            $values[] = $item;
                        } else {
                            $values[] = SqlValueDetector::isSqlParameterOrFunction($item)
                                ? $item
                                : $this->parameters->add($column, $item);
                        }
                    }
                    $value = '(' . implode(', ', $values) . ')';
                }

                return $column
                    . ($searchOperator === 'NOTIN' ? ' NOT' : '')
                    . ' IN ' . $value;
            case 'REGEXP':
                return $column . ' REGEXP '
                    . (SqlValueDetector::isSqlParameterOrFunction($value)
                        ? $value
                        : $this->parameters->add($column, $value));
            case 'FINDINSET':
            case 'NOTFINDINSET':
                if (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif (SqlValueDetector::isSqlParameterOrFunction($value)) {
                    $value = $this->parameters->add($column, $value);
                }

                return ($searchOperator === 'NOTFINDINSET' ? 'NOT ' : '')
                    . 'FIND_IN_SET(' . $value . ', ' . $column . ')';
            case 'SOUNDEX':
                if (!SqlValueDetector::isSqlParameterOrFunction($value)) {
                    $value = $this->parameters->add($column, $value);
                }

                return "SOUNDEX(" . $column . ") LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(" . $value . ")), '%')";
            default:
                if ($value === null && preg_match('/([\w_]+)\((.+)\)$/iu', $column, $matches) === 1) {
                    return strtoupper($matches[1]) . '(' . $matches[2] . ')';
                }

                return $column . ' ' . $operator . ' ' . $this->parameters->add($column, $value);
        }
    }
}
