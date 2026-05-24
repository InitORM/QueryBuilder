# Sub-queries

`subQuery()` builds a SELECT inside a closure that receives a fresh
builder. The result is returned as a `RawQuery`, which you can drop into
any of the contexts that accept one — `WHERE IN`, `FROM`, `JOIN`, or
standalone.

## Signature

```php
public function subQuery(
    Closure $closure,
    ?string $alias = null,
    bool $isIntervalQuery = true
): RawQuery
```

- **`$closure`** — receives a fresh `QueryBuilder` (cloned from the
  parent, with structure reset). Use the same fluent API to build the
  inner SELECT.
- **`$alias`** — optional alias. Only valid when `$isIntervalQuery` is
  `true` (otherwise the call throws).
- **`$isIntervalQuery`** — when `true` (default), the emitted SQL is
  wrapped in parentheses. Set to `false` for a stand-alone fragment.

## In a `WHERE IN`

The most common use:

```php
$qb->select('u.name')
   ->from('users AS u')
   ->whereIn('u.id', $qb->subQuery(function (QueryBuilder $sub) {
       $sub->select('id')
           ->from('roles')
           ->where('name', 'admin');
   }));

echo $qb->generateSelectQuery();
// SELECT `u`.`name`
//   FROM `users` AS `u`
//  WHERE `u`.`id` IN (SELECT `id` FROM `roles` WHERE `name` = :name)
```

The parentheses come from `$isIntervalQuery = true` — `whereIn()` is
happy to consume a `RawQuery` directly.

## As a derived FROM table

```php
$derived = $qb->subQuery(function (QueryBuilder $sub) {
    $sub->select('id', 'title', 'user_id')
        ->from('posts')
        ->where('user_id', 5);
}, 'p');

$qb->select('u.name', 'p.title')
   ->from('users AS u')
   ->join($derived, 'p.user_id = u.id', '');

echo $qb->generateSelectQuery();
// SELECT `u`.`name`, `p`.`title`
//   FROM `users` AS `u`
//   JOIN (SELECT `id`, `title`, `user_id` FROM `posts` WHERE `user_id` = 5) AS `p`
//     ON `p`.`user_id` = `u`.`id`
//  WHERE 1
```

The alias goes on the sub-query call (`'p'`); the JOIN keyword can be
left empty (`''`) for an unqualified `JOIN`, or set to `'INNER'`,
`'LEFT'`, etc.

## Standalone (no parentheses)

When you want the raw inner SELECT without the wrapping parentheses,
pass `$isIntervalQuery = false`:

```php
$raw = $qb->subQuery(function (QueryBuilder $sub) {
    $sub->select('id')->from('users')->where('active', 1);
}, null, false);

echo (string) $raw;
// SELECT `id` FROM `users` WHERE `active` = 1
```

Useful when you want to compose a larger SQL fragment by hand and slot a
generated SELECT into it.

> ⚠️ Passing an alias **and** `$isIntervalQuery = false` raises:
> *To define alias to a subquery, it must be an inner query.* Aliases
> only make sense on parenthesized fragments.

## Inside `where()` directly

`whereIn()` is the most common context, but any operator that accepts a
`RawQuery` works:

```php
$qb->from('users')
   ->where('id', '=', $qb->subQuery(function (QueryBuilder $sub) {
       $sub->select('user_id')->from('latest_login')->orderBy('logged_at', 'DESC')->limit(1);
   }));
// WHERE `id` = (SELECT `user_id` FROM `latest_login` WHERE 1 ORDER BY `logged_at` DESC LIMIT 1)
```

## Sub-query parameters

The closure receives a **clone** of the outer builder. That clone has
its own structure **and its own parameter bag**, so sub-query parameters
do not collide with the outer query's parameters.

```php
$qb->select('u.name')
   ->from('users AS u')
   ->whereIn('u.role_id', $qb->subQuery(function (QueryBuilder $sub) {
       $sub->select('id')
           ->from('roles')
           ->whereIn('name', ['admin', 'moderator']);
   }));
// SELECT `u`.`name`
//   FROM `users` AS `u`
//  WHERE `u`.`role_id` IN (
//      SELECT `id` FROM `roles` WHERE `name` IN (:name, :name_1)
//  )
```

The `:name` / `:name_1` placeholders live in the inner builder's bag —
but because the resulting SQL is captured as a `RawQuery` and embedded
verbatim into the outer query, the placeholders are already part of the
final SQL string. When you execute the outer query, those placeholders
need to come from the **outer** parameter bag — which means you have to
pre-bind them with `setParameter()` if you intend to PDO-execute the
result.

A simpler escape hatch when sub-query values are dynamic and must reach
PDO: hoist them out as outer parameters:

```php
$adminRole = 'admin';
$qb->setParameter('admin_role', $adminRole);
$qb->select('u.name')
   ->from('users AS u')
   ->whereIn('u.role_id', $qb->subQuery(function (QueryBuilder $sub) {
       $sub->select('id')->from('roles')->where('name', $sub->raw(':admin_role'));
   }));
// The placeholder :admin_role now lives in the OUTER bag.
```

## Multiple sub-queries

You can compose more than one sub-query in the same outer query; each
gets its own scoped builder:

```php
$qb->select('u.name')
   ->from('users AS u')
   ->whereIn('u.id', $qb->subQuery(fn (QueryBuilder $sub) =>
       $sub->select('user_id')->from('orders')->where('status', 'paid')
   ))
   ->andWhereNotIn('u.id', $qb->subQuery(fn (QueryBuilder $sub) =>
       $sub->select('user_id')->from('bans')
   ));
```

## Reaching for `RawQuery` instead

When the sub-query is static — same SQL on every call — you can just
write it as a `RawQuery`:

```php
$qb->whereIn('id', $qb->raw('(SELECT user_id FROM bans)'));
```

That skips the closure indirection. Reserve `subQuery()` for cases where
the inner SELECT is itself dynamic.

**Next:** [Grouped conditions →](grouping.md)
