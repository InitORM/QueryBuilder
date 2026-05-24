# API reference

A single-page lookup of every public method on `QueryBuilderInterface`,
plus the related helper classes. For full PHPDoc — including thrown
exceptions, generic types, and per-method examples — see the source.

Methods that mutate the query structure return `static` so calls chain.
The `compile` and getter family return a `string` or the corresponding
value.

## QueryBuilder — structure

| Method                                                     | Returns  | Purpose                                                |
|------------------------------------------------------------|----------|--------------------------------------------------------|
| `newBuilder()`                                             | `static` | Fresh sibling builder, same driver                     |
| `clone()`                                                  | `static` | PHP-level clone (parameter bag deep-cloned)            |
| `importQB(array, bool $merge = false)`                     | `static` | Replace (or merge) the structure                       |
| `exportQB()`                                               | `array`  | Snapshot the structure                                 |
| `resetStructure(?string\|array $keys = null, ?bool $isIgnore = null)` | `static` | Blank-slate, optionally keep/zero named keys |
| `getParameter()`                                           | `ParameterInterface` | Access the parameter bag                   |
| `getDriver()`                                              | `DriverInterface`    | Access the active dialect driver           |
| `setParameter(string $key, mixed $value)`                  | `static` | Overwrite a single parameter                           |
| `setParameters(array $parameters)`                         | `static` | Bulk-overwrite parameters                              |

## QueryBuilder — projection

| Method                                                     | Returns  | Purpose                                                |
|------------------------------------------------------------|----------|--------------------------------------------------------|
| `select(string\|RawQuery ...$columns)`                     | `static` | Append projection(s)                                   |
| `clearSelect()`                                            | `static` | Empty the projection list                              |
| `selectAs(col, alias)`                                     | `static` | `col AS alias`                                         |
| `selectCount(col, ?alias = null)`                          | `static` | `COUNT(col)` / aliased                                 |
| `selectCountDistinct(col, ?alias = null)`                  | `static` | `COUNT(DISTINCT col)`                                  |
| `selectMax / selectMin / selectAvg / selectSum`            | `static` | Aggregate projections                                  |
| `selectUpper / selectLower / selectLength`                 | `static` | String-function projections                            |
| `selectMid(col, offset, length, ?alias)`                   | `static` | `MID(col, offset, length)` — MySQL                     |
| `selectLeft(col, length, ?alias)` / `selectRight(col, length, ?alias)` | `static` | MySQL substring helpers                    |
| `selectDistinct(col, ?alias)`                              | `static` | `DISTINCT(col)`                                        |
| `selectCoalesce(col, default = '0', ?alias)`               | `static` | `COALESCE(col, default)` — default escaped if non-numeric string |
| `selectConcat(array $columns, ?alias)`                     | `static` | `CONCAT(col, col, …)`                                  |

## QueryBuilder — FROM / table

| Method                                  | Returns  | Purpose                              |
|-----------------------------------------|----------|--------------------------------------|
| `from(table, ?alias = null)`            | `static` | Set FROM to a single table           |
| `addFrom(table, ?alias = null)`         | `static` | Append to FROM (comma-separated)     |
| `table(table)`                          | `static` | Alias of `from()` without alias arg  |

## QueryBuilder — grouping & ordering

| Method                                  | Returns  | Purpose                              |
|-----------------------------------------|----------|--------------------------------------|
| `groupBy(...$columns)`                  | `static` | Append GROUP BY columns (arrays flattened) |
| `orderBy(col, dir = 'ASC')`             | `static` | Append ORDER BY (`'ASC'` / `'DESC'` only) |
| `offset(int = 0)`                       | `static` | Set OFFSET (`abs()`-normalized)      |
| `limit(int)`                            | `static` | Set LIMIT (`abs()`-normalized)       |

## QueryBuilder — JOIN

| Method                                  | Returns  | Emits                                |
|-----------------------------------------|----------|--------------------------------------|
| `join(table, $on = null, type = 'INNER')` | `static` | Generic — `type` is the SQL keyword  |
| `innerJoin(table, $on)`                 | `static` | `INNER JOIN … ON …`                  |
| `leftJoin(table, $on)`                  | `static` | `LEFT JOIN … ON …`                   |
| `rightJoin(table, $on)`                 | `static` | `RIGHT JOIN … ON …`                  |
| `leftOuterJoin(table, $on)`             | `static` | `LEFT OUTER JOIN … ON …`             |
| `rightOuterJoin(table, $on)`            | `static` | `RIGHT OUTER JOIN … ON …`            |
| `naturalJoin(table)`                    | `static` | `NATURAL JOIN …` (no ON)             |
| `selfJoin(table, $on)`                  | `static` | Comma FROM + ON-as-WHERE             |

ON arguments accept `string`, `RawQuery`, or `Closure(QueryBuilder)`.

## QueryBuilder — WHERE / HAVING / ON

The signature shared by all three:

```
where  (col, op = '=', val = null, logical = 'AND')
having (col, op = '=', val = null, logical = 'AND')
on     (col, op = '=', val = null, logical = 'AND')
```

| Convenience method                       | Equivalent to                                |
|------------------------------------------|----------------------------------------------|
| `andWhere(col, op, val)`                 | `where(col, op, val, 'AND')`                 |
| `orWhere(col, op, val)`                  | `where(col, op, val, 'OR')`                  |

### NULL

| Helper                                   | Bucket   |
|------------------------------------------|----------|
| `whereIsNull(col, logical = 'AND')`      | dispatch |
| `andWhereIsNull(col)` / `orWhereIsNull(col)` | AND / OR |
| `whereIsNotNull(col, logical = 'AND')`   | dispatch |
| `andWhereIsNotNull(col)` / `orWhereIsNotNull(col)` | AND / OR |

### BETWEEN

| Helper                                              | Emits                  |
|-----------------------------------------------------|------------------------|
| `between(col, $a, $b, logical = 'AND')`             | `col BETWEEN a AND b`  |
| `andBetween(col, $a, $b)` / `orBetween(col, $a, $b)`| AND / OR variants      |
| `notBetween(col, $a, $b, logical = 'AND')`          | `col NOT BETWEEN …`    |
| `andNotBetween / orNotBetween`                      | AND / OR variants      |

Two-arg form `between(col, [a, b])` is also supported.

### IN

| Helper                                       | Emits             |
|----------------------------------------------|-------------------|
| `whereIn(col, $vals, logical = 'AND')`       | `col IN (…)`      |
| `whereNotIn(col, $vals, logical = 'AND')`    | `col NOT IN (…)`  |
| `andWhereIn / orWhereIn`                     | AND / OR variants |
| `andWhereNotIn / orWhereNotIn`               | AND / OR variants |

Numeric items inlined; strings parameterized; `RawQuery` passed through.

### LIKE family

| Helper                                                | Pattern shape   | NOT?  |
|-------------------------------------------------------|-----------------|-------|
| `like(col, val, type = 'both', logical = 'AND')`      | per `type`      | no    |
| `orLike / andLike`                                    | per `type`      | no    |
| `notLike(col, val, type = 'both', logical = 'AND')`   | per `type`      | yes   |
| `orNotLike / andNotLike`                              | per `type`      | yes   |
| `startLike(col, val, logical = 'AND')`                | `val%`          | no    |
| `orStartLike / andStartLike`                          | `val%`          | no    |
| `notStartLike(col, val, logical = 'AND')`             | `val%`          | yes   |
| `orStartNotLike / andStartNotLike`                    | `val%`          | yes   |
| `endLike(col, val, logical = 'AND')`                  | `%val`          | no    |
| `orEndLike / andEndLike`                              | `%val`          | no    |
| `notEndLike(col, val, logical = 'AND')`               | `%val`          | yes   |
| `orEndNotLike / andEndNotLike`                        | `%val`          | yes   |

`like(col, val, 'both')` → `%val%`. `'before'` / `'start'` → `val%`.
`'after'` / `'end'` → `%val`.

### REGEXP / SOUNDEX

| Helper                                            | Emits                                      |
|---------------------------------------------------|--------------------------------------------|
| `regexp(col, val, logical = 'AND')`               | `col REGEXP …` — MySQL                     |
| `andRegexp / orRegexp`                            | AND / OR variants                          |
| `soundex(col, val, logical = 'AND')`              | `SOUNDEX(col) LIKE CONCAT('%', TRIM(...))` |
| `andSoundex / orSoundex`                          | AND / OR variants                          |

### FIND\_IN\_SET (MySQL)

| Helper                                                | Emits                            |
|-------------------------------------------------------|----------------------------------|
| `findInSet(col, val, logical = 'AND')`                | `FIND_IN_SET(val, col)`          |
| `notFindInSet(col, val, logical = 'AND')`             | `NOT FIND_IN_SET(val, col)`      |
| `andFindInSet / orFindInSet`                          | AND / OR variants                |
| `andNotFindInSet / orNotFindInSet`                    | AND / OR variants                |

### Grouping & sub-queries

| Method                                                | Returns       |
|-------------------------------------------------------|---------------|
| `group(Closure $c, logical = 'AND')`                  | `static`      |
| `subQuery(Closure $c, ?alias = null, bool $isInterval = true)` | `RawQuery` |
| `raw(mixed)`                                          | `RawQuery`    |

## QueryBuilder — SET (INSERT / UPDATE)

| Method                                              | Returns  |
|-----------------------------------------------------|----------|
| `set(col, val = null, strict = true)`               | `static` |
| `addSet(col, val = null, strict = true)`            | `static` |

`$column` may be `string`, `RawQuery`, or an associative array (the
"full row" form, in which case `$value` must be omitted).

## QueryBuilder — compile

| Method                                                       | Returns  |
|--------------------------------------------------------------|----------|
| `generateSelectQuery(array $selector = [], array $conditions = [])` | `string` |
| `generateInsertQuery()`                                      | `string` |
| `generateBatchInsertQuery()`                                 | `string` |
| `generateUpdateQuery()`                                      | `string` |
| `generateUpdateBatchQuery(string $referenceColumn)`          | `string` |
| `generateDeleteQuery()`                                      | `string` |
| `__toString()`                                               | `string` (heuristic dispatch) |
| `isBatch()`                                                  | `bool` (any SET row has >1 column) |

All `generate*Query()` methods may throw `QueryBuilderException`.

## RawQuery

| Method                                  | Returns      |
|-----------------------------------------|--------------|
| `__construct(mixed $rawQuery)`          |              |
| `set(mixed $rawQuery)`                  | `self`       |
| `get()`                                 | `string`     |
| `__toString()`                          | `string`     |
| `RawQuery::raw(mixed)`                  | `RawQuery`   |

## Parameters (`ParameterInterface`)

| Method                                  | Returns      |
|-----------------------------------------|--------------|
| `set(string $key, mixed $value)`        | `self`       |
| `add(string\|RawQuery $key, mixed $value)` | `string` placeholder name or `'NULL'` |
| `get(?string $key = null, mixed $default = null)` | full map or single value or default |
| `all()`                                 | `array<string, mixed>` |
| `merge(array\|ParameterInterface ...)`  | `self`       |
| `reset()`                               | `self`       |

## Drivers (`DriverInterface`)

| Method                                  | Returns      |
|-----------------------------------------|--------------|
| `escapeIdentifier(string)`              | `string` (pure) |
| `getName()`                             | `?string`    |

Built-in drivers: `GenericDriver`, `MySqlDriver`, `PostgreSqlDriver`,
`SqliteDriver`. All extend `AbstractDriver`.

## Factory

| Method                                  | Returns                  |
|-----------------------------------------|--------------------------|
| `QueryBuilderFactory::createQueryBuilder(?string $driver = null)` | `QueryBuilderInterface` |

## Exceptions

| Class                                                          | Extends                |
|----------------------------------------------------------------|------------------------|
| `Exceptions\QueryBuilderException`                             | `\Exception`           |
| `Exceptions\QueryBuilderInvalidArgumentException`              | `\InvalidArgumentException` |

`QueryBuilderException` is raised for structural problems (missing
table, missing data, alias-on-non-interval sub-query, batch reference
column missing). `QueryBuilderInvalidArgumentException` is raised for
malformed input (unknown logical connector, non-`ASC|DESC` sort
direction).

---

**Back to the index:** [Table of contents →](index.md)
