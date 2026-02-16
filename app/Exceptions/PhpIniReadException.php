<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PhpIniReadException extends \RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Failed to read php.ini at "%s".', $path));
    }
}
