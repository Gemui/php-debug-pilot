<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Config;
use App\HealthCheckResult;

/**
 * Contract for debugger drivers (e.g., Xdebug, Pcov).
 *
 * Each driver encapsulates the logic to detect, configure,
 * and verify a specific PHP debugging extension.
 */
interface DebuggerDriver
{
    /**
     * Get the unique identifier for this debugger.
     *
     * @return string e.g., 'xdebug', 'pcov'
     */
    public function getName(): string;

    /**
     * Check whether the debugger extension is installed on the system.
     */
    public function isInstalled(): bool;

    /**
     * Apply the debugger configuration to php.ini.
     *
     * @return bool True if configuration was successfully written.
     */
    public function configure(Config $config): bool;

    /**
     * Run a health-check to verify the debugger is correctly configured.
     */
    public function verify(): HealthCheckResult;
}
