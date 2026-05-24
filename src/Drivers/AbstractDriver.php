<?php
/**
 * @package InitORM\QueryBuilder
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\QueryBuilder\Drivers;

/**
 * Base implementation that handles the regex-driven identifier escaping. Each
 * concrete driver supplies a {@see self::NAME} and {@see self::ESCAPE_CHAR};
 * an empty escape character disables quoting (see {@see GenericDriver}).
 */
abstract class AbstractDriver implements DriverInterface
{
    protected const NAME = null;
    protected const ESCAPE_CHAR = '';

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

    public function getName(): ?string
    {
        return static::NAME;
    }
}
