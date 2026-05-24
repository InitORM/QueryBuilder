<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

/**
 * Contract for the parameter bag used by the clause builders. Implementations
 * must auto-suffix colliding {@see self::add()} keys so that re-binding the
 * same column name does not clobber an earlier value.
 */
interface ParameterInterface
{
    /**
     * Set a single key (overwrites any existing value at that key).
     */
    public function set(string $key, mixed $value): self;

    /**
     * Register a value and return the placeholder name actually assigned.
     * Implementations must guarantee unique placeholder names — appending
     * "_1", "_2", … when colliding with previous keys.
     */
    public function add(RawQuery|string $key, mixed $value): string;

    /**
     * Bulk-merge one or more parameter sources onto this bag.
     */
    public function merge(array|ParameterInterface ...$arrays): self;

    /**
     * Return either the full map (when $key is null) or a single value with
     * an optional default (which may be a {@see \Closure}).
     */
    public function get(?string $key = null, mixed $default = null): mixed;

    /**
     * The whole parameter map.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Clear the bag.
     */
    public function reset(): self;
}
