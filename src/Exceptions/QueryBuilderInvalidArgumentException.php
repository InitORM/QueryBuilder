<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a user-supplied argument is well-formed but not acceptable —
 * an unknown sort direction, an unknown logical connector, etc.
 */
class QueryBuilderInvalidArgumentException extends InvalidArgumentException
{
}
