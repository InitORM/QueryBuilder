<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

use Closure;
use InitORM\QueryBuilder\Drivers\DriverInterface;
use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\Exceptions\QueryBuilderInvalidArgumentException;

/**
 * Public surface of the fluent query builder. Concrete implementations
 * compose the clause-building traits in {@see \InitORM\QueryBuilder\Clause}
 * and delegate to the compilers in {@see \InitORM\QueryBuilder\Compiler}.
 *
 * Method ordering mirrors the conventional life cycle of a query:
 *   1. structure / parameters
 *   2. select / from / join / group / order / limit
 *   3. where / having / on (and sugar)
 *   4. set
 *   5. compile (generate*Query)
 *
 * Unless stated otherwise, builders return {@code static} so calls chain.
 */
interface QueryBuilderInterface
{
    // ---- structure -------------------------------------------------------

    /**
     * Return a fresh builder configured for the same dialect as this one.
     */
    public function newBuilder(): static;

    /**
     * Replace the in-memory structure with the supplied one.
     *
     * @param bool $merge When true, the supplied array is merged onto the
     *                    current structure instead of the blank-slate default.
     */
    public function importQB(array $structure, bool $merge = false): static;

    /**
     * Return the current in-memory structure array.
     */
    public function exportQB(): array;

    /**
     * Reset the structure to its blank-slate state.
     *
     * @param string[]|string|null $ignoreOrCare Keys to operate on, or null
     *                                           to reset everything.
     * @param bool|null            $isIgnore     true → keep the listed keys;
     *                                           false → zero only those keys.
     */
    public function resetStructure(null|array|string $ignoreOrCare = null, ?bool $isIgnore = null): static;

    /**
     * Shallow clone of this builder.
     */
    public function clone(): static;

    /**
     * The parameter bag used to register bound values.
     */
    public function getParameter(): ParameterInterface;

    /**
     * The active driver.
     */
    public function getDriver(): DriverInterface;

    /**
     * Set a single named parameter (overwrites if already present).
     */
    public function setParameter(string $key, mixed $value): static;

    /**
     * Set many parameters at once.
     *
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters = []): static;

    // ---- SELECT projection ----------------------------------------------

    /**
     * Add one or more projection expressions. String columns are escaped via
     * the active driver; {@see RawQuery} instances are kept verbatim.
     */
    public function select(string|RawQuery ...$columns): static;

    public function clearSelect(): static;

    public function selectCount(RawQuery|string $column, ?string $alias = null): static;

    public function selectCountDistinct(RawQuery|string $column, ?string $alias = null): static;

    public function selectMax(RawQuery|string $column, ?string $alias = null): static;

    public function selectMin(RawQuery|string $column, ?string $alias = null): static;

    public function selectAvg(RawQuery|string $column, ?string $alias = null): static;

    public function selectAs(RawQuery|string $column, string $alias): static;

    public function selectUpper(RawQuery|string $column, ?string $alias = null): static;

    public function selectLower(RawQuery|string $column, ?string $alias = null): static;

    public function selectLength(RawQuery|string $column, ?string $alias = null): static;

    public function selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null): static;

    public function selectLeft(RawQuery|string $column, int $length, ?string $alias = null): static;

    public function selectRight(RawQuery|string $column, int $length, ?string $alias = null): static;

    public function selectDistinct(RawQuery|string $column, ?string $alias = null): static;

    public function selectCoalesce(RawQuery|string $column, mixed $default = '0', ?string $alias = null): static;

    public function selectSum(RawQuery|string $column, ?string $alias = null): static;

    /**
     * @param array<int, string|RawQuery> $columns
     */
    public function selectConcat(array $columns, ?string $alias = null): static;

    // ---- FROM / table ---------------------------------------------------

    public function from(RawQuery|string $table, ?string $alias = null): static;

    public function addFrom(RawQuery|string $table, ?string $alias = null): static;

    public function table(RawQuery|string $table): static;

    // ---- grouping / ordering / pagination -------------------------------

    /**
     * @param string|RawQuery|array<int, string|RawQuery> ...$columns
     */
    public function groupBy(string|RawQuery|array ...$columns): static;

    /**
     * @throws QueryBuilderInvalidArgumentException
     */
    public function orderBy(RawQuery|string $column, string $soft = 'ASC'): static;

    public function offset(int $offset = 0): static;

    public function limit(int $limit): static;

    // ---- JOIN -----------------------------------------------------------

    /**
     * Append a JOIN to the structure. When {@code $onStmt} is a Closure, it
     * is invoked with a fresh builder so the caller can compose the ON
     * expression using on(), where(), having() etc.
     */
    public function join(RawQuery|string $table, RawQuery|string|Closure|null $onStmt = null, string $type = 'INNER'): static;

    public function selfJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    public function innerJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    public function leftJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    public function rightJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    public function leftOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    public function rightOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    public function naturalJoin(string|RawQuery $table): static;

    // ---- WHERE / HAVING / ON --------------------------------------------

    public function where(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static;

    public function having(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static;

    public function on(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static;

    public function andWhere(string|RawQuery $column, mixed $operator = '=', mixed $value = null): static;

    public function orWhere(string|RawQuery $column, mixed $operator = '=', mixed $value = null): static;

    // BETWEEN

    public function between(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): static;

    public function orBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    public function andBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    public function notBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): static;

    public function orNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    public function andNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    // FIND_IN_SET

    public function findInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    public function andFindInSet(string|RawQuery $column, mixed $value = null): static;

    public function orFindInSet(string|RawQuery $column, mixed $value = null): static;

    public function notFindInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    public function andNotFindInSet(string|RawQuery $column, mixed $value = null): static;

    public function orNotFindInSet(string|RawQuery $column, mixed $value = null): static;

    // IN

    public function whereIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    public function whereNotIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    public function orWhereIn(string|RawQuery $column, mixed $value = null): static;

    public function orWhereNotIn(string|RawQuery $column, mixed $value = null): static;

    public function andWhereIn(string|RawQuery $column, mixed $value = null): static;

    public function andWhereNotIn(string|RawQuery $column, mixed $value = null): static;

    // REGEXP / SOUNDEX

    public function regexp(string|RawQuery $column, string|RawQuery $value, string $logical = 'AND'): static;

    public function andRegexp(string|RawQuery $column, string|RawQuery $value): static;

    public function orRegexp(string|RawQuery $column, string|RawQuery $value): static;

    public function soundex(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    public function andSoundex(string|RawQuery $column, mixed $value = null): static;

    public function orSoundex(string|RawQuery $column, mixed $value = null): static;

    // NULL

    public function whereIsNull(string|RawQuery $column, string $logical = 'AND'): static;

    public function orWhereIsNull(string|RawQuery $column): static;

    public function andWhereIsNull(string|RawQuery $column): static;

    public function whereIsNotNull(string|RawQuery $column, string $logical = 'AND'): static;

    public function orWhereIsNotNull(string|RawQuery $column): static;

    public function andWhereIsNotNull(string|RawQuery $column): static;

    // LIKE family

    /**
     * @param string $type One of "both" (default), "before"/"start", "after"/"end".
     */
    public function like(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): static;

    public function orLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    public function andLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    public function notLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): static;

    public function orNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    public function andNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    public function startLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    public function orStartLike(string|RawQuery|array $column, mixed $value = null): static;

    public function andStartLike(string|RawQuery|array $column, mixed $value = null): static;

    public function notStartLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    public function orStartNotLike(string|RawQuery|array $column, mixed $value = null): static;

    public function andStartNotLike(string|RawQuery|array $column, mixed $value = null): static;

    public function endLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    public function orEndLike(string|RawQuery|array $column, mixed $value = null): static;

    public function andEndLike(string|RawQuery|array $column, mixed $value = null): static;

    public function notEndLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    public function orEndNotLike(string|RawQuery|array $column, mixed $value = null): static;

    public function andEndNotLike(string|RawQuery|array $column, mixed $value = null): static;

    // sub-query / grouping / raw

    /**
     * @throws QueryBuilderException
     */
    public function subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true): RawQuery;

    /**
     * Group multiple where / having / on clauses in parentheses.
     *
     * @throws QueryBuilderException
     */
    public function group(Closure $closure, string $logical = 'AND'): static;

    public function raw(mixed $rawQuery): RawQuery;

    // ---- SET (INSERT / UPDATE) ------------------------------------------

    public function set(RawQuery|array|string $column, mixed $value = null, bool $strict = true): static;

    public function addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true): static;

    // ---- compile --------------------------------------------------------

    /**
     * @throws QueryBuilderException
     */
    public function generateSelectQuery(array $selector = [], array $conditions = []): string;

    /**
     * @throws QueryBuilderException
     */
    public function generateUpdateQuery(): string;

    /**
     * @throws QueryBuilderException
     */
    public function generateUpdateBatchQuery(string $referenceColumn): string;

    /**
     * @throws QueryBuilderException
     */
    public function generateInsertQuery(): string;

    /**
     * @throws QueryBuilderException
     */
    public function generateBatchInsertQuery(): string;

    /**
     * @throws QueryBuilderException
     */
    public function generateDeleteQuery(): string;
}
