<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * Dialect contract. A driver describes how the underlying RDBMS quotes
 * identifiers and reports its canonical name. Everything else (LIMIT/OFFSET
 * grammar, function names, RETURNING clauses, …) is currently handled at the
 * compiler layer; this interface is intentionally small.
 *
 * To add a custom dialect:
 *
 *   1. Extend {@see AbstractDriver}.
 *   2. Override {@code self::NAME} and {@code self::ESCAPE_CHAR}.
 *   3. Pass an instance to {@see \InitORM\QueryBuilder\QueryBuilder} via
 *      {@see \InitORM\QueryBuilder\QueryBuilder::__construct()} (which selects
 *      by string), or compose the builder yourself.
 */
interface DriverInterface
{
    /**
     * Quote / escape a SQL identifier (table, column, alias, dotted-path).
     *
     * Implementations must:
     *   - leave bind-parameter prefixes (":foo") and SQL keywords AND, OR,
     *     AS, ON intact;
     *   - quote each identifier segment around dots ({@code "users.id"} →
     *     {@code `users`.`id`} in MySQL);
     *   - double the escape character when it appears inside an identifier.
     *
     * The input is not mutated — the escaped string is returned.
     */
    public function escapeIdentifier(string $identifier): string;

    /**
     * The canonical driver name — "mysql", "pgsql", "sqlite", or null when
     * the driver applies no dialect-specific behavior (see
     * {@see GenericDriver}).
     */
    public function getName(): ?string;
}
