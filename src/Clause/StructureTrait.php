<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Clause;

use InitORM\QueryBuilder\Drivers\DriverInterface;
use InitORM\QueryBuilder\ParameterInterface;

/**
 * Structure-level concerns: the in-memory query model, cloning, importing /
 * exporting, and spawning sibling builders.
 *
 * Consumers must declare:
 *   protected array $structure;
 *   protected ParameterInterface $parameters;
 *   protected DriverInterface $driver;
 *   protected const STRUCTURE = [...];
 */
trait StructureTrait
{
    /**
     * @inheritDoc
     */
    public function newBuilder(): static
    {
        return new static($this->driver->getName());
    }

    /**
     * @inheritDoc
     */
    public function resetStructure(null|array|string $ignoreOrCare = null, ?bool $isIgnore = null): static
    {
        if ($ignoreOrCare === null) {
            $this->structure = self::STRUCTURE;

            return $this;
        }

        if (is_string($ignoreOrCare)) {
            $ignoreOrCare = [$ignoreOrCare];
        }

        $newStructure = self::STRUCTURE;
        foreach ($ignoreOrCare as $key) {
            if (!isset($this->structure[$key])) {
                continue;
            }
            $newStructure[$key] = $isIgnore
                ? $this->structure[$key]
                : (self::STRUCTURE[$key] ?? []);
        }
        $this->structure = $newStructure;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $structure
     */
    public function importQB(array $structure, bool $merge = false): static
    {
        $this->structure = array_merge($merge ? $this->structure : self::STRUCTURE, $structure);

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    public function exportQB(): array
    {
        return $this->structure;
    }

    /**
     * @inheritDoc
     */
    public function getParameter(): ParameterInterface
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function setParameter(string $key, mixed $value): static
    {
        $this->parameters->set($key, $value);

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters = []): static
    {
        foreach ($parameters as $key => $value) {
            $this->parameters->set($key, $value);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }
}
