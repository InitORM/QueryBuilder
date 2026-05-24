<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\QueryBuilder;
use InitORM\QueryBuilder\RawQuery;
use PHPUnit\Framework\TestCase;

/**
 * Direct unit tests for {@see RawQuery}. The three input forms (string,
 * Closure, mixed) are each covered.
 */
class RawQueryTest extends TestCase
{
    public function testStringInputStoredVerbatim(): void
    {
        $raw = new RawQuery('NOW()');
        $this->assertSame('NOW()', (string) $raw);
    }

    public function testClosureCanReturnString(): void
    {
        $raw = new RawQuery(function () {
            return 'CURRENT_TIMESTAMP';
        });

        $this->assertSame('CURRENT_TIMESTAMP', (string) $raw);
    }

    public function testClosureCanReturnStringableObject(): void
    {
        $raw = new RawQuery(function () {
            return new class () {
                public function __toString(): string
                {
                    return 'INET_ATON(?)';
                }
            };
        });

        $this->assertSame('INET_ATON(?)', (string) $raw);
    }

    public function testClosureMayBuildViaSuppliedBuilder(): void
    {
        $raw = new RawQuery(function (QueryBuilder $qb): void {
            $qb->select('id')->from('users')->where('active', 1);
        });

        $this->assertSame('SELECT id FROM users WHERE active = 1', (string) $raw);
    }

    public function testMixedInputIsCastToString(): void
    {
        $raw = new RawQuery(42);
        $this->assertSame('42', (string) $raw);
    }

    public function testGetReturnsEmptyStringByDefault(): void
    {
        // The constructor always calls set(), so this only exercises the
        // null-coalesce fall-back inside get() — useful when subclasses
        // bypass set().
        $raw = new RawQuery('');
        $this->assertSame('', $raw->get());
    }

    public function testSetReplacesStoredValue(): void
    {
        $raw = new RawQuery('old');
        $raw->set('new');

        $this->assertSame('new', (string) $raw);
    }

    public function testSetReturnsSelfForChaining(): void
    {
        $raw = new RawQuery('a');
        $this->assertSame($raw, $raw->set('b'));
    }

    public function testStaticRawFactoryEqualsConstructor(): void
    {
        $a = RawQuery::raw('NOW()');
        $b = new RawQuery('NOW()');

        $this->assertSame((string) $a, (string) $b);
        $this->assertInstanceOf(RawQuery::class, $a);
    }

    public function testToStringMagicMethodMatchesGet(): void
    {
        $raw = new RawQuery('foo');
        $this->assertSame($raw->get(), (string) $raw);
    }
}
