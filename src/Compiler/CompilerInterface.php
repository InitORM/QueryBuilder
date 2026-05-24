<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

/**
 * Marker interface for SQL compilers. Each compiler converts a finished
 * structure array into a SQL string; concrete compilers expose a
 * {@see self::compile()} method whose signature may include extra arguments
 * specific to that query type.
 */
interface CompilerInterface
{
}
