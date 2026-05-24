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
 * and delegate SQL string assembly to the compilers in
 * {@see \InitORM\QueryBuilder\Compiler}.
 *
 * The conventional life cycle of a query is:
 *
 *   1. {@see self::newBuilder()} or instantiate a builder.
 *   2. Pick the target via {@see self::table()} / {@see self::from()}.
 *   3. Add projections / joins / clauses (SELECT) or values (INSERT/UPDATE).
 *   4. Compile with one of {@see self::generateSelectQuery()},
 *      {@see self::generateInsertQuery()}, etc.
 *   5. Read bound parameters from {@see self::getParameter()} and execute
 *      the resulting SQL via PDO (or the InitORM\DBAL connection layer).
 *
 * Builders return {@code static} so calls chain. Identifier escaping is
 * delegated to the active {@see DriverInterface}; raw fragments that should
 * bypass escaping must be wrapped in a {@see RawQuery}.
 */
interface QueryBuilderInterface
{
    // ---- structure -------------------------------------------------------

    /**
     * Spawn a brand-new builder configured for the same dialect as this one
     * — handy when assembling a sub-query while preserving the current state.
     */
    public function newBuilder(): static;

    /**
     * Replace (or merge) the in-memory structure. Typically used to restore
     * a previously {@see self::exportQB()}-ed snapshot.
     *
     * @param array<string, mixed> $structure A structure shape compatible
     *                                        with the builder.
     * @param bool                 $merge     When true, the supplied array
     *                                        is merged onto the current
     *                                        structure; when false (default)
     *                                        the structure is reset first.
     */
    public function importQB(array $structure, bool $merge = false): static;

    /**
     * Snapshot the current structure array — every clause builder mutates a
     * value of this shape. Useful for serialization, diffing, or cloning.
     *
     * @return array<string, mixed>
     */
    public function exportQB(): array;

    /**
     * Reset (or selectively keep / zero) parts of the structure.
     *
     * @param string[]|string|null $ignoreOrCare Structure keys to operate
     *                                           on. null resets everything.
     * @param bool|null            $isIgnore     true → keep the listed keys
     *                                           (zero the rest); false →
     *                                           zero the listed keys (keep
     *                                           the rest).
     */
    public function resetStructure(null|array|string $ignoreOrCare = null, ?bool $isIgnore = null): static;

    /**
     * PHP-level shallow clone. Note that the parameter bag is also cloned
     * — mutations on the clone do not bleed into the original.
     */
    public function clone(): static;

    /**
     * The parameter bag used to register bound values. Read after compiling
     * to obtain the placeholder→value map for PDO execution.
     */
    public function getParameter(): ParameterInterface;

    /**
     * The active driver — exposed so callers can re-use its identifier
     * escaping when assembling custom raw fragments.
     */
    public function getDriver(): DriverInterface;

    /**
     * Set a single named parameter, overwriting any existing value at that
     * key. Use {@see self::getParameter()}->add() if you instead want
     * collision-safe auto-suffixing.
     */
    public function setParameter(string $key, mixed $value): static;

    /**
     * Bulk-overwrite many parameters at once.
     *
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters = []): static;

    // ---- SELECT projection ----------------------------------------------

    /**
     * Append one or more projection expressions. String columns are escaped
     * via the active driver; {@see RawQuery} instances are passed through
     * verbatim.
     *
     * @example
     *   $qb->select('id', 'name', $qb->raw('NOW() AS now'));
     */
    public function select(string|RawQuery ...$columns): static;

    /**
     * Drop every projection accumulated so far; the next compile would emit
     * "SELECT *".
     */
    public function clearSelect(): static;

    /**
     * Append a {@code COUNT(column)} expression, optionally aliased.
     */
    public function selectCount(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code COUNT(DISTINCT column)} expression.
     */
    public function selectCountDistinct(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code MAX(column)} expression.
     */
    public function selectMax(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code MIN(column)} expression.
     */
    public function selectMin(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append an {@code AVG(column)} expression.
     */
    public function selectAvg(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append "column AS alias".
     */
    public function selectAs(RawQuery|string $column, string $alias): static;

    /**
     * Append an {@code UPPER(column)} projection.
     */
    public function selectUpper(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code LOWER(column)} projection.
     */
    public function selectLower(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code LENGTH(column)} projection.
     */
    public function selectLength(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code MID(column, offset, length)} projection (MySQL
     * dialect — PostgreSQL/SQLite users should use {@see self::raw()} with
     * SUBSTRING instead).
     */
    public function selectMid(RawQuery|string $column, int $offset, int $length, ?string $alias = null): static;

    /**
     * Append a {@code LEFT(column, length)} projection.
     */
    public function selectLeft(RawQuery|string $column, int $length, ?string $alias = null): static;

    /**
     * Append a {@code RIGHT(column, length)} projection.
     */
    public function selectRight(RawQuery|string $column, int $length, ?string $alias = null): static;

    /**
     * Append a {@code DISTINCT(column)} projection.
     */
    public function selectDistinct(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code COALESCE(column, default)} projection. The default
     * is escaped as an identifier only when it is a non-numeric string —
     * numeric literals pass through unchanged.
     */
    public function selectCoalesce(RawQuery|string $column, mixed $default = '0', ?string $alias = null): static;

    /**
     * Append a {@code SUM(column)} projection.
     */
    public function selectSum(RawQuery|string $column, ?string $alias = null): static;

    /**
     * Append a {@code CONCAT(col1, col2, …)} projection.
     *
     * @param array<int, string|RawQuery> $columns
     */
    public function selectConcat(array $columns, ?string $alias = null): static;

    // ---- FROM / table ---------------------------------------------------

    /**
     * Set the FROM list to a single table (clears any previously registered
     * table), optionally aliased.
     */
    public function from(RawQuery|string $table, ?string $alias = null): static;

    /**
     * Append an additional FROM entry (for comma-separated FROM lists,
     * e.g. {@code FROM users AS u, roles AS r}).
     */
    public function addFrom(RawQuery|string $table, ?string $alias = null): static;

    /**
     * Set the FROM list to a single table without supporting an alias.
     * Equivalent to {@code from($table)} for callers that prefer the
     * shorter name.
     */
    public function table(RawQuery|string $table): static;

    // ---- grouping / ordering / pagination -------------------------------

    /**
     * Append one or more GROUP BY columns. Array arguments are flattened
     * recursively.
     *
     * @param string|RawQuery|array<int, string|RawQuery> ...$columns
     */
    public function groupBy(string|RawQuery|array ...$columns): static;

    /**
     * Append an ORDER BY clause.
     *
     * @param string $soft One of "ASC" (default) or "DESC" — case-insensitive.
     *
     * @throws QueryBuilderInvalidArgumentException When $soft is not ASC/DESC.
     */
    public function orderBy(RawQuery|string $column, string $soft = 'ASC'): static;

    /**
     * Set the OFFSET. Negative numbers are reflected to their absolute value.
     */
    public function offset(int $offset = 0): static;

    /**
     * Set the LIMIT. Negative numbers are reflected to their absolute value.
     */
    public function limit(int $limit): static;

    // ---- JOIN -----------------------------------------------------------

    /**
     * Append a JOIN to the structure.
     *
     * When {@code $onStmt} is a {@see Closure}, it is invoked with a fresh
     * builder so the caller can compose the ON expression — and optionally
     * raise WHERE / HAVING side conditions that are folded back into the
     * outer query.
     *
     * @param string $type One of "INNER" (default), "LEFT", "RIGHT",
     *                     "LEFT OUTER", "RIGHT OUTER", "NATURAL", "SELF".
     */
    public function join(RawQuery|string $table, RawQuery|string|Closure|null $onStmt = null, string $type = 'INNER'): static;

    /**
     * Append a SELF JOIN. Implemented as a comma-separated FROM with the
     * ON expression added to WHERE.
     */
    public function selfJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    /**
     * Append an INNER JOIN (the default).
     */
    public function innerJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    /**
     * Append a LEFT JOIN.
     */
    public function leftJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    /**
     * Append a RIGHT JOIN.
     */
    public function rightJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    /**
     * Append a LEFT OUTER JOIN.
     */
    public function leftOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    /**
     * Append a RIGHT OUTER JOIN.
     */
    public function rightOuterJoin(string|RawQuery $table, string|RawQuery|Closure $onStmt): static;

    /**
     * Append a NATURAL JOIN. NATURAL JOIN does not carry an ON clause.
     */
    public function naturalJoin(string|RawQuery $table): static;

    // ---- WHERE / HAVING / ON --------------------------------------------

    /**
     * Add a WHERE condition. When only two arguments are supplied, the
     * second is treated as the value and the operator defaults to "=":
     *
     *   $qb->where('id', 5)           // id = 5
     *   $qb->where('id', '>', 5)      // id > 5
     *   $qb->where('name', 'IS', null)// name IS NULL
     *
     * @param string $logical "AND" (default), "OR" — "&&" and "||" are
     *                        also accepted.
     *
     * @throws QueryBuilderInvalidArgumentException On unknown $logical.
     */
    public function where(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static;

    /**
     * Add a HAVING condition. Mirrors {@see self::where()}.
     *
     * @throws QueryBuilderInvalidArgumentException On unknown $logical.
     */
    public function having(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static;

    /**
     * Add an ON condition for JOIN closures. Dotted string values
     * ({@code "u.id"}) are escaped as identifiers rather than parameterized,
     * since they almost always refer to another column on the join side.
     *
     * @throws QueryBuilderInvalidArgumentException On unknown $logical.
     */
    public function on(RawQuery|string $column, mixed $operator = '=', mixed $value = null, string $logical = 'AND'): static;

    /**
     * Convenience: {@see self::where()} with $logical fixed to "AND".
     */
    public function andWhere(string|RawQuery $column, mixed $operator = '=', mixed $value = null): static;

    /**
     * Convenience: {@see self::where()} with $logical fixed to "OR".
     */
    public function orWhere(string|RawQuery $column, mixed $operator = '=', mixed $value = null): static;

    // BETWEEN

    /**
     * Add a BETWEEN clause. The bounds may be supplied as two separate
     * arguments or as a two-element array via $firstValue.
     *
     *   $qb->between('age', 18, 65);
     *   $qb->between('age', [18, 65]);
     */
    public function between(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::between()}.
     */
    public function orBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    /**
     * AND-flavored {@see self::between()} (the default).
     */
    public function andBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    /**
     * Add a NOT BETWEEN clause.
     */
    public function notBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::notBetween()}.
     */
    public function orNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    /**
     * AND-flavored {@see self::notBetween()}.
     */
    public function andNotBetween(string|RawQuery $column, mixed $firstValue = null, mixed $lastValue = null): static;

    // FIND_IN_SET

    /**
     * Add a {@code FIND_IN_SET(value, column)} clause (MySQL-specific
     * function). PostgreSQL / SQLite users should use {@see self::raw()}
     * with array_position / instr instead.
     */
    public function findInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * AND-flavored {@see self::findInSet()}.
     */
    public function andFindInSet(string|RawQuery $column, mixed $value = null): static;

    /**
     * OR-flavored {@see self::findInSet()}.
     */
    public function orFindInSet(string|RawQuery $column, mixed $value = null): static;

    /**
     * Add a {@code NOT FIND_IN_SET(value, column)} clause.
     */
    public function notFindInSet(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * AND-flavored {@see self::notFindInSet()}.
     */
    public function andNotFindInSet(string|RawQuery $column, mixed $value = null): static;

    /**
     * OR-flavored {@see self::notFindInSet()}.
     */
    public function orNotFindInSet(string|RawQuery $column, mixed $value = null): static;

    // IN

    /**
     * Add an IN clause. Arrays are deduplicated; numeric elements are
     * inlined verbatim while strings are parameterized. A {@see RawQuery}
     * (e.g. a sub-query) is rendered as-is.
     */
    public function whereIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * Add a NOT IN clause; otherwise behaves like {@see self::whereIn()}.
     */
    public function whereNotIn(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::whereIn()}.
     */
    public function orWhereIn(string|RawQuery $column, mixed $value = null): static;

    /**
     * OR-flavored {@see self::whereNotIn()}.
     */
    public function orWhereNotIn(string|RawQuery $column, mixed $value = null): static;

    /**
     * AND-flavored {@see self::whereIn()}.
     */
    public function andWhereIn(string|RawQuery $column, mixed $value = null): static;

    /**
     * AND-flavored {@see self::whereNotIn()}.
     */
    public function andWhereNotIn(string|RawQuery $column, mixed $value = null): static;

    // REGEXP / SOUNDEX

    /**
     * Add a REGEXP comparison (MySQL — POSIX flavor). PostgreSQL users
     * prefer {@code ~} which is not surfaced here.
     */
    public function regexp(string|RawQuery $column, string|RawQuery $value, string $logical = 'AND'): static;

    /**
     * AND-flavored {@see self::regexp()}.
     */
    public function andRegexp(string|RawQuery $column, string|RawQuery $value): static;

    /**
     * OR-flavored {@see self::regexp()}.
     */
    public function orRegexp(string|RawQuery $column, string|RawQuery $value): static;

    /**
     * Add a SOUNDEX-based fuzzy comparison
     * ({@code SOUNDEX(col) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(value)), '%')}).
     */
    public function soundex(string|RawQuery $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * AND-flavored {@see self::soundex()}.
     */
    public function andSoundex(string|RawQuery $column, mixed $value = null): static;

    /**
     * OR-flavored {@see self::soundex()}.
     */
    public function orSoundex(string|RawQuery $column, mixed $value = null): static;

    // NULL

    /**
     * Add a {@code col IS NULL} clause.
     */
    public function whereIsNull(string|RawQuery $column, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::whereIsNull()}.
     */
    public function orWhereIsNull(string|RawQuery $column): static;

    /**
     * AND-flavored {@see self::whereIsNull()}.
     */
    public function andWhereIsNull(string|RawQuery $column): static;

    /**
     * Add a {@code col IS NOT NULL} clause.
     */
    public function whereIsNotNull(string|RawQuery $column, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::whereIsNotNull()}.
     */
    public function orWhereIsNotNull(string|RawQuery $column): static;

    /**
     * AND-flavored {@see self::whereIsNotNull()}.
     */
    public function andWhereIsNotNull(string|RawQuery $column): static;

    // LIKE family

    /**
     * Add a LIKE clause whose wildcard placement is decided by $type:
     *
     *   "both"           (default) → "%value%"
     *   "before" / "start"          → "value%"
     *   "after"  / "end"            → "%value"
     *
     * @param string $type One of "both", "before"/"start", "after"/"end".
     */
    public function like(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::like()}.
     */
    public function orLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    /**
     * AND-flavored {@see self::like()}.
     */
    public function andLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    /**
     * Negated {@see self::like()} — compiles to NOT LIKE.
     */
    public function notLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both', string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::notLike()}.
     */
    public function orNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    /**
     * AND-flavored {@see self::notLike()}.
     */
    public function andNotLike(string|RawQuery|array $column, mixed $value = null, string $type = 'both'): static;

    /**
     * Match strings that begin with $value — equivalent to LIKE 'value%'.
     */
    public function startLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::startLike()}.
     */
    public function orStartLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * AND-flavored {@see self::startLike()}.
     */
    public function andStartLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * Negated {@see self::startLike()} — compiles to NOT LIKE 'value%'.
     */
    public function notStartLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::notStartLike()}.
     */
    public function orStartNotLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * AND-flavored {@see self::notStartLike()}.
     */
    public function andStartNotLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * Match strings that end with $value — equivalent to LIKE '%value'.
     */
    public function endLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::endLike()}.
     */
    public function orEndLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * AND-flavored {@see self::endLike()}.
     */
    public function andEndLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * Negated {@see self::endLike()} — compiles to NOT LIKE '%value'.
     */
    public function notEndLike(string|RawQuery|array $column, mixed $value = null, string $logical = 'AND'): static;

    /**
     * OR-flavored {@see self::notEndLike()}.
     */
    public function orEndNotLike(string|RawQuery|array $column, mixed $value = null): static;

    /**
     * AND-flavored {@see self::notEndLike()}.
     */
    public function andEndNotLike(string|RawQuery|array $column, mixed $value = null): static;

    // sub-query / grouping / raw

    /**
     * Build a SELECT sub-query using a fresh builder.
     *
     * @param Closure(static): void $closure          Receives the inner
     *                                                builder by reference.
     * @param string|null           $alias            Aliases the sub-query
     *                                                (only valid when
     *                                                $isIntervalQuery is true).
     * @param bool                  $isIntervalQuery  When true (default), the
     *                                                emitted SQL is wrapped
     *                                                in parentheses — suitable
     *                                                for IN / FROM / JOIN
     *                                                contexts.
     *
     * @throws QueryBuilderException If $alias is supplied while
     *                               $isIntervalQuery is false.
     */
    public function subQuery(Closure $closure, ?string $alias = null, bool $isIntervalQuery = true): RawQuery;

    /**
     * Group multiple where / having / on clauses in parentheses, joined by
     * AND or OR. The closure receives a fresh builder; the closure's WHERE,
     * HAVING and ON buckets are each independently wrapped and folded back
     * into the outer query.
     *
     * @param Closure(static): void $closure
     *
     * @throws QueryBuilderException On unknown $logical.
     */
    public function group(Closure $closure, string $logical = 'AND'): static;

    /**
     * Wrap a SQL fragment that should be inlined verbatim, bypassing
     * identifier escaping and parameter binding. Use sparingly — never
     * embed unsanitized user input.
     */
    public function raw(mixed $rawQuery): RawQuery;

    // ---- SET (INSERT / UPDATE) ------------------------------------------

    /**
     * Append a SET row for the next INSERT or UPDATE compile. When the
     * first argument is an associative array and the second is null, every
     * key/value pair in that array is added as one row.
     *
     * Multiple calls produce multi-row INSERTs (batch insert) or the
     * input rows for {@see self::generateUpdateBatchQuery()}.
     *
     * @param bool $strict Reserved for future use.
     */
    public function set(RawQuery|array|string $column, mixed $value = null, bool $strict = true): static;

    /**
     * Alias of {@see self::set()} — appends a SET row.
     */
    public function addSet(RawQuery|array|string $column, mixed $value = null, bool $strict = true): static;

    // ---- compile --------------------------------------------------------

    /**
     * Compile to a SELECT statement.
     *
     * @param array<int, string|RawQuery> $selector   Optional shortcut: items
     *                                                are appended via
     *                                                {@see self::select()}.
     * @param array<int|string, mixed>    $conditions Optional shortcut: items
     *                                                with string keys become
     *                                                {@code where(key, value)}
     *                                                and items with integer
     *                                                keys are passed as a
     *                                                single argument to
     *                                                {@see self::where()}.
     *
     * @throws QueryBuilderException When the structure is invalid.
     */
    public function generateSelectQuery(array $selector = [], array $conditions = []): string;

    /**
     * Compile to an UPDATE statement. Requires at least one SET row and a
     * target table.
     *
     * @throws QueryBuilderException When no SET data exists or no table is set.
     */
    public function generateUpdateQuery(): string;

    /**
     * Compile to a batch UPDATE that uses CASE/WHEN expressions keyed by
     * $referenceColumn. Every SET row must contain the reference column.
     *
     * @throws QueryBuilderException When the reference column is missing
     *                               from any of the SET rows.
     */
    public function generateUpdateBatchQuery(string $referenceColumn): string;

    /**
     * Compile to a single-row INSERT statement.
     *
     * @throws QueryBuilderException When no SET data exists.
     */
    public function generateInsertQuery(): string;

    /**
     * Compile to a multi-row INSERT statement. Missing columns in any row
     * are compiled as the literal NULL.
     *
     * @throws QueryBuilderException When no SET data exists.
     */
    public function generateBatchInsertQuery(): string;

    /**
     * Compile to a DELETE statement. WHERE-less deletes compile to
     * {@code WHERE 1} — fully intentional, callers are responsible for
     * gating.
     *
     * @throws QueryBuilderException When no table is set.
     */
    public function generateDeleteQuery(): string;
}
