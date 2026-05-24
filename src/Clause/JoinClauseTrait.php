<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Clause;

use Closure;
use InitORM\QueryBuilder\Helper\BucketCompiler;
use InitORM\QueryBuilder\RawQuery;

/**
 * JOIN-clause builders. Each variant boils down to {@see self::join()} with a
 * different keyword. When {@code $onStmt} is a Closure, it is invoked with a
 * fresh QueryBuilder so the caller can compose the ON clause using the same
 * fluent API (and optionally raise WHERE / HAVING side-conditions).
 */
trait JoinClauseTrait
{
    public function join(RawQuery|string $table, RawQuery|Closure|string|null $onStmt = null, string $type = 'INNER'): static
    {
        if (is_string($table) && $type !== 'SELF') {
            $table = $this->driver->escapeIdentifier($table);
        }
        $table = (string) $table;

        if ($onStmt instanceof Closure) {
            $sub = $this->clone()->resetStructure();
            $onStmt = $onStmt($sub);
            if ($onStmt === null) {
                $subStructure = $sub->exportQB();
                if ($where = BucketCompiler::compile($subStructure, 'where')) {
                    $this->where($this->raw($where));
                }
                if ($having = BucketCompiler::compile($subStructure, 'having')) {
                    $this->having($this->raw($having));
                }
                $onStmt = BucketCompiler::compile($subStructure, 'on');
            }
        } elseif (is_string($onStmt)) {
            $onStmt = $this->driver->escapeIdentifier($onStmt);
        }

        $type = trim(strtoupper($type));
        switch ($type) {
            case 'SELF':
                $this->addFrom($table);
                $this->where(is_string($onStmt) ? $this->raw($onStmt) : $onStmt);
                break;
            case 'NATURAL':
            case 'NATURAL JOIN':
                $this->structure['join'][$table] = 'NATURAL JOIN ' . $table;
                break;
            default:
                $this->structure['join'][$table] = trim($type . ' JOIN ' . $table . ' ON ' . $onStmt);
        }

        return $this;
    }

    public function selfJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): static
    {
        return $this->join($table, $onStmt, 'SELF');
    }

    public function innerJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): static
    {
        return $this->join($table, $onStmt);
    }

    public function leftJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): static
    {
        return $this->join($table, $onStmt, 'LEFT');
    }

    public function rightJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): static
    {
        return $this->join($table, $onStmt, 'RIGHT');
    }

    public function leftOuterJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): static
    {
        return $this->join($table, $onStmt, 'LEFT OUTER');
    }

    public function rightOuterJoin(RawQuery|string $table, RawQuery|Closure|string $onStmt): static
    {
        return $this->join($table, $onStmt, 'RIGHT OUTER');
    }

    public function naturalJoin(RawQuery|string $table): static
    {
        return $this->join($table, null, 'NATURAL');
    }
}
