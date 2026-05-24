<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

use InitORM\QueryBuilder\Drivers\DriverInterface;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\Helper\SqlValueDetector;
use InitORM\QueryBuilder\ParameterInterface;
use InitORM\QueryBuilder\QueryBuilderInterface;

/**
 * Assembles a batch UPDATE statement using CASE/WHEN expressions keyed by a
 * reference column. Unlike the other compilers this one mutates the supplied
 * QueryBuilder (it registers reference-column parameters and appends a
 * WHERE IN filter) — that mirrors the single-table semantics of the previous
 * implementation.
 */
final class BatchUpdateCompiler extends AbstractCompiler
{
    /**
     * @throws QueryBuilderException
     */
    public function compile(
        QueryBuilderInterface $builder,
        string $referenceColumn,
        DriverInterface $driver,
        ParameterInterface $parameters,
    ): string {
        $structure = $builder->exportQB();
        $referenceColumn = $driver->escapeIdentifier($referenceColumn);

        $update = [];
        $data = $structure['set'];
        $updateData = $columns = $where = [];

        foreach ($data as $set) {
            if (!isset($set[$referenceColumn])) {
                throw new QueryBuilderException(
                    'The reference column does not exist in one or more of the set arrays.'
                );
            }
            $setData = [];
            $where[] = $set[$referenceColumn];
            unset($set[$referenceColumn]);
            foreach ($set as $key => $value) {
                $setData[$key] = $value;
                if (!in_array($key, $columns, true)) {
                    $columns[] = $key;
                }
            }
            $updateData[] = $setData;
        }

        foreach ($columns as $column) {
            $syntax = $column . ' = CASE';
            foreach ($updateData as $key => $values) {
                if (!array_key_exists($column, $values)) {
                    continue;
                }
                $reference = SqlValueDetector::isSqlParameterOrFunction($where[$key])
                    ? $where[$key]
                    : $parameters->add($referenceColumn, $where[$key]);
                $syntax .= ' WHEN ' . $referenceColumn . ' = ' . $reference
                    . ' THEN ' . $values[$column];
            }
            $update[] = $syntax . ' ELSE ' . $column . ' END';
        }

        $builder->whereIn($referenceColumn, $where);
        $structure = $builder->exportQB();

        return 'UPDATE ' . $this->compileSchemaName($structure)
            . ' SET '
            . implode(', ', $update)
            . ' WHERE '
            . (($whereSql = $this->compileWhere($structure)) !== null ? $whereSql : '1')
            . ($this->compileHaving($structure) ?? '')
            . ($this->compileLimit($structure) ?? '');
    }
}
