<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

use InitORM\QueryBuilder\Clause\FromClauseTrait;
use InitORM\QueryBuilder\Clause\JoinClauseTrait;
use InitORM\QueryBuilder\Clause\SelectClauseTrait;
use InitORM\QueryBuilder\Clause\SetClauseTrait;
use InitORM\QueryBuilder\Clause\StructureTrait;
use InitORM\QueryBuilder\Clause\WhereClauseTrait;
use InitORM\QueryBuilder\Compiler\BatchInsertCompiler;
use InitORM\QueryBuilder\Compiler\BatchUpdateCompiler;
use InitORM\QueryBuilder\Compiler\DeleteCompiler;
use InitORM\QueryBuilder\Compiler\InsertCompiler;
use InitORM\QueryBuilder\Compiler\SelectCompiler;
use InitORM\QueryBuilder\Compiler\UpdateCompiler;
use InitORM\QueryBuilder\Drivers\DriverInterface;
use InitORM\QueryBuilder\Drivers\GenericDriver;
use InitORM\QueryBuilder\Drivers\MySqlDriver;
use InitORM\QueryBuilder\Drivers\PostgreSqlDriver;
use InitORM\QueryBuilder\Drivers\SqliteDriver;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;

/**
 * Fluent SQL query builder. Holds the in-memory query model ("structure"), a
 * {@see ParameterInterface} bag for bound values, and the currently selected
 * dialect driver. The actual clause-building methods live in the traits in
 * {@see \InitORM\QueryBuilder\Clause}, and the {@code generate*Query()}
 * methods delegate SQL string assembly to the dedicated compilers in
 * {@see \InitORM\QueryBuilder\Compiler}.
 */
class QueryBuilder implements QueryBuilderInterface
{
    use StructureTrait;
    use FromClauseTrait;
    use SelectClauseTrait;
    use JoinClauseTrait;
    use WhereClauseTrait;
    use SetClauseTrait;

    /**
     * The blank-slate query structure. Every clause builder mutates a copy of
     * this shape; {@see StructureTrait::resetStructure()} restores it.
     */
    protected const STRUCTURE = [
        'select'   => [],
        'table'    => [],
        'join'     => [],
        'where'    => ['AND' => [], 'OR' => []],
        'having'   => ['AND' => [], 'OR' => []],
        'group_by' => [],
        'order_by' => [],
        'offset'   => null,
        'limit'    => null,
        'set'      => [],
        'on'       => ['AND' => [], 'OR' => []],
    ];

    protected array $structure;
    protected ParameterInterface $parameters;
    protected DriverInterface $driver;

    public function __construct(?string $driver = null)
    {
        $this->structure = self::STRUCTURE;
        $this->parameters = new Parameters();
        $this->driver = match ($driver) {
            'mysql'                            => new MySqlDriver(),
            'pgsql', 'postgres', 'postgresql'  => new PostgreSqlDriver(),
            'sqlite'                           => new SqliteDriver(),
            default                            => new GenericDriver(),
        };
    }

    /**
     * Heuristic dispatch: when no SET is present, emits a SELECT; otherwise
     * picks between (batch) INSERT and UPDATE based on whether any WHERE /
     * HAVING clauses have been set.
     *
     * @throws QueryBuilderException
     */
    public function __toString(): string
    {
        if (empty($this->structure['set'])) {
            return $this->generateSelectQuery();
        }

        $isBatch = $this->isBatch();
        $isInsert = empty($this->structure['where']['OR'])
            && empty($this->structure['where']['AND'])
            && empty($this->structure['having']['OR'])
            && empty($this->structure['having']['AND']);

        if ($isInsert) {
            return $isBatch ? $this->generateBatchInsertQuery() : $this->generateInsertQuery();
        }

        return $this->generateUpdateQuery();
    }

    /**
     * @throws QueryBuilderException
     */
    public function generateSelectQuery(array $selector = [], array $conditions = []): string
    {
        if (!empty($selector)) {
            $this->select(...$selector);
        }
        if (!empty($conditions)) {
            foreach ($conditions as $column => $value) {
                if (is_string($column)) {
                    $this->where($column, $value);
                } else {
                    $this->where($value);
                }
            }
        }

        return (new SelectCompiler())->compile($this->structure);
    }

    /**
     * @throws QueryBuilderException
     */
    public function generateInsertQuery(): string
    {
        return (new InsertCompiler())->compile($this->structure);
    }

    /**
     * @throws QueryBuilderException
     */
    public function generateBatchInsertQuery(): string
    {
        return (new BatchInsertCompiler())->compile($this->structure);
    }

    /**
     * @throws QueryBuilderException
     */
    public function generateUpdateQuery(): string
    {
        return (new UpdateCompiler())->compile($this->structure);
    }

    /**
     * @throws QueryBuilderException
     */
    public function generateUpdateBatchQuery(string $referenceColumn): string
    {
        return (new BatchUpdateCompiler())->compile($this, $referenceColumn, $this->driver, $this->parameters);
    }

    /**
     * @throws QueryBuilderException
     */
    public function generateDeleteQuery(): string
    {
        return (new DeleteCompiler())->compile($this->structure);
    }

    /**
     * True if any row in the SET bucket carries more than one column — used
     * by __toString() to pick between INSERT and INSERT-batch.
     */
    public function isBatch(): bool
    {
        foreach ($this->structure['set'] as $set) {
            if (is_array($set) && count($set) > 1) {
                return true;
            }
        }

        return false;
    }
}
