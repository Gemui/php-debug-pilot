<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use RuntimeException;

/**
 * Xdebug debugger driver.
 *
 * Detects, configures, and verifies the Xdebug PHP extension
 * for step-debugging with any supported IDE.
 */
final class XdebugDriver implements DebuggerDriver
{
    public function __construct(
        private readonly EnvironmentDetector $env,
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

        $block = $this->buildIniBlock($clientHost, $config->clientPort, $config->ideKey);

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

        $messages = [];
        $passed = true;

        // Check mode
        $mode = ini_get('xdebug.mode') ?: 'off';
        if (str_contains($mode, 'debug')) {
            $messages[] = "✅ xdebug.mode includes 'debug' (current: {$mode}).";
        } else {
            $messages[] = "❌ xdebug.mode does not include 'debug' (current: {$mode}).";
            $passed = false;
        }

        // Check client host
        $host = ini_get('xdebug.client_host') ?: 'localhost';
        $messages[] = "ℹ️  xdebug.client_host = {$host}";

        // Check client port
        $port = ini_get('xdebug.client_port') ?: '9003';
        $messages[] = "ℹ️  xdebug.client_port = {$port}";

        // Check start_with_request
        $start = ini_get('xdebug.start_with_request') ?: 'default';
        if ($start === 'yes') {
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
    private function buildIniBlock(string $host, int $port, string $ideKey): string
    {
        return <<<INI

        ; >>> PHP Debug Pilot — Xdebug Configuration <<<
        [xdebug]
        xdebug.mode                = debug
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
}
