# Raw queries

`RawQuery` is the explicit escape hatch for SQL fragments that should be
inlined verbatim — no identifier escaping, no parameter binding. Use it
where the fluent DSL does not (or should not) cover the case.

## When to use it

- **Database functions** — `NOW()`, `CURRENT_TIMESTAMP`, `JSON_EXTRACT(…)`,
  `INET_ATON(…)`, custom UDFs.
- **Dialect-specific operators** — PostgreSQL `~`, SQLite `LIKE` with
  ESCAPE, MySQL `MATCH () AGAINST ()`.
- **Inline sub-queries** — a static SQL fragment you have hand-written
  and want to embed.
- **Anything the builder doesn't expose directly** — set-returning
  functions, window functions, lateral joins, etc.

## When NOT to use it

> 🚨 **Never embed unsanitized user input.** `RawQuery` bypasses the
> parameter bag entirely — if you concatenate a `$_GET` value into the
> string, you've reintroduced SQL injection. Route user values through
> `where()` / `set()` / `setParameter()`.

## Three input forms

`RawQuery::__construct(mixed $rawQuery)` accepts:

### 1. A string

Used as-is:

```php
use InitORM\QueryBuilder\RawQuery;

$raw = new RawQuery('NOW()');
echo (string) $raw;
// NOW()
```

### 2. A Closure

The closure is invoked with a fresh `QueryBuilder`. It may either:

- **Return a string** — that string becomes the raw fragment.
- **Return a stringable object** — its `__toString()` is captured.
- **Return nothing** — the inner builder's `__toString()` is captured
  (i.e. the closure built a query against the supplied builder).

```php
$raw = new RawQuery(function () {
    return 'CURRENT_TIMESTAMP';
});
// CURRENT_TIMESTAMP

$raw = new RawQuery(function (QueryBuilder $qb) {
    $qb->select('id')->from('users')->where('active', 1);
});
// SELECT `id` FROM `users` WHERE `active` = 1
```

### 3. Any other value

Cast to string:

```php
$raw = new RawQuery(42);
echo (string) $raw;
// 42
```

## The two factory shortcuts

You can construct a `RawQuery` directly, but it's often nicer to use:

- **`$qb->raw($value)`** — the builder method, useful inside chains.
- **`RawQuery::raw($value)`** — the static factory, useful in places
  where you don't have a builder handy.

```php
// Inside a builder chain
$qb->from('users')->where('created_at', '>=', $qb->raw('NOW() - INTERVAL 7 DAY'));

// Outside a chain
$now = RawQuery::raw('NOW()');
```

## Where you can drop a RawQuery

Anywhere the builder accepts `RawQuery|string` (or `RawQuery|whatever`):

- **Projections** — `select($qb->raw('NOW() AS now'))`,
  `selectAs($qb->raw('JSON_EXTRACT(payload, "$.name")'), 'name')`.
- **Tables** — `from($qb->raw('users FORCE INDEX (idx_status)'))`.
- **JOIN ON** — `innerJoin('posts', $qb->raw('posts.user_id = users.id'))`.
- **WHERE values** — `where('created_at', '<', $qb->raw('NOW()'))`.
- **BETWEEN bounds** — `between('ts', $qb->raw('NOW() - INTERVAL 1 DAY'), $qb->raw('NOW()'))`.
- **IN values** — `whereIn('id', $qb->raw('(SELECT user_id FROM bans)'))`.
- **SET column values** — `set('updated_at', $qb->raw('NOW()'))`.

The builder checks each value with `SqlValueDetector::isSqlParameterOrFunction()`;
RawQuery instances always pass that check and are emitted verbatim.

## Patterns

### Database function on the right-hand side

```php
$qb->set([
    'created_at' => $qb->raw('NOW()'),
    'token'      => $qb->raw('UUID()'),
    'name'       => 'Muhammet',
]);
// (`created_at`, `token`, `name`) VALUES (NOW(), UUID(), :name)
```

### Dialect-specific WHERE

PostgreSQL ILIKE:

```php
$qb->from('users')
   ->where('email', $qb->raw('ILIKE :search'));
$qb->setParameter('search', '%@example.test');
```

### Free-form HAVING

```php
$qb->select('author_id')
   ->selectCount('id', 'post_count')
   ->from('post')
   ->groupBy('author_id')
   ->having($qb->raw('COUNT(id) > 5'));
// HAVING COUNT(id) > 5
```

### Inline sub-query without `subQuery()`

```php
$qb->whereIn('user_id', $qb->raw('(SELECT id FROM admin_users)'));
// WHERE `user_id` IN (SELECT id FROM admin_users)
```

This is the right form when the inner SELECT is **static** — you keep
the SQL self-contained and skip the closure indirection.
For dynamic inner SELECTs, prefer [`subQuery()`](subqueries.md).

### Closure that returns the builder it built

Convenient for one-liner derived expressions:

```php
$raw = $qb->raw(function (QueryBuilder $inner) {
    $inner->select('AVG(views)')->from('posts')->where('status', 1);
});
$qb->select('post.title')
   ->selectAs($raw, 'avg_views')
   ->from('post');
```

## Updating an existing RawQuery

```php
$raw = new RawQuery('NOW()');
$raw->set('CURRENT_TIMESTAMP');
echo (string) $raw;
// CURRENT_TIMESTAMP
```

`set()` returns the `RawQuery` instance for chaining.

## Reading a RawQuery's contents

```php
$raw = new RawQuery('NOW()');
echo $raw->get();   // 'NOW()'
echo (string) $raw; // 'NOW()'
```

`__toString()` and `get()` are equivalent.

**Next:** [Parameters →](parameters.md)
