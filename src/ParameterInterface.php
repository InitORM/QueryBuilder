<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

use Closure;

/**
 * Contract for the parameter bag used by the clause builders.
 *
 * Implementations must auto-suffix colliding {@see self::add()} keys so
 * re-binding the same column name does not clobber an earlier value — this
 * is what enables expressions like {@code where('id', '>', 0)->where('id', '<', 100)}
 * to compile to two distinct placeholders (":id" and ":id_1").
 *
 * Keys are sanitized to {@code [A-Za-z0-9_]} and exposed with a leading ":" so
 * the resulting array can be handed straight to {@see \PDOStatement::execute()}.
 */
interface ParameterInterface
{
    /**
     * Set a single key, overwriting any existing value at that key. The
     * stored key is sanitized and prefixed with ":" (so {@code set('id', 5)}
     * yields {@code [":id" => 5]}).
     */
    public function set(string $key, mixed $value): self;

    /**
     * Register a value and return the placeholder name actually assigned.
     *
     * - Null values short-circuit and return the literal string "NULL" — the
     *   caller is expected to inline it into the SQL instead of binding.
     * - {@see RawQuery} keys are hashed (md5) before sanitization so they
     *   produce stable, opaque placeholder names.
     * - Colliding keys auto-suffix ":foo", ":foo_1", ":foo_2", …
     *
     * @return string The full placeholder name including the ":" prefix, or
     *                the literal "NULL" when $value is null.
     */
    public function add(RawQuery|string $key, mixed $value): string;

    /**
     * Bulk-merge one or more parameter sources onto this bag. Sources can be
     * plain arrays or other {@see ParameterInterface} instances.
     *
     * @param array<string, mixed>|ParameterInterface ...$arrays
     */
    public function merge(array|ParameterInterface ...$arrays): self;

    /**
     * Return either the full map (when $key is null) or a single value with
     * an optional default. The default may be a {@see Closure} — in which
     * case it is invoked lazily only when the key is missing.
     */
    public function get(?string $key = null, mixed $default = null): mixed;

    /**
     * The whole placeholder → value map. Suitable for passing to
     * {@see \PDOStatement::execute()}.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Empty the bag. Returned for fluent chaining.
     */
    public function reset(): self;
}
