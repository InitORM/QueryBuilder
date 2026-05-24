<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Assembles a single-row INSERT statement.
 */
final class InsertCompiler extends AbstractCompiler
{
    /**
     * @param array<string, mixed> $structure
     *
     * @throws QueryBuilderException
     */
    public function compile(array $structure): string
    {
        $columns = [];
        $values = [];
        $set = array_merge(...$structure['set']);
        foreach ($set as $column => $value) {
            $columns[] = $column;
            $values[] = $value;
        }
        if (empty($columns)) {
            throw new QueryBuilderException('The data set for the insert could not be found.');
        }

        return 'INSERT INTO'
            . ' ' . $this->compileSchemaName($structure) . ' '
            . '(' . implode(', ', $columns) . ')'
            . ' VALUES '
            . '(' . implode(', ', $values) . ');';
    }
}
