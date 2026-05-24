# INSERT, UPDATE, DELETE

The non-SELECT shapes are all driven by **`set()`** / **`addSet()`** for
data and **`where()`** for filters. Which compile method you call decides
whether you get an INSERT, UPDATE, or batch variant.

## INSERT (single row)

`set()` accepts either an associative array (full row) or two arguments
(single column / value):

```php
$qb->from('users')->set([
    'name'    => 'Muhammet',
    'email'   => 'info@muhammetsafak.com.tr',
    'status'  => true,
]);

echo $qb->generateInsertQuery();
// INSERT INTO `users` (`name`, `email`, `status`)
//   VALUES (:name, :email, :status);
```

Two-argument form:

```php
$qb->from('counter')->set('value', 42);
echo $qb->generateInsertQuery();
// INSERT INTO `counter` (`value`) VALUES (42);
```

(Integer values are inlined — see [Parameters](parameters.md).)

## INSERT (batch)

Call `set()` multiple times — each call appends one row:

```php
$qb->from('post')
   ->set([
       'title'   => 'Post Title #1',
       'content' => 'Body #1',
       'author'  => 5,
       'status'  => true,
   ])
   ->set([
       'title'   => 'Post Title #2',
       'content' => 'Body #2',
       'status'  => false,
   ]);

echo $qb->generateBatchInsertQuery();
// INSERT INTO `post` (`title`, `content`, `author`, `status`) VALUES
//   (:title,   :content,   5,    :status),
//   (:title_1, :content_1, NULL, :status_1);
```

Missing columns (here `author` in row 2) are compiled to the literal
`NULL`. Bound parameters are auto-suffixed to avoid collisions —
`:title`, `:title_1`, `:title_2`, …

## INSERT errors

| Condition                       | Exception                  |
|---------------------------------|----------------------------|
| `set()` never called            | `QueryBuilderException`: *The data set for the insert could not be found.* |
| `from()` / `table()` never called | `QueryBuilderException`: *Table name not found when query.* |

```php
$qb->from('users');
$qb->generateInsertQuery();
// → QueryBuilderException: The data set for the insert could not be found.
```

## UPDATE (single row)

Combine `set()` with `where()`:

```php
$qb->from('post')
   ->where('status', '=', true)
   ->limit(5)
   ->set([
       'title'  => 'New Title',
       'status' => false,
   ]);

echo $qb->generateUpdateQuery();
// UPDATE `post`
//    SET `title` = :title, `status` = :status_1
//  WHERE `status` = :status
//  LIMIT 5
```

A WHERE-less UPDATE compiles to `WHERE 1` — intentional, callers gate as
needed:

```php
$qb->from('post')->set(['title' => 'updated']);
echo $qb->generateUpdateQuery();
// UPDATE `post` SET `title` = :title WHERE 1
```

## UPDATE (batch — CASE / WHEN)

When you need to update many rows with **per-row values**,
`generateUpdateBatchQuery($referenceColumn)` builds a CASE/WHEN
expression keyed by the reference column:

```php
$qb->from('post')
   ->where('status', '=', true)
   ->set(['id' => 5,  'title' => 'New Title #5',  'content' => 'New Content #5'])
   ->set(['id' => 10, 'title' => 'New Title #10']);

echo $qb->generateUpdateBatchQuery('id');
// UPDATE `post`
//    SET `title`   = CASE WHEN `id` = 5  THEN :title    WHEN `id` = 10 THEN :title_1 ELSE `title`   END,
//        `content` = CASE WHEN `id` = 5  THEN :content                              ELSE `content` END
//  WHERE `status` = :status AND `id` IN (5, 10)
```

Notes on the shape:

- Every row in `set()` **must** contain the reference column — otherwise
  the call raises *The reference column does not exist in one or more of
  the set arrays*.
- Columns missing from a row keep their existing value via the
  `ELSE column END` tail.
- The batch update appends a `WHERE … IN (...)` filter for the reference
  column on top of any WHERE you supplied.

## UPDATE errors

| Condition                       | Exception                  |
|---------------------------------|----------------------------|
| `set()` never called            | `QueryBuilderException`: *The data set for the update could not be found.* |
| Reference column missing in any row | `QueryBuilderException`: *The reference column does not exist in one or more of the set arrays.* |

## DELETE

```php
$qb->from('post')
   ->where('authorId', '=', 5)
   ->limit(100);

echo $qb->generateDeleteQuery();
// DELETE FROM `post` WHERE `authorId` = 5 LIMIT 100
```

Multi-condition DELETE:

```php
$qb->from('post')
   ->where('status', 1)
   ->where('author_id', 5);
// DELETE FROM `post` WHERE `status` = 1 AND `author_id` = 5
```

WHERE-less DELETE compiles to `WHERE 1` — same convention as UPDATE:

```php
$qb->from('post');
echo $qb->generateDeleteQuery();
// DELETE FROM `post` WHERE 1
```

> ⚠️ A `WHERE 1` DELETE will wipe the table. The builder does not gate
> for you; callers are responsible for ensuring a WHERE clause is in place
> for non-truncating deletes.

## DELETE errors

| Condition                       | Exception                  |
|---------------------------------|----------------------------|
| `from()` / `table()` never called | `QueryBuilderException`: *Table name not found when query.* |

## The `__toString()` heuristic

For convenience, `(string) $qb` dispatches based on the structure:

- No SET data → SELECT.
- SET data, no WHERE / HAVING → INSERT (or batch INSERT when any row has
  more than one column).
- SET data, WHERE / HAVING present → UPDATE.

```php
echo (string) $qb;  // automatic dispatch
```

It's handy for quick prototyping but explicit `generate*Query()` calls
are more readable in real code — and they raise more descriptive errors
when the structure is incomplete.

## Putting it together

The full lifecycle of a CRUD endpoint:

```php
// Create
$qb->from('post')->set([
    'title'   => 'Hello',
    'content' => 'World',
    'user_id' => 5,
]);
$pdo->prepare($qb->generateInsertQuery())->execute($qb->getParameter()->all());

// Read
$qb->resetStructure()
   ->from('post')
   ->where('user_id', 5)
   ->orderBy('id', 'DESC')
   ->limit(20);
$rows = $pdo->prepare($qb->generateSelectQuery());
$rows->execute($qb->getParameter()->all());

// Update
$qb->resetStructure()
   ->from('post')
   ->where('id', 42)
   ->set(['title' => 'Renamed']);
$pdo->prepare($qb->generateUpdateQuery())->execute($qb->getParameter()->all());

// Delete
$qb->resetStructure()
   ->from('post')
   ->where('id', 42);
$pdo->prepare($qb->generateDeleteQuery())->execute($qb->getParameter()->all());
```

**Next:** [Sub-queries →](subqueries.md)
