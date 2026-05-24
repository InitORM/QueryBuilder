# JOINs

All seven JOIN flavors share a single entry point — `join()` — and seven
sugar methods that fix the JOIN keyword for you. The ON expression can
be supplied as a string, a `RawQuery`, or a closure that composes the
expression with the same fluent DSL.

## The seven shapes

| Helper                              | SQL keyword             |
|-------------------------------------|-------------------------|
| `innerJoin(table, on)`              | `INNER JOIN … ON …`     |
| `leftJoin(table, on)`               | `LEFT JOIN … ON …`      |
| `rightJoin(table, on)`              | `RIGHT JOIN … ON …`     |
| `leftOuterJoin(table, on)`          | `LEFT OUTER JOIN … ON …`|
| `rightOuterJoin(table, on)`         | `RIGHT OUTER JOIN … ON …`|
| `naturalJoin(table)`                | `NATURAL JOIN …`        |
| `selfJoin(table, on)`               | comma-FROM + WHERE      |
| `join(table, on, type)`             | generic — type is the keyword |

`naturalJoin()` does not take an ON parameter (NATURAL JOIN does not carry
one). `selfJoin()` compiles to a comma-separated FROM with the ON
expression promoted to WHERE — convenient when you need a join-like
self-reference without an explicit JOIN keyword.

## Simple string ON

The string is escaped via the active driver before being inlined:

```php
$qb->select('post.id', 'user.name AS author')
   ->from('post')
   ->innerJoin('user', 'user.id = post.user_id');
// SELECT `post`.`id`, `user`.`name` AS `author`
//   FROM `post`
//   INNER JOIN `user` ON `user`.`id` = `post`.`user_id`
//  WHERE 1
```

Numeric literals (and operators / punctuation) inside the ON string are
left untouched — only identifier-shaped tokens get quoted. See
[drivers.md](drivers.md) for the exact escape rules.

## Aliased tables

Just spell the alias inline in the table argument:

```php
$qb->from('users AS u')
   ->leftJoin('posts AS p', 'p.user_id = u.id');
// FROM `users` AS `u` LEFT JOIN `posts` AS `p` ON `p`.`user_id` = `u`.`id`
```

The shorter form `'users u'` (no `AS`) also works.

## SELF JOIN

`selfJoin()` is a convenience for the comma-FROM form where the "join"
condition lives in WHERE rather than ON:

```php
$qb->select('post.id', 'post.title', 'user.name AS authorName')
   ->table('post')
   ->selfJoin('user', 'user.id = post.user_id');
// SELECT `post`.`id`, `post`.`title`, `user`.`name` AS `authorName`
//   FROM `post`, `user`
//  WHERE `user`.`id` = `post`.`user_id`
```

Useful when the target dialect / query planner prefers an implicit join.

## NATURAL JOIN

```php
$qb->select('*')
   ->from('orders')
   ->naturalJoin('customers');
// SELECT * FROM `orders` NATURAL JOIN `customers` WHERE 1
```

NATURAL JOIN matches on identically-named columns; nothing else to say.

## Closure-based ON

Closure form gives you the full WHERE DSL for the ON expression. The
closure receives a fresh sub-builder; its **ON**, **WHERE** and
**HAVING** buckets are all folded back into the parent query:

```php
$qb->select('u.id', 'u.name', 'p.title')
   ->from('users AS u')
   ->where('u.status', 1)
   ->join('posts AS p', function (QueryBuilder $j) {
       $j->on('p.user_id', 'u.id')
         ->where('p.publisher_time', '>=', $j->raw('NOW()'));
   })
   ->join('categories AS c', function (QueryBuilder $j) {
       $j->on('c.id', 'p.category_id')
         ->on('c.blog_id', 'u.blog_id')
         ->where('c.status', 1)
         ->having($j->raw('COUNT(p.category_id) > 1'));
   })
   ->limit(5);

echo $qb->generateSelectQuery();
// SELECT `u`.`id`, `u`.`name`, `p`.`title` FROM `users` AS `u`
//   INNER JOIN `posts` AS `p`      ON `p`.`user_id` = `u`.`id`
//   INNER JOIN `categories` AS `c` ON `c`.`id` = `p`.`category_id` AND `c`.`blog_id` = `u`.`blog_id`
//   WHERE  `u`.`status` = 1
//          AND `p`.`publisher_time` >= NOW()
//          AND `c`.`status` = 1
//  HAVING  COUNT(p.category_id) > 1
//   LIMIT  5
```

The mental model:

- `on()` lands in the closure's **ON** bucket — those clauses appear after
  the `ON` keyword on the JOIN itself.
- `where()` lands in the closure's **WHERE** bucket — those clauses are
  appended to the outer query's WHERE.
- `having()` lands in the closure's **HAVING** bucket — same idea.

This lets you wire a JOIN that depends on outer-query conditions without
leaving the JOIN's "scope" syntactically.

## When the closure returns a string

If the closure returns a string (rather than calling builder methods),
that string is taken as the literal ON expression:

```php
$qb->from('users AS u')
   ->innerJoin('posts AS p', function () {
       return 'p.user_id = u.id';
   });
// FROM `users` AS `u` INNER JOIN `posts` AS `p` ON p.user_id = u.id
```

This form is rarely needed — the closure-with-methods form is more
expressive — but it's a useful escape hatch.

## Using a sub-query as the JOIN target

The first argument can also be a [sub-query](subqueries.md):

```php
$derived = $qb->subQuery(function (QueryBuilder $sub) {
    $sub->select('id', 'title', 'user_id')
        ->from('posts')
        ->where('user_id', 5);
}, 'p');

$qb->select('u.name', 'p.title')
   ->from('users AS u')
   ->join($derived, 'p.user_id = u.id', '');
// SELECT `u`.`name`, `p`.`title` FROM `users` AS `u`
//   JOIN (SELECT `id`, `title`, `user_id` FROM `posts` WHERE `user_id` = 5) AS `p`
//     ON `p`.`user_id` = `u`.`id`
//  WHERE 1
```

The third argument (`''` above) keeps the JOIN keyword empty for an
unqualified `JOIN`. Use `'INNER'`, `'LEFT'`, etc. as you would normally.

## Pitfalls

- **The closure-fold direction is one-way**: WHERE/HAVING clauses you
  add inside the closure escape into the outer query. If you only want
  an ON-expression, only call `on()` inside the closure.
- **`selfJoin()` writes to WHERE, not ON.** That means a subsequent
  call to `where()` lands in the same bucket — useful, but worth knowing.
- **NATURAL JOIN ignores ON entirely.** If you call
  `naturalJoin('customers')` after `where('customers.id', 5)`, the
  WHERE clause survives, but no `ON` is emitted on the JOIN itself.

**Next:** [INSERT, UPDATE, DELETE →](insert-update-delete.md)
