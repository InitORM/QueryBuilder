<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * SQLite dialect — identifiers wrapped in backticks.
 */
final class SqliteDriver extends AbstractDriver
{
    protected const NAME = 'sqlite';
    protected const ESCAPE_CHAR = '`';
}
