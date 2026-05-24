<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * Default driver — does not quote identifiers. Used when the caller has not
 * explicitly picked a dialect.
 */
final class GenericDriver extends AbstractDriver
{
    protected const NAME = null;
    protected const ESCAPE_CHAR = '';
}
