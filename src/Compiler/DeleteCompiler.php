<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Assembles a DELETE statement.
 */
final class DeleteCompiler extends AbstractCompiler
{
    /**
     * @param array<string, mixed> $structure
     *
     * @throws QueryBuilderException
     */
    public function compile(array $structure): string
    {
        return 'DELETE FROM'
            . ' '
            . $this->compileSchemaName($structure)
            . ' WHERE '
            . (($where = $this->compileWhere($structure)) !== null ? $where : '1')
            . ($this->compileLimit($structure) ?? '');
    }
}
