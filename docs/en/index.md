# InitORM QueryBuilder — Developer Guide

InitORM QueryBuilder is a small, dialect-aware SQL query builder for PHP.
It turns a fluent chain of method calls into a SQL string and a separate
[parameter bag](parameters.md) that you hand to PDO at execution time.

## Table of contents

1. **[Getting started](getting-started.md)** — install, the first query,
   factories, executing the generated SQL with PDO.
2. **[SELECT](select.md)** — projection helpers (`select*`), aggregates
   (`COUNT`, `SUM`, …), `GROUP BY`, `ORDER BY`, `LIMIT` / `OFFSET`.
3. **[WHERE / HAVING / ON](where.md)** — the comparison matrix, the
   value-shortcut, `BETWEEN`, `IN`, the LIKE family, NULL checks, REGEXP,
   SOUNDEX, FIND\_IN\_SET, and AND / OR connectors.
4. **[JOINs](joins.md)** — `innerJoin`, `leftJoin`, `rightJoin`,
   `leftOuterJoin`, `rightOuterJoin`, `selfJoin`, `naturalJoin`, plus the
   closure-based ON form that folds WHERE / HAVING side-conditions back into
   the outer query.
5. **[INSERT, UPDATE, DELETE](insert-update-delete.md)** — `set` / `addSet`,
   single-row + batch INSERT, single-row UPDATE, the `CASE / WHEN`-based
   batch UPDATE, DELETE.
6. **[Sub-queries](subqueries.md)** — `subQuery()` as a `WHERE IN` value,
   as a derived `FROM` table, inside a JOIN, or as a stand-alone fragment.
7. **[Grouped conditions](grouping.md)** — `group()` for parenthesized
   WHERE / HAVING / ON sub-expressions and the AND-vs-OR connector caveat.
8. **[Raw queries](raw-queries.md)** — when and how to use `RawQuery` to
   inline a SQL fragment without escaping or parameter binding.
9. **[Parameters](parameters.md)** — the parameter bag (`Parameters`),
   collision auto-suffixing, the NULL short-circuit, RawQuery key hashing,
   plugging the bag into PDO.
10. **[Drivers](drivers.md)** — built-in dialects (MySQL, PostgreSQL,
    SQLite, generic), identifier escape rules, and how to write a custom
    driver.
11. **[Security](security.md)** — threat model, defenses shipped in
    v2.0.0, application-level residual risks, and the safe-patterns
    cookbook.
12. **[Recipes](recipes.md)** — common scenarios distilled into runnable
    snippets: pagination, soft-delete, dynamic filters, upsert, ranking.
13. **[API reference](api-reference.md)** — a categorized table of every
    public method exposed by `QueryBuilderInterface`.

## How this documentation is structured

Every chapter ships **runnable PHP snippets** followed by the SQL they
generate. Examples use the `mysql` driver by default (with backtick
quoting); the same calls produce equivalent SQL under the other drivers,
only the quoting character changes. See [drivers.md](drivers.md) for the
side-by-side comparison.

A typical example:

```php
use InitORM\QueryBuilder\QueryBuilder;

$qb = new QueryBuilder('mysql');
$qb->select('id', 'name')
   ->from('users')
   ->where('status', 1);

echo $qb->generateSelectQuery();
// SELECT `id`, `name` FROM `users` WHERE `status` = 1
```

## Namespace map

```
InitORM\QueryBuilder\
├─ QueryBuilder              — the fluent facade (see this guide top-to-bottom)
├─ QueryBuilderInterface     — the public contract
├─ QueryBuilderFactory       — driver-string → builder
├─ QueryBuilderFactoryInterface
├─ Parameters                — the bound-parameter bag
├─ ParameterInterface
├─ RawQuery                  — inline SQL fragment (escape bypass)
│
├─ Clause\
│   ├─ StructureTrait        — structure access, clone, import/export
│   ├─ FromClauseTrait       — table / from / addFrom
│   ├─ SelectClauseTrait     — select* / groupBy / orderBy / limit / offset
│   ├─ JoinClauseTrait       — join / inner / left / right / outer / self / natural
│   ├─ WhereClauseTrait      — where / having / on + sugar
│   └─ SetClauseTrait        — set / addSet
│
├─ Compiler\
│   ├─ CompilerInterface     — marker
│   ├─ AbstractCompiler      — shared compile helpers
│   ├─ SelectCompiler
│   ├─ InsertCompiler
│   ├─ BatchInsertCompiler
│   ├─ UpdateCompiler
│   ├─ BatchUpdateCompiler
│   └─ DeleteCompiler
│
├─ Drivers\
│   ├─ DriverInterface       — dialect contract
│   ├─ AbstractDriver
│   ├─ GenericDriver         — no-op default
│   ├─ MySqlDriver
│   ├─ PostgreSqlDriver
│   └─ SqliteDriver
│
├─ Operator\
│   └─ Operators             — operator-set constants
│
├─ Helper\
│   ├─ SqlValueDetector      — placeholder / function / RawQuery detection
│   └─ BucketCompiler        — AND / OR clause joining
│
└─ Exceptions\
    ├─ QueryBuilderException
    └─ QueryBuilderInvalidArgumentException
```

## Conventions in this guide

- **Code blocks**: PHP snippets that compile to SQL are followed by the
  generated SQL as a `// comment` (or in a separate `sql` block when the
  query is long).
- **Bound parameters** are shown in their canonical `:foo` / `:foo_1` form
  — every example skips the PDO `execute()` call unless the example is
  specifically about parameter retrieval.
- **Driver hints**: where the chosen driver matters (identifier quoting,
  dialect-specific functions), the example notes it inline.
- **Caveats** are called out with a leading `> ⚠️` block.

Ready to start? Open **[Getting started →](getting-started.md)**
