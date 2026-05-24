<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Clause;

use InitORM\QueryBuilder\RawQuery;

/**
 * FROM-clause builder helpers. Adds table references to the structure with
 * optional aliases; identifiers are escaped via the active driver before
 * being stored.
 */
trait FromClauseTrait
{
    /**
     * @inheritDoc
     */
    public function from(RawQuery|string $table, ?string $alias = null): static
    {
        $this->structure['table'] = [];

        return $this->addFrom($table, $alias);
    }

    /**
     * @inheritDoc
     */
    public function addFrom(RawQuery|string $table, ?string $alias = null): static
    {
        if (is_string($table)) {
            $table = $this->driver->escapeIdentifier($table);
        }
        $entry = $table . ($alias !== null ? ' AS ' . $this->driver->escapeIdentifier($alias) : '');
        if (!in_array($entry, $this->structure['table'], true)) {
            $this->structure['table'][] = $entry;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function table(RawQuery|string $table): static
    {
        if (is_string($table)) {
            $table = $this->driver->escapeIdentifier($table);
        }
        $this->structure['table'] = [(string) $table];

        return $this;
    }
}
