<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Drivers\AbstractDriver;
use InitORM\QueryBuilder\Drivers\DriverInterface;
use InitORM\QueryBuilder\Drivers\GenericDriver;
use InitORM\QueryBuilder\Drivers\MySqlDriver;
use InitORM\QueryBuilder\Drivers\PostgreSqlDriver;
use InitORM\QueryBuilder\Drivers\SqliteDriver;
use PHPUnit\Framework\TestCase;

/**
 * Behavior of every shipped driver — name reporting and identifier escaping
 * including dotted paths, reserved keywords (AND/OR/AS/ON), and double-escape
 * of an already-present quote character.
 */
class DriversTest extends TestCase
{
    public function testGenericDriverNameIsNull(): void
    {
        $this->assertNull((new GenericDriver())->getName());
    }

    public function testGenericDriverPassesIdentifiersThroughUnchanged(): void
    {
        $driver = new GenericDriver();
        $this->assertSame('users.id', $driver->escapeIdentifier('users.id'));
        $this->assertSame('users AS u', $driver->escapeIdentifier('users AS u'));
    }

    /**
     * @return array<string, array{0:DriverInterface, 1:string, 2:string}>
     */
    public static function driverNameProvider(): array
    {
        return [
            'mysql'  => [new MySqlDriver(), 'mysql', '`'],
            'pgsql'  => [new PostgreSqlDriver(), 'pgsql', '"'],
            'sqlite' => [new SqliteDriver(), 'sqlite', '`'],
        ];
    }

    /**
     * @dataProvider driverNameProvider
     */
    public function testNamedDriverNameAndEscapeChar(DriverInterface $driver, string $expectedName, string $expectedChar): void
    {
        $this->assertSame($expectedName, $driver->getName());
        // The escape char is a protected constant, but we can observe it via
        // the result of a known identifier.
        $this->assertSame($expectedChar . 'id' . $expectedChar, $driver->escapeIdentifier('id'));
    }

    /**
     * @dataProvider driverNameProvider
     */
    public function testNamedDriverEscapesDottedIdentifierComponents(DriverInterface $driver, string $name, string $c): void
    {
        $expected = $c . 'users' . $c . '.' . $c . 'id' . $c;
        $this->assertSame($expected, $driver->escapeIdentifier('users.id'));
    }

    /**
     * @dataProvider driverNameProvider
     */
    public function testNamedDriverPreservesAsKeyword(DriverInterface $driver, string $name, string $c): void
    {
        $expected = $c . 'users' . $c . ' AS ' . $c . 'u' . $c;
        $this->assertSame($expected, $driver->escapeIdentifier('users AS u'));
    }

    /**
     * @dataProvider driverNameProvider
     */
    public function testNamedDriverPreservesOnAndOrKeywords(DriverInterface $driver, string $name, string $c): void
    {
        // "ON" and "AND"/"OR" are part of the regex's exception list.
        $expected = $c . 'a' . $c . '.' . $c . 'id' . $c . ' AND ' . $c . 'b' . $c . '.' . $c . 'id' . $c;
        $this->assertSame($expected, $driver->escapeIdentifier('a.id AND b.id'));

        // Numeric literals (digit-leading tokens) are not matched by the
        // identifier regex, so they pass through unquoted.
        $expected2 = $c . 'x' . $c . '=1 OR ' . $c . 'y' . $c . '=2';
        $this->assertSame($expected2, $driver->escapeIdentifier('x=1 OR y=2'));
    }

    /**
     * @dataProvider driverNameProvider
     */
    public function testNamedDriverSkipsBindParameterPrefix(DriverInterface $driver, string $name, string $c): void
    {
        // Tokens starting with ":" should not be re-escaped.
        $result = $driver->escapeIdentifier(':bind_value');
        $this->assertStringNotContainsString($c . 'bind_value' . $c, $result);
        $this->assertStringContainsString(':bind_value', $result);
    }

    public function testMySqlDriverDoublesPreExistingBacktickInIdentifier(): void
    {
        $driver = new MySqlDriver();
        // The regex doubles any existing escape character.
        $result = $driver->escapeIdentifier('weird`name');
        $this->assertStringContainsString('``', $result);
    }

    public function testCustomDriverViaSubclassing(): void
    {
        $driver = new class () extends AbstractDriver {
            protected const NAME = 'oracle';
            protected const ESCAPE_CHAR = '"';
        };

        $this->assertSame('oracle', $driver->getName());
        $this->assertSame('"users"."id"', $driver->escapeIdentifier('users.id'));
    }
}
