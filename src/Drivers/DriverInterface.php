<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * Driver-level dialect contract. Implementations need only describe how the
 * dialect quotes identifiers; everything else (LIMIT/OFFSET grammar, function
 * names, etc.) is handled at the compiler layer.
 */
interface DriverInterface
{
    /**
     * Quote / escape a SQL identifier (table, column, alias, dotted-path).
     * Returns the escaped identifier; the input is not mutated.
     */
    public function escapeIdentifier(string $identifier): string;

    /**
     * The canonical driver name (e.g. "mysql", "pgsql", "sqlite", or null
     * when the driver applies no dialect-specific escaping).
     */
    public function getName(): ?string;
}
