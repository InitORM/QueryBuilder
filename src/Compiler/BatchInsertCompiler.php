<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Assembles a multi-row INSERT statement. Missing columns in any row are
 * compiled as the literal NULL.
 */
final class BatchInsertCompiler extends AbstractCompiler
{
    /**
     * @param array<string, mixed> $structure
     *
     * @throws QueryBuilderException
     */
    public function compile(array $structure): string
    {
        $columns = array_keys(array_merge(...$structure['set']));
        if (empty($columns)) {
            throw new QueryBuilderException('The data set for the insert could not be found.');
        }
        $values = [];
        foreach ($structure['set'] as $set) {
            $value = [];
            foreach ($columns as $column) {
                $value[$column] = $set[$column] ?? 'NULL';
            }
            $values[] = '(' . implode(', ', $value) . ')';
        }

        return 'INSERT INTO'
            . ' ' . $this->compileSchemaName($structure) . ' '
            . '(' . implode(', ', $columns) . ')'
            . ' VALUES '
            . implode(', ', $values) . ';';
    }
}
