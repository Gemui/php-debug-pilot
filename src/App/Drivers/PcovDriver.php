<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use RuntimeException;

/**
 * Pcov driver for fast code-coverage collection.
 *
 * Pcov is a lightweight alternative to Xdebug's coverage mode.
 * When Pcov is activated this driver ensures Xdebug's coverage
 * mode is disabled to prevent conflicts.
 */
final class PcovDriver implements DebuggerDriver
{
    public function __construct(
        private readonly EnvironmentDetector $env,
    ) {
    }

    public function getName(): string
    {
        return 'pcov';
    }

    public function isInstalled(): bool
    {
        return $this->env->isExtensionLoaded('pcov');
    }

    /**
     * Enable Pcov and disable conflicting Xdebug coverage mode.
     */
    public function configure(Config $config): bool
    {
        $iniPath = $this->resolveIniPath($config->phpIniPath);

        if (!is_writable($iniPath)) {
            throw new RuntimeException(
                sprintf('Cannot write to php.ini at "%s". Check file permissions.', $iniPath)
            );
        }

        $existing = file_get_contents($iniPath);
        if ($existing === false) {
            throw new RuntimeException(sprintf('Failed to read php.ini at "%s".', $iniPath));
        }

        // Remove any previous debug-pilot Pcov block.
        $cleaned = $this->stripExistingBlock($existing);

        // Conflict resolution: disable Xdebug coverage mode if present.
        $cleaned = $this->neutraliseXdebugCoverage($cleaned);

        $block = $this->buildIniBlock();

        if (file_put_contents($iniPath, $cleaned . $block) === false) {
            throw new RuntimeException(sprintf('Failed to write to php.ini at "%s".', $iniPath));
        }

        return true;
    }

    public function verify(): HealthCheckResult
    {
        if (!$this->isInstalled()) {
            return HealthCheckResult::fail($this->getName(), [
                'Pcov extension is not loaded.',
            ]);
        }

        $messages = [];
        $passed = true;

        // Check enabled
        $enabled = ini_get('pcov.enabled');
        if ($enabled === '1' || $enabled === 'On') {
            $messages[] = '✅ pcov.enabled = 1';
        } else {
            $messages[] = "❌ pcov.enabled = {$enabled} (expected 1)";
            $passed = false;
        }

        // Warn if Xdebug coverage is still active
        if ($this->env->isExtensionLoaded('xdebug')) {
            $xdebugMode = ini_get('xdebug.mode') ?: 'off';
            if (str_contains($xdebugMode, 'coverage')) {
                $messages[] = "⚠️  Xdebug coverage mode is still active (xdebug.mode={$xdebugMode}). This may conflict with Pcov.";
                $passed = false;
            } else {
                $messages[] = '✅ Xdebug coverage mode is not active — no conflict.';
            }
        }

        return new HealthCheckResult($passed, $messages, $this->getName());
    }

    // -----------------------------------------------------------------
    //  Internal helpers
    // -----------------------------------------------------------------

    /**
     * Resolve the php.ini path — use Config value or fall back to auto-detect.
     */
    private function resolveIniPath(string $configPath): string
    {
        if ($configPath !== '') {
            return $configPath;
        }

        $detected = $this->env->findPhpIniPath();
        if ($detected === null) {
            throw new RuntimeException(
                'Could not auto-detect php.ini path. Please specify it manually.'
            );
        }

        return $detected;
    }

    /**
     * Build the INI directives block for Pcov.
     */
    private function buildIniBlock(): string
    {
        return <<<INI

        ; >>> PHP Debug Pilot — Pcov Configuration <<<
        [pcov]
        pcov.enabled   = 1
        pcov.directory = .
        ; Exclude vendor from coverage by default
        pcov.exclude   = /vendor/
        ; >>> End PHP Debug Pilot — Pcov <<<

        INI;
    }

    /**
     * Remove any previously written debug-pilot Pcov block from INI content.
     */
    private function stripExistingBlock(string $content): string
    {
        $pattern = '/\n?; >>> PHP Debug Pilot — Pcov Configuration <<<.*?; >>> End PHP Debug Pilot — Pcov <<<\n?/s';

        return preg_replace($pattern, '', $content) ?? $content;
    }

    /**
     * If Xdebug is configured with coverage mode, remove 'coverage' from xdebug.mode.
     *
     * For example: `xdebug.mode = debug,coverage` → `xdebug.mode = debug`
     */
    private function neutraliseXdebugCoverage(string $iniContent): string
    {
        return (string) preg_replace_callback(
            '/^(xdebug\.mode\s*=\s*)(.+)$/m',
            function (array $matches): string {
                $modes = array_map('trim', explode(',', $matches[2]));
                $modes = array_filter($modes, fn(string $m) => $m !== 'coverage');

                $newValue = empty($modes) ? 'off' : implode(',', $modes);

                return $matches[1] . $newValue;
            },
            $iniContent
        );
    }
}
