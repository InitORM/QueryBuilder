# Parameters

The parameter bag is what makes the builder safe to use with PDO without
ever concatenating user input. This page covers the API in detail —
collision auto-suffixing, the NULL short-circuit, `RawQuery` key hashing,
and the value-inlining decision tree.

## The contract

`ParameterInterface` exposes six methods:

| Method                                    | Returns           | Purpose                          |
|-------------------------------------------|-------------------|----------------------------------|
| `set(string $key, mixed $value): self`    | `$this`           | overwrite by key                 |
| `add(string\|RawQuery $key, mixed $value): string` | placeholder name | append, auto-suffix on collision |
| `get(?string $key = null, mixed $default = null): mixed` | value or full map | read                       |
| `all(): array`                            | `array<string, mixed>` | PDO-ready map                |
| `merge(array\|ParameterInterface ...$arrays): self` | `$this`     | bulk merge                       |
| `reset(): self`                           | `$this`           | empty the bag                    |

The default implementation, `Parameters`, ships with the package.

## Accessing the bag

Every builder has a parameter bag accessible via `getParameter()`:

```php
$qb = new QueryBuilder('mysql');
$qb->from('users')->where('country', 'TR');

$bag = $qb->getParameter();
$bag->all();    // [':country' => 'TR']
```

## `set()` vs `add()`

The two write methods have **different semantics** and you'll want to
know when to reach for each:

### `set()` — overwrites by key

```php
$bag->set('id', 1);
$bag->set('id', 2);
$bag->all();
// [':id' => 2]   ← overwritten
```

Use `set()` when you have control over the key and want it to remain
stable across rebinds. `setParameter()` on the builder delegates here.

### `add()` — collision auto-suffix

```php
$bag->add('id', 1);  // returns ':id'
$bag->add('id', 2);  // returns ':id_1'
$bag->add('id', 3);  // returns ':id_2'
$bag->all();
// [':id' => 1, ':id_1' => 2, ':id_2' => 3]
```

This is what the clause builders use internally — every value bound by
`where('id', ...)`, `set('id', ...)` etc. goes through `add()` so a
chain that mentions the same column multiple times still produces a
valid SQL statement.

## Key sanitization

Both `set()` and `add()` strip non-alphanumeric characters from the key
before prefixing with `:`:

```php
$bag->add('user.id', 1);     // returns ':userid'  (dot removed)
$bag->add('user-id', 2);     // returns ':userid_1' (dash removed)
```

Why? Because PDO bind names only accept `[A-Za-z0-9_]`. The sanitization
is silent — be aware of it if you reach for "exotic" key shapes.

## The NULL short-circuit

`add()` does **not** register a binding when the value is `null`:

```php
$placeholder = $bag->add('deleted_at', null);
// $placeholder === 'NULL'
$bag->all();
// []   ← nothing was added
```

This lets the compiler inline the literal `NULL` into the SQL — a
parameterized `:deleted_at = ?` bound to PHP `null` would compile to
`= NULL` which is **not** the same as `IS NULL`. The short-circuit
sidesteps that footgun by emitting `NULL` directly when the value is
unambiguously null. Use `whereIsNull()` / `whereIsNotNull()` for the
correct SQL form.

## RawQuery keys

When the key passed to `add()` is itself a `RawQuery` (used internally by
batch UPDATE when the column reference is a complex expression), the
implementation hashes it with `md5()` to produce a stable, opaque
placeholder name:

```php
$bag->add(new RawQuery('some expression'), 1);
// returns ':<32 hex chars>'
```

You won't usually trigger this directly — it's mostly an internal
plumbing detail — but it explains the occasional `:<hash>` placeholder
in compiled SQL when reading complex queries.

## Reading values

`get()` is multi-purpose:

```php
$bag->set('id', 99);

$bag->get();        // returns the whole map
$bag->get('id');    // 99
$bag->get(':id');   // 99 — leading colon is optional
$bag->get('missing'); // null
$bag->get('missing', 'fallback'); // 'fallback'
$bag->get('missing', fn () => 'lazy'); // 'lazy' — closure invoked lazily
```

A `Closure` default is invoked **only** when the key is missing. If the
key is present, the closure is never called — handy for expensive
fallbacks.

## Merging bags

`merge()` accepts both plain arrays and other `ParameterInterface`
instances:

```php
$other = (new Parameters())->set('c', 3)->set('d', 4);

$bag = new Parameters();
$bag->merge(['a' => 1, 'b' => 2], $other);
$bag->all();
// [':a' => 1, ':b' => 2, ':c' => 3, ':d' => 4]
```

`merge()` uses `set()` semantics — colliding keys overwrite. If you need
collision-safe merging, iterate the source manually and call `add()` for
each entry.

## Resetting

```php
$bag->reset();   // empty the bag
$bag->all();     // []
```

`QueryBuilder::resetStructure()` does **not** reset the parameter bag —
they are independent. If you reuse a builder for a fresh query and you
want a clean bag too, call both:

```php
$qb->resetStructure();
$qb->getParameter()->reset();
```

## When the builder DOES NOT parameterize

Not every value flows through the bag. The internal helper
`SqlValueDetector::isSqlParameterOrFunction()` returns `true` (and the
value is **inlined** instead of bound) for:

- Integers — `5` becomes `5` in SQL.
- `?` — positional placeholder.
- `:foo` shape — pre-formed named placeholder.
- `table.column` shape — dotted column reference.
- `function()` shape — parameterless SQL function call.
- `RawQuery` — always inlined verbatim.

```php
$qb->where('id', 5);
// WHERE `id` = 5      ← integer inlined

$qb->where('id', '?');
// WHERE `id` = ?      ← positional placeholder inlined

$qb->where('id', $qb->raw('NOW()'));
// WHERE `id` = NOW()  ← RawQuery inlined
```

Everything else — strings, booleans, floats, DateTime objects,
unrecognized scalars — goes through `add()`.

> ⚠️ Floats and `DateTime` are bound, not inlined. Cast them to your
> dialect's expected form beforehand if you need a specific format.

## Plugging the bag into PDO

The map returned by `all()` is already keyed for PDO:

```php
$pdo  = new PDO(/* … */);
$qb->select('*')->from('users')->where('country', 'TR');

$stmt = $pdo->prepare($qb->generateSelectQuery());
$stmt->execute($qb->getParameter()->all());
```

PDO ignores the leading `:` on bind names, so either form (`':country'`
or `'country'`) works at execute time — but the bag always emits the
colon-prefixed form.

## Hoisting a value into the outer bag

Useful for sub-queries (see [subqueries.md](subqueries.md#sub-query-parameters))
or for hand-rolling a `RawQuery`:

```php
$qb->setParameter('admin_role', 'admin');
$qb->where($qb->raw('role = :admin_role'));
// WHERE role = :admin_role
// Bag: [':admin_role' => 'admin']
```

`setParameter()` is a convenience for `getParameter()->set(...)`.

## A worked example

```php
$qb = new QueryBuilder('mysql');
$qb->from('users')
   ->where('country', 'TR')
   ->where('country', 'US')           // collision → :country_1
   ->whereIn('role_id', [1, 2, 3])     // integers inlined
   ->set('updated_at', $qb->raw('NOW()')); // RawQuery inlined

$qb->getParameter()->all();
// [
//     ':country'   => 'TR',
//     ':country_1' => 'US',
// ]
```

Note how only the strings landed in the bag — the integers and the
`NOW()` call were inlined directly into the SQL.

**Next:** [Drivers →](drivers.md)
