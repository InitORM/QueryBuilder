<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace Test\InitORM\QueryBuilder;

use InitORM\QueryBuilder\Drivers\GenericDriver;
use InitORM\QueryBuilder\Drivers\MySqlDriver;
use InitORM\QueryBuilder\Drivers\PostgreSqlDriver;
use InitORM\QueryBuilder\Drivers\SqliteDriver;
use InitORM\QueryBuilder\QueryBuilder;
use InitORM\QueryBuilder\QueryBuilderFactory;
use PHPUnit\Framework\TestCase;

/**
 * QueryBuilderFactory driver-string selection and the structure-management
 * helpers: resetStructure / importQB / exportQB / clearSelect / clone /
 * newBuilder.
 */
class FactoryAndStructureTest extends TestCase
{
    /**
     * @return array<string, array{0:?string, 1:class-string}>
     */
    public static function driverStringProvider(): array
    {
        return [
            'mysql'      => ['mysql',      MySqlDriver::class],
            'pgsql'      => ['pgsql',      PostgreSqlDriver::class],
            'postgres'   => ['postgres',   PostgreSqlDriver::class],
            'postgresql' => ['postgresql', PostgreSqlDriver::class],
            'sqlite'     => ['sqlite',     SqliteDriver::class],
            'null'       => [null,         GenericDriver::class],
            'unknown'    => ['unknown',    GenericDriver::class],
        ];
    }

    /**
     * @dataProvider driverStringProvider
     */
    public function testFactorySelectsCorrectDriver(?string $name, string $expectedClass): void
    {
        $qb = (new QueryBuilderFactory())->createQueryBuilder($name);
        $this->assertInstanceOf($expectedClass, $qb->getDriver());
    }

    public function testNewBuilderInheritsDriverName(): void
    {
        $qb = (new QueryBuilderFactory())->createQueryBuilder('mysql');
        $sibling = $qb->newBuilder();

        $this->assertInstanceOf(MySqlDriver::class, $sibling->getDriver());
    }

    public function testExportQbReturnsBlankStructureByDefault(): void
    {
        $qb = new QueryBuilder();
        $structure = $qb->exportQB();

        $this->assertSame([], $structure['select']);
        $this->assertSame([], $structure['table']);
        $this->assertSame(['AND' => [], 'OR' => []], $structure['where']);
        $this->assertNull($structure['limit']);
        $this->assertNull($structure['offset']);
    }

    public function testImportQbReplacesStructureByDefault(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('users')->where('a', 1);

        $qb->importQB(['select' => ['name']]);
        $structure = $qb->exportQB();

        // import without merge zeroes everything not supplied.
        $this->assertSame(['name'], $structure['select']);
        $this->assertSame([], $structure['table']);
        $this->assertSame(['AND' => [], 'OR' => []], $structure['where']);
    }

    public function testImportQbWithMergeKeepsExistingFields(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('users');

        $qb->importQB(['select' => ['name']], true);
        $structure = $qb->exportQB();

        // merge overwrites the named field but keeps others.
        $this->assertSame(['name'], $structure['select']);
        $this->assertSame(['users'], $structure['table']);
    }

    public function testResetStructureZeroesEverything(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('users')->where('a', 1);
        $qb->resetStructure();

        $structure = $qb->exportQB();
        $this->assertSame([], $structure['select']);
        $this->assertSame([], $structure['table']);
        $this->assertSame(['AND' => [], 'OR' => []], $structure['where']);
    }

    public function testResetStructureKeepListPreservesNamedKeys(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('users')->where('a', 1);
        $qb->resetStructure(['select'], true);

        $structure = $qb->exportQB();
        $this->assertSame(['id'], $structure['select']);
        $this->assertSame([], $structure['table']); // zeroed
    }

    public function testResetStructureIsIgnoreFalseBehavesLikeFullReset(): void
    {
        // Quirk of the existing API: when $isIgnore is false, the function
        // starts from a blank structure and then explicitly re-zeroes the
        // listed keys — effectively a full reset.
        $qb = new QueryBuilder();
        $qb->select('id')->from('users')->where('a', 1);
        $qb->resetStructure(['select'], false);

        $structure = $qb->exportQB();
        $this->assertSame([], $structure['select']);
        $this->assertSame([], $structure['table']);
    }

    public function testResetStructureAcceptsStringForSingleKey(): void
    {
        $qb = new QueryBuilder();
        $qb->select('id')->from('users');
        $qb->resetStructure('select', true); // keep 'select'

        $structure = $qb->exportQB();
        $this->assertSame(['id'], $structure['select']);
        $this->assertSame([], $structure['table']);
    }

    public function testCloneDecouplesStructureFromOriginal(): void
    {
        $qb = new QueryBuilder();
        $qb->from('users');
        $clone = $qb->clone();

        $clone->from('posts');
        $this->assertSame(['users'], $qb->exportQB()['table']);
        $this->assertSame(['posts'], $clone->exportQB()['table']);
    }

    public function testCloneDecouplesParameterBag(): void
    {
        // Integer values are inlined, not parameterized — use strings so the
        // bag actually fills.
        $qb = new QueryBuilder();
        $qb->from('users')->where('country', 'TR');
        $clone = $qb->clone();

        $clone->where('country', 'US');
        $this->assertCount(1, $qb->getParameter()->all());
        $this->assertCount(2, $clone->getParameter()->all());
    }

    public function testClearSelectEmptiesSelectBucket(): void
    {
        $qb = new QueryBuilder();
        $qb->select('a', 'b', 'c');
        $this->assertCount(3, $qb->exportQB()['select']);

        $qb->clearSelect();
        $this->assertSame([], $qb->exportQB()['select']);
    }

    public function testSetParameterRegistersOnBag(): void
    {
        $qb = new QueryBuilder();
        $qb->setParameter('id', 5);

        $this->assertSame(5, $qb->getParameter()->get('id'));
    }

    public function testSetParametersRegistersManyOnBag(): void
    {
        $qb = new QueryBuilder();
        $qb->setParameters(['a' => 1, 'b' => 2]);

        $this->assertSame(1, $qb->getParameter()->get('a'));
        $this->assertSame(2, $qb->getParameter()->get('b'));
    }
}
