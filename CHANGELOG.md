# Changelog

All notable changes to **InitORM QueryBuilder** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] — 2026-05-24

The 2.0 line is a quality push: bug fixes (including one with SQL-injection
implications), an end-to-end refactor that splits the previous god-class into
clause traits + compilers, full PHPDoc + PHPStan level 6 + PSR-12, a 293-test
suite at 96 % line coverage, and CI on PHP 8.1 → 8.4.

### Added
- **`Operator\Operators`** — every operator list (comparison, arithmetic,
  null-check, LIKE family, BETWEEN, IN, FIND\_IN\_SET, sort directions,
  logical) is now defined once as `const`.
- **`Helper\SqlValueDetector`** — `isSqlParameter()` / `isSqlParameterOrFunction()`
  extracted to a stateless helper class.
- **`Helper\BucketCompiler`** — shared AND / OR bucket joiner used by both
  the runtime clause builders and the compilers.
- **`Compiler\*`** — six dedicated compiler classes
  (`SelectCompiler`, `InsertCompiler`, `BatchInsertCompiler`,
  `UpdateCompiler`, `BatchUpdateCompiler`, `DeleteCompiler`) plus
  `AbstractCompiler` with shared helpers and `CompilerInterface` as marker.
- **`Clause\*`** — six clause traits split out of the previous god-class:
  `StructureTrait`, `FromClauseTrait`, `SelectClauseTrait`,
  `JoinClauseTrait`, `WhereClauseTrait`, `SetClauseTrait`.
- **`Drivers\GenericDriver`** — the previous default `BaseDriver` is now an
  explicit driver class; `AbstractDriver` is the new shared base.
- **`Drivers\AbstractDriver`** — replaces the old `BaseDriver`. Concrete
  drivers declare `NAME` and `ESCAPE_CHAR` as class constants.
- **`QueryBuilder::__clone()`** — deep-clones the parameter bag so that
  cloning the builder does not bleed parameter mutations between instances.
- **`QueryBuilderInterface::getDriver()`** — exposes the active driver.
- PHP 8.4 support; CI matrix now covers 8.1 / 8.2 / 8.3 / 8.4.
- PHPStan level 6 static analysis (`composer stan`).
- PHP\_CodeSniffer with a PSR-12 ruleset (`composer cs` / `cs-fix` / `cs-ci`).
- GitHub Actions workflows: PHPUnit, PHPCS, PHPStan, composer-validate.
- Dependabot weekly updates for Composer and GitHub Actions.
- Coverage reporting via pcov in `phpunit.xml`.
- Comprehensive `docs/` developer documentation (see [docs/en/index.md](docs/en/index.md)).

### Changed
- **Breaking — driver class renames.**
  - `Drivers\BaseDriver` → `Drivers\AbstractDriver` (now `abstract`).
  - `Drivers\MySQL` → `Drivers\MySqlDriver`.
  - `Drivers\PgSQL` → `Drivers\PostgreSqlDriver`.
  - `Drivers\SQLite` → `Drivers\SqliteDriver`.
- **Breaking — `DriverInterface`.**
  - `escapeIdentify(string &$s): string` → `escapeIdentifier(string $s): string`
    (pure — no by-reference mutation).
  - `getDriver()` → `getName()`.
- **Breaking — `QueryBuilderInterface::naturalJoin()`** no longer accepts
  `$onStmt`; NATURAL JOIN does not carry an ON clause.
- **Breaking — `QueryBuilderInterface::where/having/on/andWhere/orWhere`**
  declare `$operator` as `mixed` (matching the implementation), so the
  value-shortcut `where('id', 5)` is preserved without LSP-violating type
  contracts.
- **Breaking — `QueryBuilderInterface::group()`** now declares the
  `string $logical = 'AND'` parameter that the implementation always accepted.
- **Breaking — return types `self` → `static`** across the interface, so
  subclasses correctly inherit their own static return type from chained calls.
- **Breaking — minimum PHP version** raised from 8.0 to 8.1.
- Internal `__generate*` private helpers renamed to non-magic names
  (`compileWhere`, `compileHaving`, etc.) inside the compilers.
- Every file's license header was reduced from a 10-line block to a single
  `@package` / `@license` pair — the canonical legal text lives in `LICENSE`.

### Fixed
- **B1 / B7 — `andWhereNotIn`** previously called `where(…, 'IN', …)`,
  emitting `IN` instead of `NOT IN`.
- **B2 — `orLike` / `andLike` were swapped**: the AND-named method used
  `'OR'`, the OR-named method used the default `'AND'`.
- **B3 — `orBetween`** corrupted its arguments by passing the bounds and
  `'OR'` into the wrong positional slots, producing an unusable array and
  losing the OR connector.
- **B4 — `RawQuery::set()`** used `instanceof Closure` without
  `use Closure;`, so PHP resolved the comparison against the non-existent
  `InitORM\QueryBuilder\Closure` and the closure branch was dead code.
- **B5 / B6 — `selfJoin` / `naturalJoin` signatures** now mirror the
  implementations (`naturalJoin` no longer demands an unused `$onStmt`).
- **B8 — `preg_match(...) !== FALSE`** was effectively always true and
  could read an unpopulated `$matches`; replaced with `=== 1`.
- **B9 — `whereOrHavingStatementPrepare`** now coerces a non-string
  operator argument safely before `trim()`.
- **B16 — `startLike` / `endLike` wildcard semantics inverted.**
  - `startLike('A')` now produces `LIKE 'A%'` (was `LIKE '%A'`).
  - `endLike('A')` now produces `LIKE '%A'` (was `LIKE 'A%'`).
- **B20 / B21 — UPDATE exception message** said "insert" instead of "update".
- **B23 — Abstract test bases** `AbstractQueryBuilderUnit`,
  `AbstractQueryBuilderDriverUnit` are now actually declared `abstract`.
- **B27 — `whereOrHavingPrepare`** used a loose `in_array()` which made
  boolean operators (`where('active', true)`) compare equal to non-empty
  operator strings; the value-shortcut was therefore skipped and the WHERE
  collapsed to `WHERE active`. Strict mode is now enforced.
- **B28 (security)** — the `FIND_IN_SET` / `NOT FIND_IN_SET` case had the
  parameterization branch inverted: raw string values were inlined verbatim
  into SQL while pre-bound `RawQuery` placeholders were re-parameterized.
  This was a latent SQL-injection vector for any caller passing
  user-supplied strings to `findInSet()` / `notFindInSet()`. Fixed.
- **V3 (security, hardening)** — `AbstractDriver::escapeIdentifier()` now
  rejects identifiers containing `;` or `--`. These sequences never appear
  in legitimate identifiers and are the canonical pivot for SQL injection
  when a caller forwards user input as a table or column name. The check
  runs before the dialect's quoting, so even the no-op `GenericDriver`
  gets the defense-in-depth. PostgreSQL — which allows multi-statement
  queries by default — was the highest-risk consumer.
- **V4 (security, hardening)** — `WhereClauseTrait::prepareStatement()`
  now escapes the SQL wildcard characters (`%`, `_`) and the escape
  character itself (`\`) in any user-supplied value flowing through the
  LIKE family. Previously, `like('name', '%')` compiled to
  `LIKE '%%%'` (equivalent to `LIKE '%'`) — which let any user enumerate
  every row by typing `%` into a search box. Opt-out: pass a `RawQuery`
  when raw wildcards are deliberately desired.
- **V6 (security, hardening)** — `SqlValueDetector` placeholder regex
  tightened from `/^:[(\w)]+$/` to `/^:\w+$/`. The previous character
  class spuriously permitted `(` and `)` inside placeholder names — never
  a valid PDO bind name.
- **B26** — `BucketCompiler` previously joined the AND-bucket and the
  OR-bucket of every WHERE / HAVING / ON clause with `" AND "`, which
  silently collapsed every top-level `orX()` chain into an `AND`. A call
  like `where('country', 'TR')->orWhere('country', 'US')` compiled to
  `country = :a AND country = :b` — clearly not OR semantics. The
  connector is now `" OR "`; SQL precedence (`AND > OR`) gives the
  expected `(a AND b) OR c` parse for mixed chains. The
  pre-existing `testWhereGroupMultipleStatement` test was updated to
  pin the corrected behavior.
- The PHP 8.4 implicit-nullable-parameter deprecation on `join($table, $onStmt = null, …)`
  is no longer emitted — the parameter is now explicitly typed
  `RawQuery|Closure|string|null`.

[Unreleased]: https://github.com/InitORM/QueryBuilder/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/InitORM/QueryBuilder/releases/tag/v2.0.0
