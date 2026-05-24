<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Exceptions;

use Exception;

/**
 * Thrown on structural query problems — missing table, missing data set,
 * invalid sub-query alias placement, etc.
 */
class QueryBuilderException extends Exception
{
}
