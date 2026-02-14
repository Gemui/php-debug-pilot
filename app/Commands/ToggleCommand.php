<?php

declare(strict_types=1);

namespace App\Commands;

use App\DriverManager;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use LaravelZero\Framework\Commands\Command;

/**
 * CLI command to toggle a debugger extension on or off.
 *
 * When enabling, checks if the extension is installed first and
 * offers to install it (mirroring the SetupCommand flow).
 *
 * Usage:
 *   php debug-pilot toggle xdebug
 *   php debug-pilot toggle pcov
 */
final class ToggleCommand extends Command
{
    protected $signature = 'toggle {extension : Extension name (e.g. xdebug, pcov)}';

    protected $description = 'Enable or disable a debugger extension';

    public function handle(
        DriverManager $driverManager,
        ExtensionInstaller $installer,
        InstallationAdvisor $advisor,
    ): int {
        $name = strtolower((string) $this->argument('extension'));

        // Resolve the driver
        try {
            $driver = $driverManager->resolveDebugger($name);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $wasEnabled = $driver->isEnabled();
        $newState = !$wasEnabled;

        // ---------------------------------------------------------------
        //  Guard: cannot enable an extension that is not installed
        // ---------------------------------------------------------------
        if ($newState && !$driver->isInstalled() && !$driver->hasIniDirective()) {
            $this->warn("The '{$name}' extension is not installed.");

            if ($installer->canAutoInstall()) {
                $install = $this->confirm('Would you like to install it now?', true);
                if ($install) {
                    $this->line('Running: <info>' . $advisor->getInstallCommand($name) . '</info>');
                    $result = $installer->install($name, function (string $line): void {
                        $this->line("  │ {$line}");
                    });

                    if ($result->success) {
                        $this->info("✅ {$name} installed successfully.");
                    } else {
                        $this->error("❌ Installation failed (exit {$result->exitCode}).");
                        return self::FAILURE;
                    }
                } else {
                    $this->info('Cannot enable an extension that is not installed. Install it first, then re-run.');
                    return self::SUCCESS;
                }
            } else {
                $this->warn($advisor->getInstallInstructions($name));
                $this->info('Install the extension first, then re-run this command.');
                return self::SUCCESS;
            }
        }

        $action = $newState ? 'Enabling' : 'Disabling';

        $this->newLine();
        $this->info("{$action} {$name}…");

        try {
            $driver->setEnabled($newState);
        } catch (\Throwable $e) {
            $this->error("❌ Failed to {$action} {$name}: {$e->getMessage()}");
            return self::FAILURE;
        }

        $stateLabel = $newState ? 'enabled' : 'disabled';

        $this->newLine();
        $this->info("✅ {$name} is now {$stateLabel}.");
        $this->newLine();
        $this->warn('⚠️  Please restart your PHP process (php-fpm, Apache, or terminal)');
        $this->line('for the change to take effect.');

        return self::SUCCESS;
    }
}
