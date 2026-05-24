<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Helper;

/**
 * Joins the AND / OR sub-lists of a WHERE / HAVING / ON bucket into a single
 * SQL fragment. Shared between the runtime clause builders (when a closure
 * pulls a sub-builder's bucket back into the parent) and the
 * {@see \InitORM\QueryBuilder\Compiler\AbstractCompiler}.
 */
final class BucketCompiler
{
    /**
     * Returns null if both AND and OR sub-lists are empty.
     *
     * AND-clauses are joined with " AND ", OR-clauses with " OR ", and when
     * both sub-lists are non-empty they are concatenated with " OR " — so a
     * chain like {@code where(a).orWhere(b)} compiles to {@code a OR b}
     * (relying on SQL's usual `AND > OR` precedence for the
     * {@code a AND b OR c → (a AND b) OR c} parse).
     *
     * @param array<string, mixed> $structure
     */
    public static function compile(array $structure, string $key): ?string
    {
        $isAndEmpty = empty($structure[$key]['AND']);
        $isOrEmpty = empty($structure[$key]['OR']);
        if ($isOrEmpty && $isAndEmpty) {
            return null;
        }

        return (!$isAndEmpty ? implode(' AND ', $structure[$key]['AND']) : '')
            . (!$isAndEmpty && !$isOrEmpty ? ' OR ' : '')
            . (!$isOrEmpty ? implode(' OR ', $structure[$key]['OR']) : '');
    }

    /** @codeCoverageIgnore */
    private function __construct()
    {
    }
}
