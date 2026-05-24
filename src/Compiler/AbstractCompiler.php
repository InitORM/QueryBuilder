<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Compiler;

use InitORM\QueryBuilder\Exceptions\QueryBuilderException;
use InitORM\QueryBuilder\Helper\BucketCompiler;

/**
 * Shared compile-time helpers used by the concrete query compilers. The
 * structure array passed in is treated as read-only — each helper simply
 * walks a section of it and returns the corresponding SQL fragment.
 */
abstract class AbstractCompiler implements CompilerInterface
{
    /**
     * Compile the WHERE bucket (without the leading "WHERE" keyword).
     *
     * @param array<string, mixed> $structure
     */
    protected function compileWhere(array $structure): ?string
    {
        return $this->compileBucket($structure, 'where');
    }

    /**
     * Compile the HAVING bucket, prefixed with " HAVING ". Returns null when
     * the bucket is empty.
     *
     * @param array<string, mixed> $structure
     */
    protected function compileHaving(array $structure): ?string
    {
        $body = $this->compileBucket($structure, 'having');

        return $body === null ? null : ' HAVING ' . $body;
    }

    /**
     * Compile the AND/OR bucket of WHERE, HAVING or ON. Delegates to
     * {@see BucketCompiler::compile()}; see that method for the joining
     * rules.
     *
     * @param array<string, mixed> $structure
     */
    protected function compileBucket(array $structure, string $key): ?string
    {
        return BucketCompiler::compile($structure, $key);
    }

    /**
     * Compile the LIMIT / OFFSET tail. Returns " LIMIT n", " LIMIT m, n",
     * " OFFSET n" or null.
     *
     * @param array<string, mixed> $structure
     */
    protected function compileLimit(array $structure): ?string
    {
        if ($structure['limit'] === null && $structure['offset'] === null) {
            return null;
        }

        $statement = ' ';
        if ($structure['limit'] === null) {
            $statement .= 'OFFSET ' . $structure['offset'];
        } else {
            $statement .= 'LIMIT '
                . ($structure['offset'] !== null ? $structure['offset'] . ', ' : '')
                . $structure['limit'];
        }

        return $statement;
    }

    /**
     * Returns the schema (table) name targeted by INSERT/UPDATE/DELETE.
     * Multiple tables in {@code structure['table']} mean the caller is in
     * SELECT/JOIN territory; for mutation queries we use the last entry.
     *
     * @param array<string, mixed> $structure
     *
     * @throws QueryBuilderException
     */
    protected function compileSchemaName(array $structure): string
    {
        if (empty($structure['table'])) {
            throw new QueryBuilderException('Table name not found when query.');
        }

        return (string) end($structure['table']);
    }
}
