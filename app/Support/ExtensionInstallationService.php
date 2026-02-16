<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\DebuggerDriver;

/**
 * Service for handling extension installation and enablement flow.
 *
 * Centralizes the logic for ensuring an extension is ready to use,
 * which includes checking installation status, offering to install
 * if missing, and enabling if disabled.
 */
class ExtensionInstallationService
{
    public function __construct(
        private readonly ExtensionInstaller $installer,
        private readonly InstallationAdvisor $advisor,
    ) {
    }

    /**
     * Ensure the extension is ready to use (installed and enabled).
     *
     * @param DebuggerDriver $driver The driver for the extension
     * @param callable(string): void $outputCallback Callback for streaming output
     * @param callable(string, bool): bool $confirmCallback Callback for user confirmation
     * @return ExtensionReadyResult Result indicating success and whether restart is needed
     */
    public function ensureExtensionReady(
        DebuggerDriver $driver,
        callable $outputCallback,
        callable $confirmCallback,
    ): ExtensionReadyResult {
        $name = $driver->getName();
        $requiresRestart = false;

        // Case 1: Extension is fully installed and loaded
        if ($driver->isInstalled()) {
            return ExtensionReadyResult::success();
        }

        // Case 2: Extension is installed but disabled — offer to enable it
        if ($driver->hasIniDirective()) {
            $enable = $confirmCallback("The '{$name}' extension is disabled. Would you like to enable it now?", true);

            if (!$enable) {
                return ExtensionReadyResult::failure("Cannot proceed without enabling {$name}.");
            }

            try {
                $driver->setEnabled(true);
                $outputCallback("✅ {$name} enabled.");
                return ExtensionReadyResult::success(requiresRestart: true);
            } catch (\Throwable $e) {
                return ExtensionReadyResult::failure("Failed to enable {$name}: {$e->getMessage()}");
            }
        }

        // Case 3: Extension is not installed — offer to install it
        if (!$this->installer->canAutoInstall()) {
            // Cannot auto-install (Windows or Docker) — provide instructions
            $command = $this->advisor->getInstallCommand($name);
            $instructions = $this->advisor->getInstallInstructions($name);

            $outputCallback("The '{$name}' extension is not installed.");
            $outputCallback('');
            $outputCallback($instructions);
            $outputCallback('');
            $outputCallback("Suggested command: {$command}");
            $outputCallback('');
            $outputCallback("After installing, re-run this command.");

            return ExtensionReadyResult::failure("Cannot proceed without installing {$name}.");
        }

        // Auto-install is available — ask user
        $install = $confirmCallback("The '{$name}' extension is not installed. Would you like to install it now?", true);

        if (!$install) {
            return ExtensionReadyResult::failure("Cannot proceed without installing {$name}.");
        }

        // Attempt installation
        $outputCallback("Installing {$name}...");
        $result = $this->installer->install($name, $outputCallback);

        if (!$result->success) {
            $outputCallback("❌ Installation failed.");
            if ($result->output !== '') {
                $outputCallback($result->output);
            }
            return ExtensionReadyResult::failure("Failed to install {$name}.");
        }

        $outputCallback("✅ {$name} installed successfully.");
        return ExtensionReadyResult::success(requiresRestart: true);
    }
}
