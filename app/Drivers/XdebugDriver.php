<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Config;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use App\Support\IniEditor;

/**
 * Xdebug debugger driver.
 *
 * Detects, configures, and verifies the Xdebug PHP extension
 * for step-debugging with any supported IDE.
 */
final class XdebugDriver extends AbstractPhpExtensionDriver
{
    /** Regex pattern that matches the zend_extension directive for xdebug. */
    protected const EXTENSION_PATTERN = 'zend_extension\s*=\s*["\']?(?:.*[/\\\\])?xdebug(?:\.so|\.dll)?["\']?';

    /** Start marker for the Xdebug configuration block. */
    protected const BLOCK_MARKER_START = '; >>> PHP Debug Pilot — Xdebug Configuration <<<';

    /** End marker for the Xdebug configuration block. */
    protected const BLOCK_MARKER_END = '; >>> End PHP Debug Pilot — Xdebug <<<';

    public function __construct(
        EnvironmentDetector $env,
        IniEditor $iniEditor = new IniEditor(),
    ) {
        parent::__construct($env, $iniEditor);
    }

    public function getName(): string
    {
        return 'xdebug';
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
    //  Protected methods (Template Method implementation)
    // -----------------------------------------------------------------

    /**
     * Build the INI directives block for Xdebug.
     */
    protected function buildIniBlock(Config $config): string
    {
        $host = $this->resolveClientHost($config->clientHost);
        $port = $config->clientPort;
        $ideKey = $config->ideKey;
        $mode = $config->xdebugMode;

        $start = static::BLOCK_MARKER_START;
        $end = static::BLOCK_MARKER_END;

        return <<<INI

        {$start}
        [xdebug]
        xdebug.mode                = {$mode}
        xdebug.client_host         = {$host}
        xdebug.client_port         = {$port}
        xdebug.idekey              = {$ideKey}
        xdebug.start_with_request  = yes
        xdebug.discover_client_host = false
        {$end}

        INI;
    }

    /**
     * Override to use zend_extension instead of extension.
     */
    protected function getExtensionDirectivePrefix(): string
    {
        return 'zend_extension=';
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

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
