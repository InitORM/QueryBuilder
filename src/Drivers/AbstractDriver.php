<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

use InitORM\QueryBuilder\Exceptions\QueryBuilderInvalidArgumentException;

/**
 * Base implementation that handles the regex-driven identifier escaping.
 *
 * Each concrete driver supplies:
 *   - {@see self::NAME}         — the canonical lowercase name.
 *   - {@see self::ESCAPE_CHAR}  — the identifier-quoting character; pass an
 *                                 empty string to disable quoting (see
 *                                 {@see GenericDriver}).
 *
 * {@see self::escapeIdentifier()} rejects identifiers that contain SQL
 * query-breakout sequences (`;` or `--`) — those characters never appear in
 * legitimate identifiers and are the canonical pivot for SQL injection when
 * a caller forwards user input as a table or column name without sanitising
 * it. PostgreSQL allows multi-statement queries by default, which makes this
 * defense-in-depth particularly important.
 *
 * After the validation step, the regex:
 *
 *   - skips bind-parameter prefixes ":foo";
 *   - skips the SQL keywords AND, OR, AS, ON (both cases);
 *   - quotes each remaining identifier-shaped run with the configured char;
 *   - first doubles any pre-existing occurrence of the escape char.
 */
abstract class AbstractDriver implements DriverInterface
{
    /**
     * Canonical lowercase driver name, or null for a no-op driver.
     */
    protected const NAME = null;

    /**
     * Identifier-quoting character. Empty string disables quoting.
     */
    protected const ESCAPE_CHAR = '';

    /**
     * Sequences that must never appear in an identifier — the SQL
     * statement-separator and the line-comment leader. Blocking them defeats
     * the most common identifier-pivoted injection technique.
     */
    private const FORBIDDEN_SEQUENCES = [';', '--'];

    /**
     * @inheritDoc
     *
     * @throws QueryBuilderInvalidArgumentException When the identifier
     *         contains a forbidden query-breakout sequence (`;` or `--`).
     */
    public function escapeIdentifier(string $identifier): string
    {
        foreach (self::FORBIDDEN_SEQUENCES as $sequence) {
            if (str_contains($identifier, $sequence)) {
                throw new QueryBuilderInvalidArgumentException(sprintf(
                    'Identifier contains a forbidden SQL sequence (%s): %s',
                    $sequence,
                    $identifier,
                ));
            }
        }

        $char = static::ESCAPE_CHAR;
        if ($char === '') {
            return $identifier;
        }

        return (string) preg_replace(
            '/\b(?<!:)(?!(AND|and|OR|or|AS|as|ON|on)\b)([a-zA-Z_][a-zA-Z0-9_]*)\b/',
            $char . '$0' . $char,
            str_replace($char, $char . $char, trim($identifier, $char))
        );
    }

    /**
     * @inheritDoc
     */
    public function getName(): ?string
    {
        return static::NAME;
    }
}
