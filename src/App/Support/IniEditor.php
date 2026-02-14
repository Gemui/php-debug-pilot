<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Utility for commenting / uncommenting directive lines in php.ini content.
 *
 * All methods operate on string content and return transformed strings.
 * The caller is responsible for reading/writing the actual file.
 */
final class IniEditor
{
    /**
     * Check if a matching line exists and is uncommented.
     *
     * @param string $iniContent Full php.ini content.
     * @param string $pattern    Regex pattern to match the directive line.
     */
    public function isLineEnabled(string $iniContent, string $pattern): bool
    {
        // Match a line that is NOT prefixed with ';' (allowing leading whitespace)
        return (bool) preg_match('/^\s*(?!;)' . $pattern . '/m', $iniContent);
    }

    /**
     * Comment out a matching directive line by prefixing it with ';'.
     *
     * Only modifies uncommented occurrences.
     *
     * @param string $iniContent Full php.ini content.
     * @param string $pattern    Regex pattern to match the directive line.
     */
    public function commentLine(string $iniContent, string $pattern): string
    {
        return (string) preg_replace(
            '/^(\s*)(?!;)(' . $pattern . '.*)$/m',
            '$1;$2',
            $iniContent
        );
    }

    /**
     * Uncomment a matching directive line by removing the leading ';'.
     *
     * Only modifies commented occurrences.
     *
     * @param string $iniContent Full php.ini content.
     * @param string $pattern    Regex pattern to match the directive line.
     */
    public function uncommentLine(string $iniContent, string $pattern): string
    {
        return (string) preg_replace(
            '/^(\s*);+\s*(' . $pattern . '.*)$/m',
            '$1$2',
            $iniContent
        );
    }

    /**
     * Check whether a matching line exists at all (commented or not).
     *
     * @param string $iniContent Full php.ini content.
     * @param string $pattern    Regex pattern to match the directive line.
     */
    public function hasLine(string $iniContent, string $pattern): bool
    {
        return (bool) preg_match('/^\s*;?\s*' . $pattern . '/m', $iniContent);
    }

    /**
     * Append a new line to the end of the content.
     *
     * @param string $iniContent Full php.ini content.
     * @param string $line       The line to append (without trailing newline).
     */
    public function appendLine(string $iniContent, string $line): string
    {
        $separator = str_ends_with(rtrim($iniContent), "\n") ? '' : "\n";

        return $iniContent . $separator . $line . "\n";
    }
}
