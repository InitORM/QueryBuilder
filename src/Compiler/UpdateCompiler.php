<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Assembles a single-row UPDATE statement.
 */
final class UpdateCompiler extends AbstractCompiler
{
    /**
     * @throws QueryBuilderException
     */
    public function compile(array $structure): string
    {
        $set = array_merge(...$structure['set']);
        $updateSet = [];
        foreach ($set as $column => $value) {
            $updateSet[] = $column . ' = ' . $value;
        }
        if (empty($updateSet)) {
            throw new QueryBuilderException('The data set for the update could not be found.');
        }

        return 'UPDATE ' . $this->compileSchemaName($structure)
            . ' SET ' . implode(', ', $updateSet)
            . ' WHERE '
            . (($where = $this->compileWhere($structure)) !== null ? $where : '1')
            . ($this->compileHaving($structure) ?? '')
            . ($this->compileLimit($structure) ?? '');
    }
}
