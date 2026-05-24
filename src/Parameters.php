<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder;

use Closure;

/**
 * Default {@see ParameterInterface} implementation backed by a plain array.
 *
 * - {@see self::add()} auto-suffixes collisions ("col", "col_1", "col_2", …)
 *   and short-circuits null values to the literal string "NULL".
 * - {@see self::set()} overwrites by key.
 *
 * Stored keys are sanitized to {@code [A-Za-z0-9_]} and prefixed with ":" so
 * the resulting map plugs directly into {@see \PDOStatement::execute()}.
 */
class Parameters implements ParameterInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $parameters;

    public function __construct()
    {
        $this->parameters = [];
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value): self
    {
        $this->parameters[':' . preg_replace('/[^A-Za-z0-9_]/', '', $key)] = $value;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function add(RawQuery|string $key, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($key instanceof RawQuery) {
            $key = md5((string) $key);
        }
        $key = preg_replace('/[^A-Za-z0-9_]/', '', $key);
        $originKey = ltrim((string) $key, ':');
        $i = 0;
        do {
            $key = ':' . ($i === 0 ? $originKey : $originKey . '_' . $i);
            ++$i;
            $hasParameter = isset($this->parameters[$key]);
        } while ($hasParameter);

        $this->parameters[$key] = $value;

        return $key;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed>|ParameterInterface ...$arrays
     */
    public function merge(array|ParameterInterface ...$arrays): self
    {
        foreach ($arrays as $array) {
            if ($array instanceof ParameterInterface) {
                $array = $array->all();
            }
            foreach ($array as $key => $value) {
                $this->set($key, $value);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->parameters;
        }

        $key = ':' . ltrim($key, ':');
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        return $default instanceof Closure ? $default() : $default;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function reset(): self
    {
        $this->parameters = [];

        return $this;
    }
}
