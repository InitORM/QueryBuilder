<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * MySQL / MariaDB dialect — identifiers wrapped in backticks.
 */
final class MySqlDriver extends AbstractDriver
{
    protected const NAME = 'mysql';
    protected const ESCAPE_CHAR = '`';
}
