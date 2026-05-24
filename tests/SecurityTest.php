<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Drivers\MySqlDriver;
use InitORM\QueryBuilder\Drivers\PostgreSqlDriver;
use InitORM\QueryBuilder\Exceptions\QueryBuilderInvalidArgumentException;
use InitORM\QueryBuilder\Helper\SqlValueDetector;
use InitORM\QueryBuilder\QueryBuilder;

/**
 * Security regression tests covering both the defenses added in v2.0.0 and
 * the documented residual risks. See docs/en/security.md for the full
 * threat-model write-up.
 *
 * Defenses:
 *   - V3 — identifier escape rejects ";" and "--"
 *   - V4 — LIKE wildcards (%, _) and the escape char (\) in supplied values
 *          are auto-escaped before being concatenated into the LIKE pattern
 *   - V6 — bind-placeholder regex tightened to ^:\w+$
 *
 * Documented residual risks (NOT fixed in code — application-level concern):
 *   - V1 — function-shaped strings ("NOW()", "CURRENT_USER()") in a value
 *          slot are inlined as SQL, bypassing the parameter bag
 *   - V2 — dotted column references ("users.password") in a value slot are
 *          inlined as a column reference
 *   - V5 — orderBy() does not whitelist column names
 *
 * The "documented residual risk" tests pin the current behavior so future
 * refactors do not silently broaden the attack surface.
 */
class SecurityTest extends AbstractQueryBuilderUnit
{
    // -----------------------------------------------------------------------
    // V3 — escapeIdentifier rejects query-breakout sequences
    // -----------------------------------------------------------------------

    public function testEscapeIdentifierRejectsSemicolon(): void
    {
        $this->expectException(QueryBuilderInvalidArgumentException::class);
        $this->expectExceptionMessage('forbidden SQL sequence (;)');
        (new MySqlDriver())->escapeIdentifier('users; DROP TABLE x');
    }

    public function testEscapeIdentifierRejectsDoubleHyphenCommentLeader(): void
    {
        $this->expectException(QueryBuilderInvalidArgumentException::class);
        $this->expectExceptionMessage('forbidden SQL sequence (--)');
        (new MySqlDriver())->escapeIdentifier('users -- comment');
    }

    public function testEscapeIdentifierRejectsForbiddenSequenceEvenOnGenericDriver(): void
    {
        // GenericDriver is the no-op driver. The validation runs *before*
        // the no-op return, so even unquoted drivers gain defense-in-depth.
        $qb = new QueryBuilder(); // GenericDriver

        $this->expectException(QueryBuilderInvalidArgumentException::class);
        $qb->getDriver()->escapeIdentifier('users; DROP TABLE x');
    }

    public function testFromWithSemicolonInjectionRaises(): void
    {
        $this->expectException(QueryBuilderInvalidArgumentException::class);
        $this->db->from('users; DROP TABLE x; --');
    }

    public function testWhereWithDoubleHyphenInjectionRaises(): void
    {
        $this->expectException(QueryBuilderInvalidArgumentException::class);
        $this->db->from('users')->where('name -- ', 'admin');
    }

    public function testPostgreSqlDriverAlsoRejectsForbiddenSequences(): void
    {
        // PostgreSQL allows multi-statement queries by default, which makes
        // the validation particularly important on that driver.
        $this->expectException(QueryBuilderInvalidArgumentException::class);
        (new PostgreSqlDriver())->escapeIdentifier('users; DROP');
    }

    public function testLegitimateIdentifiersWithOperatorsStillWork(): void
    {
        $driver = new MySqlDriver();
        // JOIN-style ON expressions go through escapeIdentifier; operators
        // like = and > must continue to pass through.
        $this->assertSame('`a` = `b`', $driver->escapeIdentifier('a = b'));
        $this->assertSame('`a`.`b` AND `c`.`d`', $driver->escapeIdentifier('a.b AND c.d'));
    }

    // -----------------------------------------------------------------------
    // V4 — LIKE wildcard auto-escape
    // -----------------------------------------------------------------------

    public function testLikeEscapesPercentSignInUserValue(): void
    {
        $this->db->from('user')->like('name', '50%');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        // The user's literal "%" is escaped — the surrounding wildcards
        // ("both" type) are still added by the builder.
        $this->assertSame('%50\\%%', $params[':name']);
    }

    public function testLikeEscapesUnderscoreInUserValue(): void
    {
        $this->db->from('user')->like('name', 'a_b');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame('%a\\_b%', $params[':name']);
    }

    public function testLikeEscapesBackslashFirstThenWildcards(): void
    {
        $this->db->from('user')->like('name', 'a\\b%c');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        // The \ is doubled, then the % is escaped.
        $this->assertSame('%a\\\\b\\%c%', $params[':name']);
    }

    public function testStartLikeEscapesWildcardsInValue(): void
    {
        $this->db->from('user')->startLike('name', 'a%b');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame('a\\%b%', $params[':name']);
    }

    public function testEndLikeEscapesWildcardsInValue(): void
    {
        $this->db->from('user')->endLike('name', 'a%b');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame('%a\\%b', $params[':name']);
    }

    public function testLikeWithRawQueryValueBypassesEscapeAsDocumented(): void
    {
        // Opt-out: when the caller deliberately passes RawQuery, the value
        // is inlined verbatim and the wildcards survive untouched.
        $this->db->from('user')->like('name', $this->db->raw("'custom%pattern'"));
        $sql = $this->db->generateSelectQuery();

        $this->assertEquals(
            "SELECT * FROM user WHERE name LIKE 'custom%pattern'",
            $sql,
        );
    }

    public function testLikeWithExplicitNamedPlaceholderBypassesEscape(): void
    {
        // ":foo" placeholder is recognized by isSqlParameter() and emitted
        // verbatim. Useful when the caller has pre-bound the parameter and
        // wants full control over the pattern.
        $this->db->from('user')->like('name', $this->db->raw(':needle'));
        $sql = $this->db->generateSelectQuery();

        $this->assertEquals(
            'SELECT * FROM user WHERE name LIKE :needle',
            $sql,
        );
    }

    public function testWildcardAttackOnlyMatchesEscapedRowsAfterFix(): void
    {
        // Pre-fix, like('name', '%') would compile to LIKE '%%%' which is
        // semantically LIKE '%' (matches every row). Post-fix the % is
        // escaped so the pattern only matches rows containing the literal
        // "%" character.
        $this->db->from('user')->like('name', '%');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame('%\\%%', $params[':name']);
    }

    // -----------------------------------------------------------------------
    // V6 — placeholder regex tightened
    // -----------------------------------------------------------------------

    public function testIsSqlParameterAcceptsCanonicalPlaceholderShapes(): void
    {
        $this->assertTrue(SqlValueDetector::isSqlParameter('?'));
        $this->assertTrue(SqlValueDetector::isSqlParameter(':foo'));
        $this->assertTrue(SqlValueDetector::isSqlParameter(':foo_1'));
        $this->assertTrue(SqlValueDetector::isSqlParameter(':a_b_c'));
    }

    public function testIsSqlParameterRejectsValuesContainingParens(): void
    {
        // Pre-V6 the character class [(\w)]+ permitted "(" and ")" inside
        // placeholder names — never a legal PDO bind name. Now rejected.
        $this->assertFalse(SqlValueDetector::isSqlParameter(':foo()'));
        $this->assertFalse(SqlValueDetector::isSqlParameter(':((('));
        $this->assertFalse(SqlValueDetector::isSqlParameter(':foo)bar'));
    }

    public function testIsSqlParameterRejectsNonStringAndShellLikeValues(): void
    {
        $this->assertFalse(SqlValueDetector::isSqlParameter('foo'));        // no leading :
        $this->assertFalse(SqlValueDetector::isSqlParameter(':'));          // bare colon
        $this->assertFalse(SqlValueDetector::isSqlParameter(':foo bar'));    // space
        $this->assertFalse(SqlValueDetector::isSqlParameter(5));             // not a string
        $this->assertFalse(SqlValueDetector::isSqlParameter(null));          // not a string
    }

    // -----------------------------------------------------------------------
    // V1 / V2 — DOCUMENTED RESIDUAL RISKS
    //
    // The auto-detection in SqlValueDetector::isSqlParameterOrFunction() is
    // intentionally lenient so that callers can write
    //     $qb->set('updated_at', 'NOW()')
    // and have it inlined without ceremony. The trade-off is that the same
    // lenience applies when a value happens to look like a function call or
    // a dotted column reference — including when that value originates from
    // user input. Application code MUST NOT forward unsanitized user input
    // into the value slot of where()/set() etc. The tests below pin the
    // current behavior so refactors do not silently regress.
    //
    // See docs/en/security.md §V1, §V2 for the corresponding warnings.
    // -----------------------------------------------------------------------

    public function testFunctionShapedValueIsInlinedAsDocumentedRisk(): void
    {
        // ⚠️ This documents a known residual risk, not desired behavior for
        // user-controlled inputs. Callers MUST sanitize / validate the
        // value before passing it in.
        $this->db->from('user')->where('id', 'CURRENT_USER()');
        $sql = $this->db->generateSelectQuery();

        // The value is inlined as a SQL function call rather than being
        // parameterized.
        $this->assertStringContainsString('id = CURRENT_USER()', $sql);
    }

    public function testFunctionWithArgumentsIsParameterizedNotInlined(): void
    {
        // The function-detection regex requires *empty* parentheses; calls
        // with arguments (e.g. SLEEP(10)) do NOT match and are routed to
        // the parameter bag.
        $this->db->from('user')->where('id', 'SLEEP(10)');
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame('SLEEP(10)', $params[':id']);
    }

    public function testDottedColumnReferenceIsInlinedAsDocumentedRisk(): void
    {
        // ⚠️ Same caveat — "table.column" shape in a value slot is treated
        // as a SQL column reference.
        $this->db->from('user')->where('id', 'users.password');
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString('id = users.password', $sql);
    }

    public function testNonFunctionStringValueIsAlwaysParameterized(): void
    {
        // Sanity: ordinary user input goes through the parameter bag.
        $this->db->from('user')->where('id', "Robert'); DROP TABLE Students; --");
        $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        $this->assertSame("Robert'); DROP TABLE Students; --", $params[':id']);
    }

    // -----------------------------------------------------------------------
    // V5 — ORDER BY column whitelist is an application concern
    // -----------------------------------------------------------------------

    public function testOrderByOnlyEscapesIdentifierDoesNotWhitelist(): void
    {
        // The builder escapes the column identifier but does not constrain
        // it to a predefined whitelist. Callers MUST whitelist before
        // passing user-supplied sort columns.
        $this->db->from('user')->orderBy('password', 'ASC');
        $sql = $this->db->generateSelectQuery();

        $this->assertStringContainsString('ORDER BY password ASC', $sql);
    }

    public function testOrderByDirectionIsValidatedAgainstAscDesc(): void
    {
        $this->expectException(QueryBuilderInvalidArgumentException::class);
        $this->db->from('user')->orderBy('id', 'INJECTED;--');
    }

    // -----------------------------------------------------------------------
    // Integration — end-to-end: a hostile input scenario that lands safely
    // -----------------------------------------------------------------------

    public function testEndToEndPdoSafeWithHostileStringInput(): void
    {
        // What the application MUST do: pass user input as a *value*, never
        // as an identifier. Below, "name" is a hard-coded column, and the
        // attacker-controlled string lands in the parameter bag.
        $attackerInput = "'; DROP TABLE users; --";

        $this->db->from('user')->where('name', $attackerInput);
        $sql = $this->db->generateSelectQuery();
        $params = $this->db->getParameter()->all();

        // SQL is parameterized; the attacker string is in the bag, never in
        // the compiled SQL.
        $this->assertEquals('SELECT * FROM user WHERE name = :name', $sql);
        $this->assertSame($attackerInput, $params[':name']);
        $this->assertStringNotContainsString('DROP', $sql);
    }
}
