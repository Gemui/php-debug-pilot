<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Value object representing the result of an extension installation attempt.
 */
final readonly class InstallResult
{
    public function __construct(
        public bool $success,
        public string $output,
        public string $errorOutput,
        public int $exitCode,
    ) {
    }

    public static function success(string $output = ''): self
    {
        return new self(true, $output, '', 0);
    }

    public static function failure(string $errorOutput, int $exitCode = 1): self
    {
        return new self(false, '', $errorOutput, $exitCode);
    }
}
