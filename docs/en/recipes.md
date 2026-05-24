# Recipes

A grab-bag of patterns distilled from real applications. Each recipe is
self-contained — copy, adjust the table / column names, and you've got a
working snippet.

All examples use the `mysql` driver. Substitute `'pgsql'` / `'sqlite'`
in the constructor for other dialects — the SQL emitted is otherwise
identical except for the quoting character.

## Pagination

A classic page-N-of-K listing. Inputs: page (1-based), page size.

```php
function paginatedPosts(PDO $pdo, int $page = 1, int $perPage = 20): array
{
    $page    = max(1, $page);
    $offset  = ($page - 1) * $perPage;

    $qb = new InitORM\QueryBuilder\QueryBuilder('mysql');
    $qb->select('id', 'title', 'created_at')
       ->from('post')
       ->where('published', 1)
       ->orderBy('created_at', 'DESC')
       ->offset($offset)
       ->limit($perPage);

    $stmt = $pdo->prepare($qb->generateSelectQuery());
    $stmt->execute($qb->getParameter()->all());

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

For a total count alongside, do it in a sibling builder:

```php
$count = $qb->newBuilder();
$count->selectCount('*', 'total')->from('post')->where('published', 1);
$total = (int) $pdo->query($count->generateSelectQuery())->fetchColumn();
```

## Soft delete

Convention: `deleted_at` is `NULL` for live rows, set to a timestamp on
delete.

```php
// Reading — exclude soft-deleted rows
$qb->from('post')
   ->whereIsNull('deleted_at');

// Soft-deleting a single row
$qb->from('post')
   ->where('id', 42)
   ->set('deleted_at', $qb->raw('NOW()'));
$pdo->prepare($qb->generateUpdateQuery())->execute($qb->getParameter()->all());

// Restoring
$qb->resetStructure()
   ->from('post')
   ->where('id', 42)
   ->set('deleted_at', null);
$pdo->prepare($qb->generateUpdateQuery())->execute($qb->getParameter()->all());
```

## Dynamic search filters

Build a search query whose conditions depend on which inputs the caller
supplied:

```php
function search(PDO $pdo, array $filters): array
{
    $qb = new InitORM\QueryBuilder\QueryBuilder('mysql');
    $qb->from('product');

    if (!empty($filters['name'])) {
        $qb->like('name', $filters['name']);
    }
    if (!empty($filters['category_id'])) {
        $qb->where('category_id', (int) $filters['category_id']);
    }
    if (!empty($filters['min_price'])) {
        $qb->where('price', '>=', (float) $filters['min_price']);
    }
    if (!empty($filters['max_price'])) {
        $qb->where('price', '<=', (float) $filters['max_price']);
    }
    if (!empty($filters['in_stock'])) {
        $qb->where('stock', '>', 0);
    }

    $qb->orderBy($filters['sort'] ?? 'created_at', 'DESC');

    $stmt = $pdo->prepare($qb->generateSelectQuery());
    $stmt->execute($qb->getParameter()->all());
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

## Multi-tenant scoping

Every query is gated by a tenant ID. Wrap the building step:

```php
function tenantScopedBuilder(int $tenantId): InitORM\QueryBuilder\QueryBuilder
{
    return (new InitORM\QueryBuilder\QueryBuilder('mysql'))
        ->where('tenant_id', $tenantId);
}

$qb = tenantScopedBuilder(42);
$qb->select('*')->from('post')->orderBy('id', 'DESC');
// SELECT * FROM `post` WHERE `tenant_id` = 42 ORDER BY `id` DESC
```

## Batch insert from an array of records

```php
$qb = new InitORM\QueryBuilder\QueryBuilder('mysql');
$qb->from('post');

foreach ($records as $record) {
    $qb->set($record);
}
$sql = $qb->generateBatchInsertQuery();
$pdo->prepare($sql)->execute($qb->getParameter()->all());
```

Missing columns in any record compile to `NULL` — useful when records
are partially populated.

## Batch UPDATE (CASE/WHEN)

Given an array of `[id => [...changes]]` pairs:

```php
$qb = new InitORM\QueryBuilder\QueryBuilder('mysql');
$qb->from('post');

foreach ($changes as $id => $row) {
    $qb->set(['id' => $id] + $row);
}

$sql = $qb->generateUpdateBatchQuery('id');
$pdo->prepare($sql)->execute($qb->getParameter()->all());
// UPDATE `post`
//    SET `title` = CASE WHEN `id` = 1 THEN :title WHEN `id` = 2 THEN :title_1 ELSE `title` END,
//        ...
//  WHERE `id` IN (1, 2, …)
```

## "Upsert" via INSERT ... ON DUPLICATE KEY UPDATE (MySQL)

The builder does not yet emit the `ON DUPLICATE KEY UPDATE` tail, but
you can hand-craft the trailer with `RawQuery`:

```php
$qb->from('counter')
   ->set(['key' => 'visits', 'value' => 1]);

$sql = $qb->generateInsertQuery();
$sql = rtrim($sql, ';') . ' ON DUPLICATE KEY UPDATE `value` = `value` + 1;';
$pdo->prepare($sql)->execute($qb->getParameter()->all());
```

Same idea on PostgreSQL with `ON CONFLICT (...) DO UPDATE SET …`.

## "Most recent N per group" via a sub-query

```php
$qb = new InitORM\QueryBuilder\QueryBuilder('mysql');
$qb->select('p.*')
   ->from('post AS p')
   ->whereIn('p.id', $qb->subQuery(function (InitORM\QueryBuilder\QueryBuilder $sub) {
       $sub->select($sub->raw('MAX(id)'))
           ->from('post')
           ->groupBy('user_id');
   }));
// SELECT `p`.* FROM `post` AS `p`
//  WHERE `p`.`id` IN (SELECT MAX(id) FROM `post` WHERE 1 GROUP BY `user_id`)
```

## Active-record style helper

Wrap the builder in a tiny per-table helper to cut boilerplate:

```php
final class UserQueries
{
    public function __construct(private PDO $pdo) {}

    private function qb(): InitORM\QueryBuilder\QueryBuilder
    {
        return (new InitORM\QueryBuilder\QueryBuilder('mysql'))->from('users');
    }

    public function findActiveById(int $id): ?array
    {
        $qb = $this->qb()->where('id', $id)->whereIsNull('deleted_at')->limit(1);
        $stmt = $this->pdo->prepare($qb->generateSelectQuery());
        $stmt->execute($qb->getParameter()->all());
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function markDeleted(int $id): void
    {
        $qb = $this->qb()
            ->where('id', $id)
            ->set('deleted_at', $qb->raw('NOW()'));
        $this->pdo->prepare($qb->generateUpdateQuery())
                  ->execute($qb->getParameter()->all());
    }
}
```

## Ranking & windowed counts (MySQL 8 / PostgreSQL)

Window functions aren't first-class in the DSL, but `RawQuery` slots
right in:

```php
$qb->select('id', 'user_id', 'created_at')
   ->select($qb->raw('ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) AS rn'))
   ->from('post');
// SELECT `id`, `user_id`, `created_at`,
//        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) AS rn
//   FROM `post` WHERE 1
```

## Auditing a query before execution

Sometimes you want to log the SQL before sending it off:

```php
$sql        = $qb->generateSelectQuery();
$parameters = $qb->getParameter()->all();

$logger->debug('SQL', ['sql' => $sql, 'params' => $parameters]);

$stmt = $pdo->prepare($sql);
$stmt->execute($parameters);
```

`(string) $qb` triggers the dispatch heuristic ([insert-update-delete.md](insert-update-delete.md#the-__tostring-heuristic))
— `generate*Query()` is more explicit and recommended in production.

## A safe-by-default helper

Wrap PDO + builder in a single function so callers can't forget either
side of the parameter handoff:

```php
function exec(PDO $pdo, InitORM\QueryBuilder\QueryBuilderInterface $qb, string $type = 'select'): PDOStatement
{
    $sql = match ($type) {
        'select'         => $qb->generateSelectQuery(),
        'insert'         => $qb->generateInsertQuery(),
        'insert-batch'   => $qb->generateBatchInsertQuery(),
        'update'         => $qb->generateUpdateQuery(),
        'update-batch'   => $qb->generateUpdateBatchQuery('id'),
        'delete'         => $qb->generateDeleteQuery(),
    };

    $stmt = $pdo->prepare($sql);
    $stmt->execute($qb->getParameter()->all());
    return $stmt;
}
```

## More patterns?

Got a recipe you'd like to see here? Open an issue or PR against
[InitORM/QueryBuilder](https://github.com/InitORM/QueryBuilder/issues).

**Next:** [API reference →](api-reference.md)
