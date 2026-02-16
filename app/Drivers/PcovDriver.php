<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Config;
use App\Exceptions\PhpIniNotWritableException;
use App\Exceptions\PhpIniReadException;
use App\Exceptions\PhpIniWriteException;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use App\Support\IniEditor;

/**
 * Pcov driver for fast code-coverage collection.
 *
 * Pcov is a lightweight alternative to Xdebug's coverage mode.
 * When Pcov is activated this driver ensures Xdebug's coverage
 * mode is disabled to prevent conflicts.
 */
final class PcovDriver extends AbstractPhpExtensionDriver
{
    /** Regex pattern that matches the extension directive for pcov. */
    protected const EXTENSION_PATTERN = 'extension\s*=\s*["\']?(?:.*[/\\\\])?pcov(?:\.so|\.dll)?["\']?';

    /** Start marker for the Pcov configuration block. */
    protected const BLOCK_MARKER_START = '; >>> PHP Debug Pilot — Pcov Configuration <<<';

    /** End marker for the Pcov configuration block. */
    protected const BLOCK_MARKER_END = '; >>> End PHP Debug Pilot — Pcov <<<';

    public function __construct(
        EnvironmentDetector $env,
        IniEditor $iniEditor = new IniEditor(),
    ) {
        parent::__construct($env, $iniEditor);
    }

    public function getName(): string
    {
        return 'pcov';
    }

    /**
     * Override configure() to neutralize Xdebug coverage mode before calling parent.
     */
    public function configure(Config $config): bool
    {
        $iniPath = $this->resolveIniPath($config->phpIniPath);

        if (!is_writable($iniPath)) {
            throw PhpIniNotWritableException::forPath($iniPath);
        }

        $existing = file_get_contents($iniPath);
        if ($existing === false) {
            throw PhpIniReadException::forPath($iniPath);
        }

        // Conflict resolution: disable Xdebug coverage mode if present
        $cleaned = $this->neutraliseXdebugCoverage($existing);

        if (file_put_contents($iniPath, $cleaned) === false) {
            throw PhpIniWriteException::forPath($iniPath);
        }

        // Now call parent to write Pcov configuration
        return parent::configure($config);
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
    //  Protected methods (Template Method implementation)
    // -----------------------------------------------------------------

    /**
     * Build the INI directives block for Pcov.
     */
    protected function buildIniBlock(Config $config): string
    {
        $start = static::BLOCK_MARKER_START;
        $end = static::BLOCK_MARKER_END;

        return <<<INI

        {$start}
        [pcov]
        pcov.enabled   = 1
        pcov.directory = .
        ; Exclude vendor from coverage by default
        pcov.exclude   = /vendor/
        {$end}

        INI;
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

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
