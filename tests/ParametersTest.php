<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Parameters;
use InitORM\QueryBuilder\RawQuery;
use PHPUnit\Framework\TestCase;

/**
 * Direct unit tests for the {@see Parameters} bag — the clause builders
 * exercise it indirectly, but this file pins down the collision-suffix
 * scheme, sanitization, null short-circuit and RawQuery key hashing.
 */
class ParametersTest extends TestCase
{
    public function testSetStoresWithColonPrefixedSanitizedKey(): void
    {
        $bag = new Parameters();
        $bag->set('user.id', 42);

        $this->assertSame([':userid' => 42], $bag->all());
    }

    public function testSetOverwritesOnRepeatedKey(): void
    {
        $bag = new Parameters();
        $bag->set('id', 1);
        $bag->set('id', 2);

        $this->assertSame([':id' => 2], $bag->all());
    }

    public function testAddReturnsColonPrefixedPlaceholderName(): void
    {
        $bag = new Parameters();
        $placeholder = $bag->add('id', 5);

        $this->assertSame(':id', $placeholder);
        $this->assertSame([':id' => 5], $bag->all());
    }

    public function testAddAutoSuffixesOnCollision(): void
    {
        $bag = new Parameters();
        $first = $bag->add('id', 1);
        $second = $bag->add('id', 2);
        $third = $bag->add('id', 3);

        $this->assertSame(':id', $first);
        $this->assertSame(':id_1', $second);
        $this->assertSame(':id_2', $third);
        $this->assertSame([':id' => 1, ':id_1' => 2, ':id_2' => 3], $bag->all());
    }

    public function testAddReturnsLiteralNullForNullValueAndDoesNotRegister(): void
    {
        $bag = new Parameters();
        $result = $bag->add('id', null);

        $this->assertSame('NULL', $result);
        $this->assertSame([], $bag->all());
    }

    public function testAddSanitizesNonAlphaNumericKeyChars(): void
    {
        $bag = new Parameters();
        $bag->add('user.id', 5);

        $this->assertArrayHasKey(':userid', $bag->all());
    }

    public function testAddRawQueryKeyHashesToStablePlaceholder(): void
    {
        $bag = new Parameters();
        $raw = new RawQuery('some expression');
        $placeholder = $bag->add($raw, 1);

        // md5 of "some expression" — stable hash, always 32 hex chars
        $this->assertMatchesRegularExpression('/^:[a-f0-9]{32}$/', $placeholder);
    }

    public function testGetReturnsAllWhenKeyIsNull(): void
    {
        $bag = new Parameters();
        $bag->set('a', 1)->set('b', 2);

        $this->assertSame([':a' => 1, ':b' => 2], $bag->get());
    }

    public function testGetReturnsSingleValueByKeyWithoutColon(): void
    {
        $bag = new Parameters();
        $bag->set('id', 99);

        $this->assertSame(99, $bag->get('id'));
        $this->assertSame(99, $bag->get(':id')); // with colon also works
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $bag = new Parameters();
        $this->assertSame('fallback', $bag->get('missing', 'fallback'));
    }

    public function testGetInvokesClosureDefaultLazily(): void
    {
        $bag = new Parameters();
        $bag->set('id', 1);

        $invocations = 0;
        $closure = function () use (&$invocations) {
            $invocations++;
            return 'lazy';
        };

        // Present key — closure NOT invoked.
        $bag->get('id', $closure);
        $this->assertSame(0, $invocations);

        // Missing key — closure invoked.
        $result = $bag->get('missing', $closure);
        $this->assertSame('lazy', $result);
        $this->assertSame(1, $invocations);
    }

    public function testResetEmptiesTheBag(): void
    {
        $bag = new Parameters();
        $bag->set('a', 1)->add('b', 2);
        $this->assertCount(2, $bag->all());

        $bag->reset();
        $this->assertSame([], $bag->all());
    }

    public function testMergeCombinesArrayAndOtherBags(): void
    {
        $other = (new Parameters())->set('c', 3)->set('d', 4);

        $bag = new Parameters();
        $bag->merge(['a' => 1, 'b' => 2], $other);

        $this->assertSame([
            ':a' => 1,
            ':b' => 2,
            ':c' => 3,
            ':d' => 4,
        ], $bag->all());
    }

    public function testFluentChainingReturnsSelf(): void
    {
        $bag = new Parameters();
        $this->assertSame($bag, $bag->set('a', 1));
        $this->assertSame($bag, $bag->merge(['b' => 2]));
        $this->assertSame($bag, $bag->reset());
    }
}
