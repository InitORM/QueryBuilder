<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Operator;

/**
 * Centralized operator constants used by the WHERE/HAVING/ON clause builders
 * and compilers. Kept in one place so the recognized SQL operator surface is
 * obvious and easy to extend.
 */
final class Operators
{
    /**
     * Operators that take a column + value on either side. When one of these
     * is supplied, the value-shortcut (where('col', 5)) does not engage.
     */
    public const COMPARISON = [
        '=', '!=', '>', '<', '>=', '<=', '<>',
    ];

    /**
     * Arithmetic operators recognized in WHERE expressions.
     */
    public const ARITHMETIC = [
        '+', '-', '*', '/', '%',
        '+=', '-=', '*=', '/=', '%=', '&=', '^-=', '|*=',
    ];

    /**
     * NULL-check operators. Compiled to "IS NULL" / "IS NOT NULL" when the
     * value is null.
     */
    public const NULL_CHECK = [
        'IS', 'IS NOT',
    ];

    /**
     * The union of operators that bypass the value-shortcut in
     * WhereClauseTrait::prepareLogical().
     */
    public const VALUE_SHORTCUT_BYPASS = [
        'IS', 'IS NOT',
        '=', '!=', '>', '<', '>=', '<=', '<>',
        '+', '-', '*', '/', '%',
        '+=', '-=', '*=', '/=', '%=', '&=', '^-=', '|*=',
    ];

    /**
     * Logical connectors accepted as keys in the structure where/having/on
     * buckets.
     */
    public const LOGICAL = ['AND', 'OR'];

    /**
     * Maps colloquial logical connectors to canonical form.
     */
    public const LOGICAL_ALIASES = [
        '&&' => 'AND',
        '||' => 'OR',
    ];

    /**
     * LIKE-family operators (after stripping spaces/underscores from the
     * user-provided string and uppercasing).
     */
    public const LIKE_FAMILY = [
        'LIKE',
        'NOTLIKE',
        'STARTLIKE',
        'NOTSTARTLIKE',
        'ENDLIKE',
        'NOTENDLIKE',
    ];

    /**
     * LIKE-family operators whose pattern carries a leading "%" wildcard.
     * (Matches strings ending with the supplied value.)
     */
    public const LIKE_PREFIX_WILDCARD = [
        'LIKE', 'NOTLIKE', 'ENDLIKE', 'NOTENDLIKE',
    ];

    /**
     * LIKE-family operators whose pattern carries a trailing "%" wildcard.
     * (Matches strings starting with the supplied value.)
     */
    public const LIKE_SUFFIX_WILDCARD = [
        'LIKE', 'NOTLIKE', 'STARTLIKE', 'NOTSTARTLIKE',
    ];

    /**
     * LIKE-family operators that should be compiled with the "NOT LIKE"
     * keyword.
     */
    public const LIKE_NEGATED = [
        'NOTLIKE', 'NOTSTARTLIKE', 'NOTENDLIKE',
    ];

    /**
     * BETWEEN operators.
     */
    public const BETWEEN = ['BETWEEN', 'NOTBETWEEN'];

    /**
     * IN operators.
     */
    public const IN = ['IN', 'NOTIN'];

    /**
     * FIND_IN_SET operators.
     */
    public const FIND_IN_SET = ['FINDINSET', 'NOTFINDINSET'];

    /**
     * ORDER BY sort directions accepted by orderBy().
     */
    public const SORT_DIRECTIONS = ['ASC', 'DESC'];

    /**
     * Recognize the user-supplied JOIN type aliases that map onto SQL JOIN
     * keywords by the JOIN clause builder.
     */
    public const JOIN_TYPES = [
        'INNER',
        'LEFT',
        'RIGHT',
        'LEFT OUTER',
        'RIGHT OUTER',
        'NATURAL',
        'NATURAL JOIN',
        'SELF',
    ];

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
