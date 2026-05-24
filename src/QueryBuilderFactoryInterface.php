<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

/**
 * Factory contract for {@see QueryBuilderInterface}. Useful when callers
 * prefer to dependency-inject a factory rather than newing up a builder
 * directly — e.g. when sharing dialect selection across a request.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Build a new query builder using the named driver.
     *
     * @param string|null $driver One of "mysql", "pgsql" ("postgres",
     *                            "postgresql"), "sqlite", or null for the
     *                            generic (no-escaping) driver.
     */
    public function createQueryBuilder(?string $driver = null): QueryBuilderInterface;
}
