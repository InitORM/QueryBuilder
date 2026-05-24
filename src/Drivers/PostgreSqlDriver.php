<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * PostgreSQL dialect — identifiers wrapped in double quotes.
 */
final class PostgreSqlDriver extends AbstractDriver
{
    protected const NAME = 'pgsql';
    protected const ESCAPE_CHAR = '"';
}
