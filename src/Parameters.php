<?php
/**
 * InitORM QueryBuilder
 *
 * This file is part of InitORM QueryBuilder.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2023 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     1.0
 * @link        https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);
namespace InitORM\QueryBuilder;

use Closure;

class Parameters implements ParameterInterface
{

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
        $this->parameters[':' . ltrim(str_replace('.', '', $key), ':')] = $value;

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
            $key = md5((string)$key);
        }
        $originKey = ltrim(str_replace('.', '', $key), ':');
        $i = 0;
        do {
            $key = ':' . ($i === 0 ? $originKey : $originKey . '_' . $i);
            ++$i;
            $hasParameter = isset($this->parameters[$key]);
        } while($hasParameter);

        $this->parameters[$key] = $value;

        return $key;
    }

    /**
     * @inheritDoc
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

        return ($default instanceof Closure) ? call_user_func_array($default, []) : $default;
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
