<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Helper;

use InitORM\QueryBuilder\RawQuery;

/**
 * Stateless helpers that tell the clause builders whether a given user-provided
 * value should be treated as something to inline into the SQL verbatim (a
 * placeholder, a column reference, a SQL function call, a {@see RawQuery}, or
 * an integer literal) versus something that must be added to the parameter bag
 * and replaced with a generated placeholder.
 */
final class SqlValueDetector
{
    /**
     * True if the value is a PDO placeholder token: either the positional "?"
     * or a named ":foo" / ":foo_1" style binding.
     */
    public static function isSqlParameter(mixed $value): bool
    {
        return is_string($value) && ($value === '?' || preg_match('/^:\w+$/', $value) === 1);
    }

    /**
     * True if the value can be inlined verbatim — a PDO placeholder, a dotted
     * column reference (table.column), a parameterless function call (NOW()),
     * a {@see RawQuery}, or an integer literal.
     */
    public static function isSqlParameterOrFunction(mixed $value): bool
    {
        if ($value instanceof RawQuery) {
            return true;
        }

        if (is_int($value)) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return $value === '?'
            || preg_match('/^:\w+$/', $value) === 1
            || preg_match('/^[a-zA-Z_]+[.]+[a-zA-Z_]+$/', $value) === 1
            || preg_match('/^[a-zA-Z_]+\(\)$/', $value) === 1;
    }

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
