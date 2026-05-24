# SELECT

This chapter covers everything that goes between `SELECT` and `WHERE` —
projections, table picking, grouping, ordering and pagination. WHERE / ON
clauses live in their [own chapter](where.md); JOINs in [joins.md](joins.md).

## Picking a table

```php
$qb->from('users');
// FROM `users`

$qb->from('users', 'u');
// FROM `users` AS `u`
```

To target multiple tables (comma-separated FROM list — uncommon but legal):

```php
$qb->from('users', 'u')
   ->addFrom('orders', 'o');
// FROM `users` AS `u`, `orders` AS `o`
```

`table()` is an alias of `from()` without the alias parameter — handy when
you literally just want one table:

```php
$qb->table('users');
// FROM `users`
```

When no projection is set, the compiler emits `SELECT *`:

```php
$qb->from('users');
echo $qb->generateSelectQuery();
// SELECT * FROM `users` WHERE 1
```

The trailing `WHERE 1` is intentional — it is a stable shape that callers
can always append further conditions to.

## Projections — `select()`

`select(...$columns)` takes any number of column references. Strings go
through the active driver's identifier escaping; [RawQuery](raw-queries.md)
values are inlined verbatim.

```php
$qb->select('id', 'name', $qb->raw('NOW() AS now'))
   ->from('users');
// SELECT `id`, `name`, NOW() AS now FROM `users` WHERE 1
```

Calling `select()` twice appends — it does not replace:

```php
$qb->select('id')
   ->select('name');
// SELECT `id`, `name`
```

Use `clearSelect()` to wipe the projection list:

```php
$qb->select('id')->clearSelect()->select('name');
// SELECT `name`
```

## Aliases — `selectAs()`

```php
$qb->selectAs('full_name', 'name')
   ->from('users');
// SELECT `full_name` AS `name` FROM `users` WHERE 1
```

For a `RawQuery` projection that needs an alias, either use `selectAs`
with the raw fragment, or append `AS …` directly in the raw string —
both work.

## Aggregate functions

The aggregate-projection helpers are shorthand for the common SQL function
calls. Each accepts an optional alias.

| Helper                | Emits             |
|-----------------------|-------------------|
| `selectCount('id')`              | `COUNT(id)`              |
| `selectCount('id', 'total')`     | `COUNT(id) AS total`     |
| `selectCountDistinct('email')`   | `COUNT(DISTINCT email)`  |
| `selectMax('age')`               | `MAX(age)`               |
| `selectMin('age')`               | `MIN(age)`               |
| `selectAvg('age')`               | `AVG(age)`               |
| `selectSum('amount')`            | `SUM(amount)`            |

Example combining several:

```php
$qb->selectCount('id', 'total')
   ->selectAvg('age', 'avg_age')
   ->from('users');
// SELECT COUNT(`id`) AS `total`, AVG(`age`) AS `avg_age` FROM `users` WHERE 1
```

## String functions

| Helper                              | Emits                          |
|-------------------------------------|--------------------------------|
| `selectUpper('name')`               | `UPPER(name)`                  |
| `selectLower('name')`               | `LOWER(name)`                  |
| `selectLength('bio')`               | `LENGTH(bio)`                  |
| `selectMid('name', 1, 5)`           | `MID(name, 1, 5)`              |
| `selectLeft('name', 3)`             | `LEFT(name, 3)`                |
| `selectRight('name', 3)`            | `RIGHT(name, 3)`               |
| `selectConcat(['fn', 'ln'])`        | `CONCAT(fn, ln)`               |
| `selectDistinct('name')`            | `DISTINCT(name)`               |

> ⚠️ `MID()` and `LEFT()` / `RIGHT()` are MySQL-flavored. For PostgreSQL
> or SQLite use `selectSum`/`selectAvg`/`selectCount` (all standard) and
> reach for [RawQuery](raw-queries.md) for `SUBSTRING(... FROM ... FOR ...)`.

## COALESCE

`selectCoalesce()` is the standard `COALESCE(column, default)` projection.
The default can be:

- a numeric literal — inlined as-is;
- a non-numeric string — treated as another identifier and escaped;
- a `RawQuery` — inlined verbatim.

```php
$qb->select('post.title')
   ->selectCoalesce('stat.views', 0, 'views')
   ->from('post')
   ->leftJoin('stat', 'stat.id = post.id');
// SELECT `post`.`title`, COALESCE(`stat`.`views`, 0) AS `views`
//   FROM `post` LEFT JOIN `stat` ON `stat`.`id` = `post`.`id` WHERE 1
```

A fallback to another column:

```php
$qb->selectCoalesce('stat.views', 'post.legacy_views', 'views');
// COALESCE(`stat`.`views`, `post`.`legacy_views`) AS `views`
```

## CONCAT

```php
$qb->selectConcat(['first_name', $qb->raw("' '"), 'last_name'], 'full_name')
   ->from('users');
// SELECT CONCAT(`first_name`, ' ', `last_name`) AS `full_name` FROM `users` WHERE 1
```

## GROUP BY

`groupBy()` is variadic and recursively flattens arrays:

```php
$qb->select('author_id')
   ->selectCount('id', 'post_count')
   ->from('post')
   ->groupBy('author_id');
// SELECT `author_id`, COUNT(`id`) AS `post_count`
//   FROM `post` WHERE 1 GROUP BY `author_id`

$qb->groupBy(['a', 'b'], 'c');
// GROUP BY `a`, `b`, `c`
```

Duplicate columns are deduplicated — calling `groupBy('a')` twice does
not emit `a, a`.

For `HAVING` (which is just WHERE-against-aggregates), see the
[WHERE chapter](where.md#having).

## ORDER BY

`orderBy(column, direction)` — direction is `'ASC'` (default) or `'DESC'`,
case-insensitive. Anything else throws `QueryBuilderInvalidArgumentException`.

```php
$qb->orderBy('id', 'desc')
   ->orderBy('name', 'ASC');
// ORDER BY `id` DESC, `name` ASC
```

Duplicate `(column, direction)` pairs are deduplicated.

## LIMIT / OFFSET

`limit()` / `offset()` set the corresponding clauses. Negative arguments
are reflected to their absolute value:

```php
$qb->limit(10);                 // LIMIT 10
$qb->offset(20)->limit(10);     // LIMIT 20, 10
$qb->offset(20);                // OFFSET 20    (without LIMIT)
$qb->limit(-5);                 // LIMIT 5      (sign-flipped)
```

## The compile-time shortcut

`generateSelectQuery()` accepts two optional arguments — a selector array
and a conditions array. Useful for one-liners:

```php
$qb->from('post');
echo $qb->generateSelectQuery(
    ['id', 'title'],
    ['status' => 1],
);
// SELECT `id`, `title` FROM `post` WHERE `status` = 1
```

Conditions with string keys turn into `where(key, value)`; entries with
integer keys are passed as a single argument (typically a `RawQuery`).

## Putting it together

A representative end-to-end SELECT:

```php
$qb->select('p.id', 'p.title')
   ->selectCount('c.id', 'comments')
   ->from('post', 'p')
   ->leftJoin('comment AS c', 'c.post_id = p.id')
   ->where('p.published', 1)
   ->groupBy('p.id')
   ->orderBy('p.created_at', 'DESC')
   ->limit(20);

echo $qb->generateSelectQuery();
// SELECT `p`.`id`, `p`.`title`, COUNT(`c`.`id`) AS `comments`
//   FROM `post` AS `p`
//   LEFT JOIN `comment` AS `c` ON `c`.`post_id` = `p`.`id`
//  WHERE `p`.`published` = 1
//  GROUP BY `p`.`id`
//  ORDER BY `p`.`created_at` DESC
//  LIMIT 20
```

**Next:** the [WHERE / HAVING / ON chapter →](where.md)
