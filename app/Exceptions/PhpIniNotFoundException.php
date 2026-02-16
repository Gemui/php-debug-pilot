<?php

declare(strict_types=1);

namespace App\Exceptions;

final class PhpIniNotFoundException extends \RuntimeException
{
    public static function create(): self
    {
        return new self('Could not auto-detect php.ini path. Please specify it manually.');
    }
}
