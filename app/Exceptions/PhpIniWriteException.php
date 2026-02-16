<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PhpIniWriteException extends \RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Failed to write to php.ini at "%s".', $path));
    }
}
