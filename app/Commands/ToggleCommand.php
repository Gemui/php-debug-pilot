<?php

declare(strict_types=1);

namespace App\Commands;

use App\DriverManager;
use App\Support\ExtensionInstallationService;
use App\Support\EnvironmentDetector;
use App\Exceptions\PhpIniNotWritableException;
use App\Exceptions\PhpIniNotFoundException;
use LaravelZero\Framework\Commands\Command;

/**
 * CLI command to toggle a debugger extension on or off.
 *
 * When enabling, checks if the extension is installed first and
 * offers to install it (mirroring the SetupCommand flow).
 *
 * Usage:
 *   php debug-pilot toggle xdebug
 */
final class ToggleCommand extends Command
{
    protected $signature = 'toggle {extension : Extension name (e.g. xdebug)}';

    protected $description = 'Enable or disable a debugger extension';

    public function handle(
        DriverManager $driverManager,
        ExtensionInstallationService $installationService,
        EnvironmentDetector $env,
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

        // Ensure extension is ready if enabling
        if ($newState && (!$driver->isInstalled() || !$driver->isEnabled())) {
            $result = $installationService->ensureExtensionReady(
                $driver,
                fn(string $line) => $this->line($line),
                fn(string $message, bool $default) => true
            );

            if (!$result->success) {
                $this->info($result->message);
                return self::SUCCESS;
            }

            // Extension was just installed/enabled
            $this->newLine();
            $this->info("✅ {$name} is now enabled.");
            $this->newLine();
            $this->warn('⚠️  Please restart your PHP process (php-fpm, Apache, or terminal)');
            $this->line('for the change to take effect.');
            return self::SUCCESS;
        }

        $action = $newState ? 'Enabling' : 'Disabling';

        $this->newLine();
        $this->info("{$action} {$name}…");

        try {
            $driver->setEnabled($newState);
        } catch (PhpIniNotWritableException $e) {
            $this->error("❌ {$e->getMessage()}");
            $this->newLine();
            if (!$env->isDocker()) {
                $this->line('Try running the command with sudo:');
                $this->line('  sudo debug-pilot toggle ' . $name);
            } else {
                $this->line('Ensure your Dockerfile grants write permissions to php.ini.');
            }
            return self::FAILURE;
        } catch (PhpIniNotFoundException $e) {
            $this->error("❌ {$e->getMessage()}");
            return self::FAILURE;
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
