<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

/**
 * Concrete {@see QueryBuilderFactoryInterface}. Trivial; exposed so callers
 * (and dependent packages) can dependency-inject a factory instead of
 * newing up {@see QueryBuilder} directly.
 */
class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    public function createQueryBuilder(?string $driver = null): QueryBuilderInterface
    {
        return new QueryBuilder($driver);
    }
}
