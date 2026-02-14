<?php

declare(strict_types=1);

namespace App;

/**
 * Value object representing the result of a debugger health-check.
 */
final readonly class HealthCheckResult
{
    /**
     * @param bool     $passed     Whether all checks passed.
     * @param string[] $messages   Human-readable status/error messages.
     * @param string   $driverName The name of the driver that ran the check.
     */
    public function __construct(
        public bool $passed,
        public array $messages,
        public string $driverName,
    ) {
    }

    /**
     * Shortcut to create a passing result.
     *
     * @param string   $driverName
     * @param string[] $messages
     */
    public static function pass(string $driverName, array $messages = []): self
    {
        return new self(passed: true, messages: $messages, driverName: $driverName);
    }

    /**
     * Shortcut to create a failing result.
     *
     * @param string   $driverName
     * @param string[] $messages
     */
    public static function fail(string $driverName, array $messages = []): self
    {
        return new self(passed: false, messages: $messages, driverName: $driverName);
    }
}
