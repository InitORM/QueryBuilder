# Security

This page is the security threat model for InitORM QueryBuilder: what the
library defends against, what it deliberately delegates to the caller, and
how to write application code that does not regress those guarantees.

The matching regression suite lives in
[`tests/SecurityTest.php`](../../tests/SecurityTest.php) — every defense
listed here has a test that pins the behavior.

## Threat model at a glance

| # | Risk                                                              | Defense                                                   | Where    |
|---|-------------------------------------------------------------------|-----------------------------------------------------------|----------|
| V1 | "Function-shaped" string in a value slot bypasses parameterisation | **Documented**, application-level                         | §V1      |
| V2 | "Dotted column reference" in a value slot bypasses parameterisation | **Documented**, application-level                         | §V2      |
| V3 | Query-breakout characters (`;`, `--`) in identifier names         | **Hardened** — `escapeIdentifier()` raises                | §V3      |
| V4 | LIKE wildcard injection (`%`, `_`, `\` in user values)            | **Hardened** — default auto-escape                        | §V4      |
| V5 | ORDER BY column enumeration via unvalidated input                 | **Documented**, application-level                         | §V5      |
| V6 | Placeholder regex permitted `(` and `)` characters                | **Hardened** — regex tightened to `^:\w+$`                | §V6      |

> 🚨 **Golden rule.** User input must always reach the database as a *value*,
> never as an *identifier* (column / table / alias name) and never as a SQL
> fragment. The library is designed around that boundary; once a caller
> crosses it, no defense the builder can mount is reliable.

## §V1 — Function-shaped strings in values

### What happens

`SqlValueDetector::isSqlParameterOrFunction()` treats a string matching
`^[a-zA-Z_]+\(\)$` as a parameterless SQL function call and inlines it
verbatim. This is what lets you write:

```php
$qb->set('updated_at', 'NOW()');
// SET `updated_at` = NOW()
```

If a caller passes user input straight into a value slot and the input
happens to match that shape, the same path triggers:

```php
$qb->from('user')->where('id', $_GET['id']);
// Attacker:  $_GET['id'] = 'CURRENT_USER()'
//   → WHERE `id` = CURRENT_USER()
// Attacker:  $_GET['id'] = 'DATABASE()'
//   → WHERE `id` = DATABASE()
```

Empty-parens form only: `'SLEEP(10)'` does NOT match (the regex rejects
content between the parens) and is parameterised normally.

### Why it's not auto-disabled

Function inlining for trusted programmer strings (`'NOW()'`, `'UUID()'`,
`'CURDATE()'`) is the most common reason developers reach for the
library. Stripping the auto-detection would force every caller through
`RawQuery` and break the README's "Quick start" example.

### What you must do

Treat values flowing into `where()` / `having()` / `on()` / `set()` as
**parameter data**. If you need a function call, supply it via `RawQuery`:

```php
// ❌ NEVER
$qb->where('id', $_GET['id']);

// ✅ if you have a fixed expected shape, cast and validate
$qb->where('id', (int) $_GET['id']);

// ✅ if you want a SQL function, mark it as raw — programmer intent
$qb->set('updated_at', $qb->raw('NOW()'));
```

## §V2 — Dotted column references in values

### What happens

`SqlValueDetector::isSqlParameterOrFunction()` also matches
`^[a-zA-Z_]+\.[a-zA-Z_]+$` and inlines as a column reference — the
shorthand that lets you compare against another column without
ceremony:

```php
$qb->where('post.user_id', 'users.id');
// WHERE `post`.`user_id` = users.id
```

User input shaped like that bypasses parameterisation:

```php
// Attacker: $_GET['id'] = 'users.password'
$qb->where('id', $_GET['id']);
// WHERE `id` = users.password
//   ↑ Boolean comparison against another column rather than a literal.
//   ↑ Wrong rows on purpose-controlled input.
```

### Why it's not auto-disabled

Same trade-off as §V1 — JOIN ON expressions and side-by-side column
comparisons would all require `RawQuery` if this auto-detection were
removed.

### What you must do

Same as §V1 — values originating from the request body / query string /
headers MUST be coerced to an explicit primitive type (or rejected) before
being passed in.

## §V3 — Query-breakout characters in identifiers — *defended*

### What it would have been

The identifier-escape regex in `AbstractDriver::escapeIdentifier()` quotes
identifier-shaped runs but leaves operator and punctuation characters
alone:

```php
// Pre-v2.0.0 behavior (hypothetical, user-supplied table):
$qb->from('users; DROP TABLE x; --');
// FROM `users`; `DROP` `TABLE` `x`; --
//   ↑ The `;` and `--` survive the escape pass. On PostgreSQL, where
//   ↑ PDO multi-statement queries are allowed by default, this is a
//   ↑ direct injection vector.
```

### The defense

`escapeIdentifier()` now rejects any identifier containing `;` or `--`:

```php
$qb->from('users; DROP');
// throws QueryBuilderInvalidArgumentException:
//   Identifier contains a forbidden SQL sequence (;)
```

The check runs **before** the dialect's quoting logic, so it applies to
the no-op `GenericDriver` as well — generic-driver users get the same
defense-in-depth as MySQL / PgSQL / SQLite callers.

Operator characters (`=`, `>`, `<`, `.`) and whitespace continue to pass
through, so legitimate JOIN ON expressions (`'user.id = post.user_id'`)
still work.

### What you must do

The defense is opt-in by virtue of existing — no caller change needed.
Continue to validate user input before passing it as a table or column
name, but the library will now fail loudly rather than emit unsafe SQL
when that validation slips.

## §V4 — LIKE wildcard injection — *defended*

### What it would have been

The LIKE compiler wraps the user-supplied value with `%` characters
according to the `$type` argument. Pre-v2.0.0 the value was used as-is,
so `%` and `_` inside the value retained their SQL wildcard meaning:

```php
// Pre-fix behavior:
$qb->like('name', '%');
// LIKE '%%%'   ≡ LIKE '%'   — matches every row
```

A search box that lets users type their query straight into `like()`
would let any user enumerate the whole table by typing `%`.

### The defense

The LIKE branch in `WhereClauseTrait::prepareStatement()` now escapes
the SQL wildcard characters before concatenating the surrounding `%`:

```php
$qb->like('name', '50%');
// param :name = '%50\%%'
//   ↑ The user's literal "%" is escaped with "\".
$qb->like('name', 'a_b');
// param :name = '%a\_b%'
//   ↑ Underscore is also a single-character wildcard in SQL.
$qb->like('name', '\\');
// param :name = '%\\\\%'
//   ↑ The escape character is doubled.
```

The same applies to `notLike`, `startLike`, `endLike`, and every
`and*` / `or*` variant.

### Opt out

When the caller deliberately wants raw wildcards (e.g. a programmer
building a hand-shaped pattern), pass a `RawQuery`:

```php
$qb->like('name', $qb->raw("'custom%pattern'"));
// LIKE 'custom%pattern'   — no escape applied
```

Passing a placeholder-shaped string (e.g. `':needle'` wrapped in `raw()`)
also bypasses the escape — the value is emitted verbatim.

## §V5 — ORDER BY column whitelist

### What happens

`orderBy()` escapes the column identifier (no SQL injection possible
through the identifier itself, post-V3) but does not constrain it to a
predefined set of columns. A user who supplies the sort column directly
can therefore:

- Sort by *any* column in the table — including columns the application
  did not intend to expose (a hashed password column, an internal status
  flag).
- Use timing differences across sorts to enumerate column types.

### What you must do

Whitelist sort columns in the application layer:

```php
$sortable = ['id', 'created_at', 'title'];
$sortColumn = $_GET['sort'] ?? 'id';
if (!in_array($sortColumn, $sortable, true)) {
    $sortColumn = 'id';
}
$qb->orderBy($sortColumn, 'DESC');
```

The sort *direction* is already validated against `ASC` / `DESC` by the
library — `orderBy('id', $_GET['dir'])` is safe to that extent.

## §V6 — placeholder-shape regex — *defended*

### What it would have been

`SqlValueDetector::isSqlParameter()` and the same check inside
`isSqlParameterOrFunction()` used the regex `/^:[(\w)]+$/`. The
character class `[(\w)]+` permitted `(`, `)`, and word characters —
almost certainly a typo for `^:\w+$`. PDO bind names only accept
`[A-Za-z0-9_]`, so the extra characters were never legal.

### The defense

Both call sites now use `/^:\w+$/`. No legitimate caller is affected;
the change just trims latent room for surprising matches.

## Putting it together — safe patterns

A representative "safe" search endpoint, end to end:

```php
use InitORM\QueryBuilder\QueryBuilder;

function search(PDO $pdo, array $get): array
{
    $qb = new QueryBuilder('mysql');
    $qb->from('product');

    // Values — always parameter-bound. Cast where the shape is known.
    if (!empty($get['name'])) {
        // V4: % / _ in user input is auto-escaped by like().
        $qb->like('name', (string) $get['name']);
    }
    if (!empty($get['category_id'])) {
        $qb->where('category_id', (int) $get['category_id']);
    }

    // Sort — whitelist the column, library validates direction.
    $sortable = ['id', 'name', 'price'];
    $sort = in_array($get['sort'] ?? '', $sortable, true) ? $get['sort'] : 'id';
    $dir  = strcasecmp($get['dir'] ?? '', 'asc') === 0 ? 'ASC' : 'DESC';
    $qb->orderBy($sort, $dir);

    // Pagination — coerce to int, library rectifies negative values.
    $qb->limit((int) ($get['limit'] ?? 20))
       ->offset((int) ($get['offset'] ?? 0));

    $stmt = $pdo->prepare($qb->generateSelectQuery());
    $stmt->execute($qb->getParameter()->all());
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

Conversely, every example below is **unsafe** and the library cannot
defend against it:

```php
// ❌ Identifier from user input — even with §V3 active, this lets the
//    user pick which table to query, which is rarely intentional.
$qb->from($_GET['table']);

// ❌ Value from user input passed straight to a function-shaped check
//    that may match §V1.
$qb->where('id', $_GET['id']);   // attacker: id=CURRENT_USER()

// ❌ ORDER BY without a whitelist — §V5.
$qb->orderBy($_GET['sort']);

// ❌ RawQuery built from user input — escape hatch by design.
$qb->where($qb->raw('id = ' . $_GET['id']));
```

## Reporting a vulnerability

Do **not** open public issues for security problems. Follow the
[organization-wide disclosure process](https://github.com/InitORM/.github/blob/main/SECURITY.md).

## Further reading

- [`tests/SecurityTest.php`](../../tests/SecurityTest.php) — every
  defense and documented residual risk has a regression test.
- [Raw queries](raw-queries.md) — when and how to use `RawQuery`
  responsibly.
- [Parameters](parameters.md) — the value-inlining decision tree.
- [Drivers](drivers.md) — the identifier-escape rules driver by driver.

**Back to the index:** [Table of contents →](index.md)
