# Grouped conditions

For parenthesized WHERE / HAVING / ON sub-expressions, use `group()`. It
wraps a sub-expression in parentheses and folds it into the chosen
bucket (AND or OR).

## Signature

```php
public function group(Closure $closure, string $logical = 'AND'): static
```

- The closure receives a fresh sub-builder. Its WHERE, HAVING and ON
  buckets are each independently wrapped in parentheses and merged back.
- `$logical` is `'AND'` (default) or `'OR'`. `'&&'` / `'||'` aliases are
  accepted.
- Anything else raises `QueryBuilderException`.

## A single grouped block

```php
$qb->from('users')
   ->where('status', 1)
   ->group(function (QueryBuilder $g) {
       $g->where('type', 3)
         ->where('type', 4);
   });

echo $qb->generateSelectQuery();
// SELECT * FROM `users`
//  WHERE `status` = 1 AND (`type` = 3 AND `type` = 4)
```

## OR-grouped block

```php
$qb->from('users')
   ->where('status', 1)
   ->group(function (QueryBuilder $g) {
       $g->where('country', 'TR')
         ->where('country', 'US');
   }, 'OR');
// Buckets:
//   where['AND'] = ['status = 1']
//   where['OR']  = ['(country = TR AND country = US)']
//
// Compiled — AND-bucket and OR-bucket joined by " OR ":
//   WHERE `status` = 1 OR (`country` = 'TR' AND `country` = 'US')
```

## Nested groups

`group()` can be nested arbitrarily deep — each closure spawns its own
sub-builder:

```php
$qb->select('id', 'title', 'content', 'url')
   ->from('posts')
   ->where('status', 1)
   ->group(function (QueryBuilder $g1) {
       $g1->where('user_id', 1)
          ->where('datetime', '>=', date('Y-m-d'));
   }, 'or')
   ->group(function (QueryBuilder $g2) {
       $g2->group(function (QueryBuilder $g3) {
              $g3->where('id', 2)->where('status', 3);
           }, 'or')
          ->group(function (QueryBuilder $g4) {
              $g4->where('id', 4)->where('status', 5);
           }, 'or');
   }, 'or');
// SELECT `id`, `title`, `content`, `url` FROM `posts`
//  WHERE `status` = 1
//        AND (`user_id` = 1 AND `datetime` >= :datetime)
//        OR  ((`id` = 2 AND `status` = 3) OR (`id` = 4 AND `status` = 5))
```

## Grouping with HAVING and ON

The closure's HAVING and ON buckets are folded independently — useful in
JOIN closures:

```php
$qb->from('posts AS p')
   ->innerJoin('users AS u', function (QueryBuilder $j) {
       $j->on('u.id', 'p.user_id')
         ->group(function (QueryBuilder $g) {
             $g->where('u.country', 'TR')
               ->where('u.country', 'US');
         }, 'OR')
         ->having($j->raw('COUNT(p.id) > 5'));
   });
// FROM `posts` AS `p`
//   INNER JOIN `users` AS `u` ON `u`.`id` = `p`.`user_id`
//  WHERE (`u`.`country` = :u_country AND `u`.`country` = :u_country_1)
// HAVING COUNT(p.id) > 5
```

## How the AND / OR buckets compile

Internally, each WHERE / HAVING / ON clause is filed into one of two
sub-lists per bucket — `AND` or `OR`. The bucket compiler joins them as:

```text
<all-AND-clauses joined by " AND ">
   + (if both lists non-empty: " OR ")
   + <all-OR-clauses joined by " OR ">
```

SQL precedence (`AND` binds tighter than `OR`) parses
`a AND b OR c` as `(a AND b) OR c`, which matches the natural reading
of a fluent chain like `where(a).where(b).orWhere(c)`:

```php
$qb->from('post')
   ->where('a', 1)
   ->where('b', 2)
   ->orWhere('c', 3);
// WHERE `a` = 1 AND `b` = 2 OR `c` = 3
//   (parsed: (`a` = 1 AND `b` = 2) OR `c` = 3)
```

A simpler chain — one AND clause + one OR clause:

```php
$qb->from('users')
   ->where('country', 'TR')
   ->orWhere('country', 'US');
// WHERE `country` = :country OR `country` = :country_1
```

> ℹ️ This was previously buggy (tracked as B26): the inter-bucket
> connector was hard-coded to `" AND "`, which silently collapsed every
> top-level `orX()` chain into an `AND`. The 2.0.0 release fixes it.

If you need something other than the precedence default — e.g.
`a OR (b AND c)` — use `group()` to introduce explicit parentheses:

```php
$qb->from('post')
   ->where('a', 1)
   ->group(function (QueryBuilder $g) {
       $g->where('b', 2)->where('c', 3);
   }, 'OR');
// WHERE `a` = 1 OR (`b` = 2 AND `c` = 3)
```

## Quick lookup

| You want…                              | Use                            |
|----------------------------------------|--------------------------------|
| `WHERE a AND (b AND c)`                | `where(a).group(fn ($g) => $g->where(b)->where(c))` |
| `WHERE a AND (b OR c)`                 | `where(a).group(fn ($g) => $g->where(b)->orWhere(c))` |
| `WHERE a OR (b)`                       | `where(a).group(fn ($g) => $g->where(b), 'OR')` |
| `WHERE (a OR b) AND (c OR d)`          | Two `group(..., 'OR')` chained — see "Nested groups" |

**Next:** [Raw queries →](raw-queries.md)
