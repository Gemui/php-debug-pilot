<?php

declare(strict_types=1);

namespace App\Commands;

use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use LaravelZero\Framework\Commands\Command;

/**
 * Standalone command to install a PHP extension.
 *
 * Usage:
 *   php debug-pilot install xdebug
 *   php debug-pilot install pcov
 */
final class InstallCommand extends Command
{
    private const KNOWN_EXTENSIONS = ['xdebug', 'pcov'];

    protected $signature = 'install {extension : Extension name (xdebug, pcov)}';

    protected $description = 'Install a PHP debugger extension';

    public function handle(
        ExtensionInstaller $installer,
        InstallationAdvisor $advisor,
        EnvironmentDetector $env,
    ): int {
        $ext = strtolower((string) $this->argument('extension'));

        if (!in_array($ext, self::KNOWN_EXTENSIONS, true)) {
            $this->error("Unknown extension '{$ext}'. Supported: " . implode(', ', self::KNOWN_EXTENSIONS));
            return self::FAILURE;
        }

        // Already installed?
        if ($env->isExtensionLoaded($ext)) {
            $this->info("The '{$ext}' extension is already installed and loaded.");
            return self::SUCCESS;
        }

        // Can we auto-install?
        if (!$installer->canAutoInstall()) {
            $this->warn('Auto-installation is not available in this environment.');
            $this->warn($advisor->getInstallInstructions($ext));
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Installing {$ext}…");
        $this->line("Running: <info>{$advisor->getInstallCommand($ext)}</info>");
        $this->newLine();

        $result = $installer->install($ext, function (string $line): void {
            $this->line("  │ {$line}");
        });

        if ($result->success) {
            $this->newLine();
            $this->info("✅ {$ext} installed successfully.");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("❌ Installation failed (exit code {$result->exitCode}).");
        if ($result->errorOutput !== '') {
            $this->newLine();
            $this->error($result->errorOutput);
        }

        return self::FAILURE;
    }
}
