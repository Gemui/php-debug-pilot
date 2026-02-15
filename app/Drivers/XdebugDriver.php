<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use App\Support\IniEditor;
use RuntimeException;

/**
 * Xdebug debugger driver.
 *
 * Detects, configures, and verifies the Xdebug PHP extension
 * for step-debugging with any supported IDE.
 */
final class XdebugDriver implements DebuggerDriver
{
    /** Regex pattern that matches the zend_extension directive for xdebug. */
    private const EXTENSION_PATTERN = 'zend_extension\s*=\s*["\']?(?:.*[\/\\\\])?xdebug(?:\.so|\.dll)?["\']?';

    public function __construct(
        private readonly EnvironmentDetector $env,
        private readonly IniEditor $iniEditor = new IniEditor(),
    ) {
    }

    public function getName(): string
    {
        return 'xdebug';
    }

    public function isInstalled(): bool
    {
        return $this->env->isExtensionLoaded('xdebug');
    }

    public function isEnabled(): bool
    {
        $iniPath = $this->env->findPhpIniPath();
        if ($iniPath === null || !is_file($iniPath)) {
            return false;
        }

        $content = file_get_contents($iniPath);
        if ($content === false) {
            return false;
        }

        return $this->iniEditor->isLineEnabled($content, self::EXTENSION_PATTERN);
    }

    public function hasIniDirective(): bool
    {
        $iniPath = $this->env->findPhpIniPath();
        if ($iniPath === null || !is_file($iniPath)) {
            return false;
        }

        $content = file_get_contents($iniPath);
        if ($content === false) {
            return false;
        }

        return $this->iniEditor->hasLine($content, self::EXTENSION_PATTERN);
    }

    public function setEnabled(bool $enabled): bool
    {
        $iniPath = $this->env->findPhpIniPath();
        if ($iniPath === null) {
            throw new RuntimeException('Could not auto-detect php.ini path.');
        }

        if (!is_writable($iniPath)) {
            throw new RuntimeException(
                sprintf('Cannot write to php.ini at "%s". Check file permissions.', $iniPath)
            );
        }

        $content = file_get_contents($iniPath);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read php.ini at "%s".', $iniPath));
        }

        if ($enabled) {
            if ($this->iniEditor->hasLine($content, self::EXTENSION_PATTERN)) {
                $content = $this->iniEditor->uncommentLine($content, self::EXTENSION_PATTERN);
            } else {
                $content = $this->iniEditor->appendLine($content, 'zend_extension=xdebug');
            }
        } else {
            $content = $this->iniEditor->commentLine($content, self::EXTENSION_PATTERN);
        }

        if (file_put_contents($iniPath, $content) === false) {
            throw new RuntimeException(sprintf('Failed to write to php.ini at "%s".', $iniPath));
        }

        return true;
    }

    /**
     * Write Xdebug configuration directives to php.ini.
     *
     * Appends a clearly marked block so it can be identified and
     * replaced on subsequent runs.
     */
    public function configure(Config $config): bool
    {
        $iniPath = $this->resolveIniPath($config->phpIniPath);

        if (!is_writable($iniPath)) {
            throw new RuntimeException(
                sprintf('Cannot write to php.ini at "%s". Check file permissions.', $iniPath)
            );
        }

        $clientHost = $this->resolveClientHost($config->clientHost);

        $block = $this->buildIniBlock($clientHost, $config->clientPort, $config->ideKey, $config->xdebugMode);

        $existing = file_get_contents($iniPath);
        if ($existing === false) {
            throw new RuntimeException(sprintf('Failed to read php.ini at "%s".', $iniPath));
        }

        // Remove any previous debug-pilot Xdebug block before appending.
        $cleaned = $this->stripExistingBlock($existing);

        if (file_put_contents($iniPath, $cleaned . $block) === false) {
            throw new RuntimeException(sprintf('Failed to write to php.ini at "%s".', $iniPath));
        }

        return true;
    }

    public function verify(): HealthCheckResult
    {
        if (!$this->isInstalled()) {
            return HealthCheckResult::fail($this->getName(), [
                'Xdebug extension is not loaded.',
            ]);
        }

        $iniPath = $this->env->findPhpIniPath();
        $iniContent = ($iniPath !== null && is_file($iniPath))
            ? (file_get_contents($iniPath) ?: '')
            : '';

        $messages = [];
        $passed = true;

        // Check mode
        $mode = $this->readIniDirective($iniContent, 'xdebug.mode') ?? 'off';
        if ($mode !== 'off') {
            $messages[] = "✅ xdebug.mode = {$mode}";
        } else {
            $messages[] = "❌ xdebug.mode is 'off' — no Xdebug features are active.";
            $passed = false;
        }

        // Check client host
        $host = $this->readIniDirective($iniContent, 'xdebug.client_host') ?? 'localhost';
        $messages[] = "ℹ️  xdebug.client_host = {$host}";

        // Check client port
        $port = $this->readIniDirective($iniContent, 'xdebug.client_port') ?? '9003';
        $messages[] = "ℹ️  xdebug.client_port = {$port}";

        // Check start_with_request
        $start = $this->readIniDirective($iniContent, 'xdebug.start_with_request') ?? 'default';
        if (in_array($start, ['yes', '1', 'On'], true)) {
            $messages[] = '✅ xdebug.start_with_request = yes';
        } else {
            $messages[] = "⚠️  xdebug.start_with_request = {$start} (recommend 'yes')";
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
     * Resolve the client host — delegate to EnvironmentDetector when set to 'auto'.
     */
    private function resolveClientHost(string $configHost): string
    {
        if ($configHost !== 'auto') {
            return $configHost;
        }

        return $this->env->getClientHost();
    }

    /**
     * Build the INI directives block.
     */
    private function buildIniBlock(string $host, int $port, string $ideKey, string $mode = 'debug'): string
    {
        return <<<INI

        ; >>> PHP Debug Pilot — Xdebug Configuration <<<
        [xdebug]
        xdebug.mode                = {$mode}
        xdebug.client_host         = {$host}
        xdebug.client_port         = {$port}
        xdebug.idekey              = {$ideKey}
        xdebug.start_with_request  = yes
        xdebug.discover_client_host = false
        ; >>> End PHP Debug Pilot — Xdebug <<<

        INI;
    }

    /**
     * Remove any previously written debug-pilot Xdebug block from INI content.
     */
    private function stripExistingBlock(string $content): string
    {
        $pattern = '/\n?; >>> PHP Debug Pilot — Xdebug Configuration <<<.*?; >>> End PHP Debug Pilot — Xdebug <<<\n?/s';

        return preg_replace($pattern, '', $content) ?? $content;
    }

    /**
     * Read a directive value from raw INI content.
     *
     * Returns the last uncommented occurrence so that the Debug Pilot
     * block (appended at the end) takes precedence over earlier values.
     */
    private function readIniDirective(string $iniContent, string $directive): ?string
    {
        $escaped = preg_quote($directive, '/');
        if (preg_match_all('/^\s*' . $escaped . '\s*=\s*(.+)$/m', $iniContent, $matches)) {
            return trim(end($matches[1]));
        }

        return null;
    }
}
