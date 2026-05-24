# WHERE / HAVING / ON

These three clauses share the same internal builder: every method below
returns the same fragment shape, just into a different bucket (WHERE,
HAVING, or ON). The bulk of the DSL lives here.

## The three buckets

| Method   | Bucket   | Used by                    |
|----------|----------|----------------------------|
| `where`  | `where`  | SELECT, UPDATE, DELETE     |
| `having` | `having` | SELECT (aggregate filter)  |
| `on`     | `on`     | JOIN closure (see [joins.md](joins.md)) |

Each of them carries the same signature:

```php
where(column, operator = '=', value = null, logical = 'AND')
```

`logical` is `'AND'` (default) or `'OR'`. The aliases `'&&'` and `'||'`
are also accepted. An unknown connector throws
`QueryBuilderInvalidArgumentException`.

## The value-shortcut

Passing two arguments is the most common form:

```php
$qb->where('id', 5);
// WHERE `id` = 5
```

Internally the second argument is the operator slot; when the value slot
is `null` **and** the operator slot is not actually a SQL operator, the
two are swapped and `=` is assumed. Result: `where('id', 5)` and
`where('id', '=', 5)` produce identical SQL.

> ⚠️ Boolean values are valid via this shortcut after the v2.0.0 fix
> (the previous loose `in_array` comparison made `where('active', true)`
> collapse to `WHERE active`). Booleans are routed through the parameter
> bag.

## Comparison operators

```php
$qb->where('age', '>=', 18);            // WHERE `age` >= 18
$qb->where('status', '!=', 'banned');   // WHERE `status` != :status
$qb->where('score', '<>', 0);           // WHERE `score` <> 0
```

The full set: `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`. Anything not in this
list (or in the LIKE / BETWEEN / IN families described below) is treated
as a SQL operator and inlined verbatim.

## AND / OR connectors

```php
$qb->from('users')
   ->where('country', 'TR')
   ->andWhere('active', 1);
// WHERE `country` = :country AND `active` = 1

$qb->from('users')
   ->where('country', 'TR')
   ->orWhere('country', 'US');
// see the caveat in grouping.md before relying on this shape
```

`andWhere` / `orWhere` are convenience aliases for
`where(..., 'AND')` / `where(..., 'OR')`. The same applies to **every**
where-sugar method below: each one comes in a base form, an `and*` form
and an `or*` form.

> ℹ️ A top-level chain like `where(a).orWhere(b)` compiles to `a OR b`
> at the SQL level — see [Grouped conditions](grouping.md#how-the-and--or-buckets-compile)
> for the exact joining rules when you mix AND and OR clauses.

## NULL checks

| Helper                          | Emits                          |
|---------------------------------|--------------------------------|
| `whereIsNull(col)`              | `col IS NULL`                  |
| `orWhereIsNull(col)`            | `col IS NULL`  (OR bucket)     |
| `andWhereIsNull(col)`           | `col IS NULL`  (AND bucket)    |
| `whereIsNotNull(col)`           | `col IS NOT NULL`              |
| `orWhereIsNotNull(col)`         | `col IS NOT NULL` (OR bucket)  |
| `andWhereIsNotNull(col)`        | `col IS NOT NULL` (AND bucket) |

Example:

```php
$qb->from('post')
   ->whereIsNull('deleted_at');
// SELECT * FROM `post` WHERE `deleted_at` IS NULL
```

## BETWEEN

The bounds may be supplied as two arguments or as a single two-element
array (the latter is the long-standing convenience form):

```php
$qb->where('id', 'BETWEEN', [10, 20]);
$qb->between('id', 10, 20);            // identical
$qb->between('id', [10, 20]);          // identical
// WHERE `id` BETWEEN 10 AND 20
```

Numeric bounds are inlined; strings, dates, and other values flow through
the parameter bag:

```php
$qb->between('date', '2026-01-01', '2026-12-31');
// WHERE `date` BETWEEN :date AND :date_1
```

Mixing parameters with a `RawQuery` (e.g. a SQL function) on one side
works as expected:

```php
$qb->between('date', '2026-01-01', $qb->raw('NOW()'));
// WHERE `date` BETWEEN :date AND NOW()
```

| Helper                                         | Variant              |
|------------------------------------------------|----------------------|
| `between(col, $a, $b)` / `andBetween(...)`     | AND `BETWEEN`        |
| `orBetween(col, $a, $b)`                       | OR `BETWEEN`         |
| `notBetween(col, $a, $b)` / `andNotBetween(...)` | AND `NOT BETWEEN`  |
| `orNotBetween(col, $a, $b)`                    | OR `NOT BETWEEN`     |

## IN / NOT IN

```php
$qb->whereIn('id', [1, 2, 3]);            // WHERE `id` IN (1, 2, 3)
$qb->whereIn('country', ['TR', 'US']);    // WHERE `country` IN (:country, :country_1)
$qb->whereNotIn('id', [4, 5]);            // WHERE `id` NOT IN (4, 5)
```

Numeric items are inlined; strings are parameterized with collision
auto-suffixing. The array is deduplicated before emission:

```php
$qb->whereIn('id', [1, 2, 2, 3, 1]);
// WHERE `id` IN (1, 2, 3)
```

A `RawQuery` (e.g. a sub-query) is passed through verbatim:

```php
$qb->whereIn('id', $qb->raw('(SELECT user_id FROM bans)'));
// WHERE `id` IN (SELECT user_id FROM bans)
```

| Helper                                                       |
|--------------------------------------------------------------|
| `whereIn(col, vals)` / `andWhereIn(col, vals)` / `orWhereIn(col, vals)` |
| `whereNotIn(col, vals)` / `andWhereNotIn(col, vals)` / `orWhereNotIn(col, vals)` |

## LIKE family

The LIKE helpers wrap the supplied value with `%` according to the chosen
**type** (`'both'`, `'before'`/`'start'`, `'after'`/`'end'`). After the
v2.0.0 fix the semantics are:

| Helper                  | Value `'foo'` → pattern   | SQL              |
|-------------------------|---------------------------|------------------|
| `like(col, 'foo')`      | `%foo%`                   | `col LIKE :p`    |
| `notLike(col, 'foo')`   | `%foo%`                   | `col NOT LIKE :p`|
| `startLike(col, 'foo')` | `foo%`                    | `col LIKE :p`    |
| `notStartLike(col, 'foo')` | `foo%`                 | `col NOT LIKE :p`|
| `endLike(col, 'foo')`   | `%foo`                    | `col LIKE :p`    |
| `notEndLike(col, 'foo')`| `%foo`                    | `col NOT LIKE :p`|

Each helper has the usual `and*` / `or*` variants:

```php
$qb->from('user')->orLike('username', 'php');
// WHERE `username` LIKE :username   (in the OR bucket)
```

If you supply a value that is already a placeholder (e.g. `':needle'`),
the wildcard wrapping is skipped — the placeholder is emitted as-is:

```php
$qb->from('user')->like('username', $qb->raw(':needle'));
// WHERE `username` LIKE :needle
```

## REGEXP

```php
$qb->regexp('username', '^[a-z]+$');
// WHERE `username` REGEXP :username
```

> ⚠️ `REGEXP` is MySQL-flavored POSIX. PostgreSQL users prefer `~`;
> reach for [RawQuery](raw-queries.md) to spell that out.

`andRegexp(col, val)` and `orRegexp(col, val)` are the connector-specific
variants.

## SOUNDEX

`soundex()` produces a fuzzy match against the SOUNDEX of the supplied
value:

```php
$qb->soundex('name', 'Robert');
// WHERE SOUNDEX(`name`) LIKE CONCAT('%', TRIM(TRAILING '0' FROM SOUNDEX(:name)), '%')
```

`andSoundex(col, val)` and `orSoundex(col, val)` exist as expected.

## FIND\_IN\_SET

MySQL-specific set-membership check:

```php
$qb->findInSet('roles', 'admin');
// WHERE FIND_IN_SET(:roles, `roles`)

$qb->notFindInSet('roles', 'admin');
// WHERE NOT FIND_IN_SET(:roles, `roles`)
```

> 🔐 The v2.0.0 release fixed an SQL-injection vector in this method —
> raw string values are now always parameterized. See the CHANGELOG entry
> for B28.

`andFindInSet`, `orFindInSet`, `andNotFindInSet`, `orNotFindInSet` are the
connector variants.

## HAVING

`having()` accepts the same arguments as `where()` and routes into the
HAVING bucket. Combine with `GROUP BY`:

```php
$qb->select('author_id')
   ->selectCount('id', 'post_count')
   ->from('post')
   ->groupBy('author_id')
   ->having('post_count', '>', 5);
// SELECT `author_id`, COUNT(`id`) AS `post_count`
//   FROM `post` WHERE 1
//  GROUP BY `author_id`
// HAVING `post_count` > 5
```

The full helper family (between, in, like, …) is **WHERE-only** — for
HAVING you call `having()` directly with the operator you need, or use
`raw()` for complex aggregate predicates:

```php
$qb->having($qb->raw('COUNT(id) > 5'));
```

## ON (for JOIN closures)

`on()` is functionally identical to `where()` / `having()`, except that
**string values with a dot are treated as column references** rather than
parameter values:

```php
$qb->on('c.id', 'p.category_id');
// ON `c`.`id` = `p`.`category_id`   — the right-hand side is NOT parameterized
```

That makes it natural to compose JOIN ON expressions via the closure form
of `join()`; see [joins.md](joins.md).

## Grouping conditions

For parenthesized conditions, use [`group()`](grouping.md):

```php
$qb->where('status', 1)
   ->group(function (QueryBuilder $g) {
       $g->where('type', 3)
         ->where('type', 4);
   });
// WHERE `status` = 1 AND (`type` = 3 AND `type` = 4)
```

## Quick lookup

| You want…                                  | Use                                     |
|--------------------------------------------|-----------------------------------------|
| `col = value`                              | `where(col, value)` or `where(col, '=', value)` |
| `col >= value`                             | `where(col, '>=', value)`               |
| `col IS NULL`                              | `whereIsNull(col)`                      |
| `col IS NOT NULL`                          | `whereIsNotNull(col)`                   |
| `col BETWEEN a AND b`                      | `between(col, a, b)`                    |
| `col IN (...)`                             | `whereIn(col, [...])`                   |
| `col LIKE '%foo%'`                         | `like(col, 'foo')`                      |
| `col LIKE 'foo%'`                          | `startLike(col, 'foo')`                 |
| `col LIKE '%foo'`                          | `endLike(col, 'foo')`                   |
| `col REGEXP '…'`                           | `regexp(col, '…')`                      |
| `SOUNDEX(col) ~= SOUNDEX(value)`           | `soundex(col, value)`                   |
| `FIND_IN_SET(value, col)`                  | `findInSet(col, value)`                 |
| parenthesized group                        | `group(closure, 'AND' \| 'OR')`         |
| raw SQL fragment                           | `where($qb->raw('…'))`                  |

**Next:** [JOINs →](joins.md)
