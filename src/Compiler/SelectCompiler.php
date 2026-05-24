<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

/**
 * Assembles a SELECT statement from the structure array. The actual column /
 * value escaping and parameter registration happen earlier, in the clause
 * builders; this compiler only string-joins.
 */
final class SelectCompiler extends AbstractCompiler
{
    public function compile(array $structure): string
    {
        return 'SELECT '
            . (empty($structure['select']) ? '*' : implode(', ', $structure['select']))
            . ' FROM '
            . implode(', ', $structure['table'])
            . (!empty($structure['join']) ? ' ' . implode(' ', $structure['join']) : '')
            . ' WHERE '
            . (($where = $this->compileWhere($structure)) !== null ? $where : '1')
            . (!empty($structure['group_by']) ? ' GROUP BY ' . implode(', ', $structure['group_by']) : '')
            . ($this->compileHaving($structure) ?? '')
            . (!empty($structure['order_by']) ? ' ORDER BY ' . implode(', ', $structure['order_by']) : '')
            . ($this->compileLimit($structure) ?? '');
    }
}
