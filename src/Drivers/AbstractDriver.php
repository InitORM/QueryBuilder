<?php

/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * Base implementation that handles the regex-driven identifier escaping.
 *
 * Each concrete driver supplies:
 *   - {@see self::NAME}         — the canonical lowercase name.
 *   - {@see self::ESCAPE_CHAR}  — the identifier-quoting character; pass an
 *                                 empty string to disable quoting (see
 *                                 {@see GenericDriver}).
 *
 * The regex used by {@see self::escapeIdentifier()}:
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
     * @inheritDoc
     */
    public function escapeIdentifier(string $identifier): string
    {
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
