# Drivers

A driver is responsible for **identifier escaping** — quoting table and
column names, alias references, and dotted paths — and reporting its
canonical name. Nothing else; the dialect-specific clause grammar lives
in the compilers.

## The four built-ins

| Driver string                                | Class                                | Escape char | Notes |
|----------------------------------------------|--------------------------------------|-------------|-------|
| `'mysql'`                                    | `Drivers\MySqlDriver`                | `` ` ``     |       |
| `'pgsql'` / `'postgres'` / `'postgresql'`    | `Drivers\PostgreSqlDriver`           | `"`         |       |
| `'sqlite'`                                   | `Drivers\SqliteDriver`               | `` ` ``     | Same as MySQL — SQLite accepts both. |
| `null` (or anything unknown)                 | `Drivers\GenericDriver`              | _(none)_    | No identifier quoting — pass-through. |

```php
use InitORM\QueryBuilder\QueryBuilder;

new QueryBuilder('mysql');     // → MySqlDriver
new QueryBuilder('pgsql');     // → PostgreSqlDriver
new QueryBuilder('sqlite');    // → SqliteDriver
new QueryBuilder();            // → GenericDriver
new QueryBuilder('unknown');   // → GenericDriver
```

## The contract

```php
interface DriverInterface
{
    public function escapeIdentifier(string $identifier): string;
    public function getName(): ?string;
}
```

- **`escapeIdentifier()`** is pure — the input is returned (possibly
  modified) but never mutated.
- **`getName()`** returns the canonical lowercase name, or `null` for
  drivers that don't apply a dialect.

## What the escape regex does

The escape implementation (`AbstractDriver::escapeIdentifier()`) uses a
single regex with three pieces of behavior:

1. **Identifier-shaped tokens** (`[a-zA-Z_][a-zA-Z0-9_]*`) get wrapped
   with the escape char.
2. **Bind-parameter prefixes** (`:foo`) are skipped — `:foo` stays
   `:foo`, never `` :`foo` ``.
3. **SQL keywords** AND, OR, AS, ON (both cases) are skipped.
4. **Pre-existing escape characters** inside the identifier are doubled
   (the standard SQL "escape the escape" rule).

Examples (using `MySqlDriver`, with `` ` `` as the escape char):

```php
$d = new MySqlDriver();

$d->escapeIdentifier('id');             // `id`
$d->escapeIdentifier('users.id');       // `users`.`id`
$d->escapeIdentifier('users AS u');     // `users` AS `u`
$d->escapeIdentifier('a.id AND b.id');  // `a`.`id` AND `b`.`id`
$d->escapeIdentifier(':bind_value');    // :bind_value     (untouched)
$d->escapeIdentifier('weird`name');     // `weird``name`   (escape doubled)
```

Numeric literals (digit-leading tokens) are NOT matched by the regex —
they pass through unquoted. That's intentional — it means string
fragments like `x = 1 OR y = 2` only quote the identifiers:

```php
$d->escapeIdentifier('x=1 OR y=2');     // `x`=1 OR `y`=2
```

## When the driver is invoked

Every public clause builder that takes an identifier-shaped argument
runs it through `escapeIdentifier()` before storing it in the structure:

```php
$qb = new QueryBuilder('mysql');
$qb->from('users AS u')->where('u.country', 'TR');
$qb->exportQB()['table'];
// [ '`users` AS `u`' ]
```

By the time the structure is compiled, every identifier is already
quoted. The compilers themselves do no quoting.

For projections that build a function call (`COUNT(...)`, `MAX(...)`,
…), only the column argument is escaped — the function keyword stays
unquoted:

```php
$qb->selectMax('age');
$qb->exportQB()['select'];
// [ 'MAX(`age`)' ]
```

## Adding a custom driver

Extend `AbstractDriver` and override the two class constants:

```php
namespace App\Db;

use InitORM\QueryBuilder\Drivers\AbstractDriver;

final class OracleDriver extends AbstractDriver
{
    protected const NAME = 'oracle';
    protected const ESCAPE_CHAR = '"';
}
```

Use it by constructing a builder with your driver directly — the
constructor's match expression only knows about the four built-ins:

```php
$qb = new QueryBuilder();          // GenericDriver
// Swap the driver via reflection / a subclass, or compose the builder yourself.
```

Or extend `QueryBuilder` if you want first-class support for your driver
string:

```php
namespace App\Db;

use InitORM\QueryBuilder\QueryBuilder as BaseBuilder;

final class QueryBuilder extends BaseBuilder
{
    public function __construct(?string $driver = null)
    {
        parent::__construct($driver);
        if ($driver === 'oracle') {
            $this->driver = new OracleDriver();
        }
    }
}
```

## A custom driver with non-trivial escaping

Override `escapeIdentifier()` directly if your dialect needs more than
a single escape character. Below: a hypothetical driver that
upper-cases identifiers and uses square brackets (SQL Server style):

```php
final class SqlServerDriver extends AbstractDriver
{
    protected const NAME = 'sqlsrv';
    protected const ESCAPE_CHAR = ''; // disable the default

    public function escapeIdentifier(string $identifier): string
    {
        return preg_replace(
            '/\b(?<!:)(?!(AND|and|OR|or|AS|as|ON|on)\b)([a-zA-Z_][a-zA-Z0-9_]*)\b/',
            '[$0]',
            $identifier,
        );
    }
}
```

Now `escapeIdentifier('users.id')` returns `[users].[id]` and the rest
of the clause builders fall in line automatically.

## Comparing the built-in drivers

The same query, four ways:

```php
$build = function (?string $dialect) {
    $qb = new QueryBuilder($dialect);
    return $qb->select('u.id', 'u.name')
              ->from('users AS u')
              ->where('u.country', 'TR')
              ->generateSelectQuery();
};

$build(null);
// SELECT u.id, u.name FROM users AS u WHERE u.country = :u_country

$build('mysql');
// SELECT `u`.`id`, `u`.`name` FROM `users` AS `u` WHERE `u`.`country` = :u_country

$build('pgsql');
// SELECT "u"."id", "u"."name" FROM "users" AS "u" WHERE "u"."country" = :u_country

$build('sqlite');
// SELECT `u`.`id`, `u`.`name` FROM `users` AS `u` WHERE `u`.`country` = :u_country
```

## Reading the active driver

`QueryBuilder::getDriver()` returns the live `DriverInterface` instance —
handy when you want to escape an identifier yourself in a raw fragment:

```php
$col = $qb->getDriver()->escapeIdentifier('users.id');
$qb->where($qb->raw($col . ' = ' . $qb->raw('NOW()')));
```

`getDriver()->getName()` exposes the driver's name (or `null` for the
generic driver) and is what `newBuilder()` uses to propagate the
dialect to a sibling builder.

**Next:** [Recipes →](recipes.md)
