<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PhpIniNotWritableException extends \RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(
            sprintf('Cannot write to php.ini at "%s". Check file permissions or run with sudo.', $path)
        );
    }
}
