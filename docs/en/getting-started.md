# Getting started

This page walks you through installing the library, building your first
query, and executing it against a live database with PDO. Five minutes,
end to end.

## Requirements

- **PHP ≥ 8.1**
- **`ext-pdo`** — only the consumer needs it; the builder itself never
  touches a connection.

## Install

```bash
composer require initorm/query-builder
```

The package is **dependency-free at runtime**. It pulls in `phpunit`,
`squizlabs/php_codesniffer` and `phpstan/phpstan` only as `require-dev`.

## Hello, query

```php
use InitORM\QueryBuilder\QueryBuilder;

require __DIR__ . '/vendor/autoload.php';

$qb = new QueryBuilder('mysql');

$qb->select('id', 'name')
   ->from('users')
   ->where('status', 1);

echo $qb->generateSelectQuery();
// SELECT `id`, `name` FROM `users` WHERE `status` = 1
```

The constructor accepts a driver name (`'mysql'`, `'pgsql'`/`'postgres'`/
`'postgresql'`, `'sqlite'`) or `null` for the no-op generic driver. Pick
the one that matches your target database; the only behavior that changes
is identifier quoting.

## The factory

For dependency-injected setups, use the factory instead of `new`:

```php
use InitORM\QueryBuilder\QueryBuilderFactory;

$factory = new QueryBuilderFactory();

$qb = $factory->createQueryBuilder('pgsql');
// instance of QueryBuilderInterface, configured for PostgreSQL
```

Reuse the same factory across requests; it carries no state.

## Executing the SQL with PDO

The builder returns a SQL string and stores bound values in a separate
[parameter bag](parameters.md). You hand both to PDO:

```php
$pdo = new PDO(
    'mysql:host=localhost;dbname=app;charset=utf8mb4',
    'app',
    'secret',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$qb->select('id', 'name', 'email')
   ->from('users')
   ->where('status', 1)
   ->andWhere('country', 'TR')
   ->orderBy('id', 'DESC')
   ->limit(20);

$sql        = $qb->generateSelectQuery();
$parameters = $qb->getParameter()->all();

$stmt = $pdo->prepare($sql);
$stmt->execute($parameters);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    var_dump($row);
}
```

The parameters bag is just an array keyed by placeholder name:

```php
$qb->getParameter()->all();
// [
//     ':country' => 'TR',
// ]
```

> ⚠️ Integer values (and a handful of other "safe" forms — see
> [Parameters](parameters.md) for the full list) are **inlined directly**
> into the SQL rather than parameterized. That keeps simple lookups
> readable while still routing every user-supplied string through PDO.

## Re-using a builder

Every public method that mutates the structure returns `$this`, so chaining
is the usual style. When you want to start over without throwing away the
builder, reset its structure:

```php
$qb->resetStructure();  // blank-slate
$qb->select('id')->from('posts');
```

To carry the structure across calls, snapshot it:

```php
$snapshot = $qb->exportQB();
// later …
$qb->importQB($snapshot);
```

To spawn a sibling builder configured for the same dialect:

```php
$other = $qb->newBuilder();   // fresh structure, same driver
```

## A first INSERT and UPDATE

```php
$qb->resetStructure()
   ->from('users')
   ->set([
       'name'  => 'Muhammet',
       'email' => 'info@muhammetsafak.com.tr',
   ]);

echo $qb->generateInsertQuery();
// INSERT INTO `users` (`name`, `email`) VALUES (:name, :email);

$qb->resetStructure()
   ->from('users')
   ->where('id', 5)
   ->set(['name' => 'Updated']);

echo $qb->generateUpdateQuery();
// UPDATE `users` SET `name` = :name WHERE `id` = 5
```

See [INSERT / UPDATE / DELETE](insert-update-delete.md) for batch shapes,
`CASE / WHEN`-based batch updates, and the edge-case error paths.

## What's next

- The [SELECT chapter](select.md) covers every projection helper and the
  ordering / pagination knobs.
- The [WHERE chapter](where.md) is where most of the API surface lives —
  comparison operators, BETWEEN, IN, the LIKE family, NULL checks, REGEXP,
  SOUNDEX, FIND\_IN\_SET.
- For complex queries, jump to [Sub-queries](subqueries.md),
  [JOINs](joins.md) and [Grouped conditions](grouping.md).

Need a quick lookup? The [API reference](api-reference.md) lists every
public method on one page.
