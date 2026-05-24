# InitORM QueryBuilder

[![Packagist Version](https://img.shields.io/packagist/v/initorm/query-builder.svg)](https://packagist.org/packages/initorm/query-builder)
[![Total Downloads](https://img.shields.io/packagist/dt/initorm/query-builder.svg)](https://packagist.org/packages/initorm/query-builder)
[![PHP Version](https://img.shields.io/packagist/php-v/initorm/query-builder.svg)](https://packagist.org/packages/initorm/query-builder)
[![License](https://img.shields.io/packagist/l/initorm/query-builder.svg)](LICENSE)
[![PHPUnit](https://github.com/InitORM/QueryBuilder/actions/workflows/phpunit.yml/badge.svg)](https://github.com/InitORM/QueryBuilder/actions/workflows/phpunit.yml)
[![PHPStan](https://github.com/InitORM/QueryBuilder/actions/workflows/phpstan.yml/badge.svg)](https://github.com/InitORM/QueryBuilder/actions/workflows/phpstan.yml)
[![PHP_CodeSniffer](https://github.com/InitORM/QueryBuilder/actions/workflows/phpcs.yml/badge.svg)](https://github.com/InitORM/QueryBuilder/actions/workflows/phpcs.yml)

A lightweight, dialect-aware **SQL query builder** for PHP. It turns fluent
calls into a SQL string plus a separate parameter bag suitable for direct
execution with **PDO** — without ever concatenating user values into SQL.

InitORM QueryBuilder is the lowest layer of the [InitORM](https://github.com/InitORM)
package family; it has **no runtime dependencies** beyond the `pdo` extension
and is designed to be used either standalone or as part of the
`initorm/database` and `initorm/orm` stack.

## Why this library

- **Safe by default** — every value goes through a collision-safe parameter
  bag. Raw fragments are opt-in via `RawQuery`.
- **Dialect aware** — identifier escaping is delegated to pluggable drivers
  for MySQL/MariaDB, PostgreSQL, SQLite, plus a no-op generic driver.
- **Tiny and predictable** — single namespace, no service container, no
  reflection, no annotations; the whole thing is around 1 600 lines of code.
- **Battle-tested clause DSL** — comparison operators, BETWEEN, IN, LIKE
  family (`like` / `startLike` / `endLike`), NULL checks, REGEXP, SOUNDEX,
  FIND\_IN\_SET, sub-queries, parenthesized groups, closure-based JOIN ON
  expressions.

## Requirements

- **PHP ≥ 8.1**
- **`ext-pdo`** (only needed by the consumer at execution time; the builder
  itself does not require an open connection)

## Installation

```bash
composer require initorm/query-builder
```

## Quick start

```php
use InitORM\QueryBuilder\QueryBuilder;

$qb = new QueryBuilder('mysql');

$sql = $qb
    ->select('u.id', 'u.name')
    ->from('users AS u')
    ->where('u.status', 1)
    ->andWhere('u.country', 'TR')
    ->orderBy('u.id', 'DESC')
    ->limit(20)
    ->generateSelectQuery();

// $sql ─────────────────────────────────────────────────────────────────
// SELECT `u`.`id`, `u`.`name`
//   FROM `users` AS `u`
//  WHERE `u`.`status` = 1 AND `u`.`country` = :country
//  ORDER BY `u`.`id` DESC
//  LIMIT 20

$pdo = new PDO('mysql:host=localhost;dbname=app', 'app', 'secret');
$stmt = $pdo->prepare($sql);
$stmt->execute($qb->getParameter()->all());
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### INSERT

```php
$qb->from('users')->set([
    'name'    => 'Muhammet',
    'email'   => 'info@muhammetsafak.com.tr',
    'created' => $qb->raw('NOW()'),
]);

echo $qb->generateInsertQuery();
// INSERT INTO `users` (`name`, `email`, `created`)
//   VALUES (:name, :email, NOW());
```

### Sub-query in WHERE IN

```php
$qb->select('u.name')
   ->from('users AS u')
   ->whereIn('u.id', $qb->subQuery(function (QueryBuilder $sub) {
       $sub->select('id')->from('roles')->where('name', 'admin');
   }));
// SELECT `u`.`name` FROM `users` AS `u`
//  WHERE `u`.`id` IN (SELECT `id` FROM `roles` WHERE `name` = :name)
```

### Closure-based JOIN ON

```php
$qb->select('p.title', 'u.name')
   ->from('posts AS p')
   ->innerJoin('users AS u', function (QueryBuilder $j) {
       $j->on('u.id', 'p.user_id')
         ->where('u.active', 1);
   });
```

### Batch UPDATE (CASE/WHEN)

```php
$qb->from('posts')
   ->set(['id' => 1, 'title' => 'First',  'views' => 100])
   ->set(['id' => 2, 'title' => 'Second', 'views' =>  42]);

echo $qb->generateUpdateBatchQuery('id');
// UPDATE `posts`
//    SET `title` = CASE WHEN `id` = 1 THEN :title WHEN `id` = 2 THEN :title_1 ELSE `title` END,
//        `views` = CASE WHEN `id` = 1 THEN 100   WHEN `id` = 2 THEN 42         ELSE `views` END
//  WHERE `id` IN (1, 2)
```

## Supported drivers

| String                            | Driver class                                 | Escape char |
|-----------------------------------|----------------------------------------------|-------------|
| `'mysql'`                         | `Drivers\MySqlDriver`                        | `` ` ``     |
| `'pgsql'` / `'postgres'` / `'postgresql'` | `Drivers\PostgreSqlDriver`           | `"`         |
| `'sqlite'`                        | `Drivers\SqliteDriver`                       | `` ` ``     |
| `null` (or anything unknown)      | `Drivers\GenericDriver` (no quoting)         | _(none)_    |

A custom dialect can be added by extending `Drivers\AbstractDriver` and
setting the `NAME` and `ESCAPE_CHAR` class constants.

## Documentation

Full developer documentation with runnable examples lives in
[`docs/`](docs/) — see [`docs/en/index.md`](docs/en/index.md) for the table
of contents.

## Security

InitORM QueryBuilder is built around the rule **"user input is a value, never
an identifier or a SQL fragment"**. Defenses shipped in 2.0.0:

- **Identifier hardening** — `escapeIdentifier()` rejects `;` and `--` so
  query-breakout characters in a column or table name cannot survive the
  escape pass (relevant especially on PostgreSQL, where PDO allows
  multi-statement queries by default).
- **LIKE wildcard auto-escape** — `%`, `_`, and `\` inside user-supplied
  LIKE values are escaped by default. Opt out with `$qb->raw(...)` when
  raw wildcards are intentional.
- **Strict placeholder regex** — placeholder names are now tightly bound
  to `^:\w+$`.
- **FIND\_IN\_SET parameter fix (B28)** — a pre-2.0.0 inversion bug
  inlined raw user strings as SQL; fixed.

The full threat model, residual application-level concerns
(`ORDER BY` whitelisting, value-shaped function detection), and a complete
regression suite live in [`docs/en/security.md`](docs/en/security.md) and
[`tests/SecurityTest.php`](tests/SecurityTest.php).

Report vulnerabilities through the
[organization-wide security policy](https://github.com/InitORM/.github/blob/main/SECURITY.md).

## Tests, lint, static analysis

```bash
composer install
composer test     # phpunit (with pcov line-coverage summary)
composer cs       # PHP_CodeSniffer (PSR-12)
composer cs-fix   # phpcbf — auto-fix style violations
composer stan     # PHPStan level 6
composer qa       # cs-ci + stan + test
```

The repository ships with GitHub Actions workflows under
[`.github/workflows/`](.github/workflows) that run the same checks on every
push and pull request, across the PHP 8.1 → 8.4 matrix.

Current numbers: **293 tests / 391 assertions / 96.46 % line coverage**.

## Contributing

The contribution workflow, code style, and pull-request template are shared
across the InitORM organization. See
[InitORM/.github → CONTRIBUTING](https://github.com/InitORM/.github/blob/main/CONTRIBUTING.md)
and the [PR template](https://github.com/InitORM/.github/blob/main/PULL_REQUEST_TEMPLATE.md).
A short summary:

1. Branch from `master`.
2. Stick to **PSR-12**; run `composer qa` before opening a PR.
3. Add tests for new behavior — the test suite is the contract.
4. Reference issues with `Fixes #123` / `Refs #123`.

Security issues should follow the disclosure process in
[InitORM/.github → SECURITY](https://github.com/InitORM/.github/blob/main/SECURITY.md).

## Versioning

This package follows [Semantic Versioning](https://semver.org). The
behavioral and structural changes between 1.x and 2.x are listed in
[CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).

## Credits

Authored and maintained by [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr)
&lt;info@muhammetsafak.com.tr&gt;. Issues and contributions are welcome on
[GitHub](https://github.com/InitORM/QueryBuilder).
