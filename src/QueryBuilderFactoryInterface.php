<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

/**
 * Factory contract for {@see QueryBuilderInterface}. Useful when callers
 * want to inject a builder factory rather than newing one up.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Build a new query builder using the named driver (mysql / pgsql /
     * sqlite / null = generic).
     */
    public function createQueryBuilder(?string $driver = null): QueryBuilderInterface;
}
