<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Result object for extension installation/readiness operations.
 */
final readonly class ExtensionReadyResult
{
    public function __construct(
        public bool $success,
        public bool $requiresRestart,
        public string $message,
    ) {
    }

    public static function success(bool $requiresRestart = false, string $message = ''): self
    {
        return new self(true, $requiresRestart, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, false, $message);
    }
}
