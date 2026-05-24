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
    public function newBuilder(): static
    {
        return new static($this->driver->getName());
    }

    /**
     * Resets the structure to its blank-slate state, optionally preserving
     * (or zeroing) only specific keys.
     *
     * @param string[]|string|null $ignoreOrCare Keys to act on (or null to
     *                                           reset everything).
     * @param bool|null            $isIgnore     true → keep the listed keys;
     *                                           false → zero only those keys.
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

    public function clone(): static
    {
        return clone $this;
    }

    public function importQB(array $structure, bool $merge = false): static
    {
        $this->structure = array_merge($merge ? $this->structure : self::STRUCTURE, $structure);

        return $this;
    }

    public function exportQB(): array
    {
        return $this->structure;
    }

    public function getParameter(): ParameterInterface
    {
        return $this->parameters;
    }

    public function setParameter(string $key, mixed $value): static
    {
        $this->parameters->set($key, $value);

        return $this;
    }

    public function setParameters(array $parameters = []): static
    {
        foreach ($parameters as $key => $value) {
            $this->parameters->set($key, $value);
        }

        return $this;
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }
}
