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
     * @return string e.g., 'xdebug'
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

    /**
     * Check whether the extension is currently enabled in php.ini.
     */
    public function isEnabled(): bool;

    /**
     * Enable or disable the extension by editing php.ini.
     *
     * @param bool $enabled True to enable, false to disable.
     * @return bool True if the change was successfully written.
     */
    public function setEnabled(bool $enabled): bool;

    /**
     * Check if the extension has a directive line in php.ini (even if commented out).
     *
     * This indicates the extension binary is available on the system,
     * even if not currently loaded in this PHP process.
     */
    public function hasIniDirective(): bool;
}
