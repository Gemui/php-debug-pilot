<?php

declare(strict_types=1);

namespace App\Drivers;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\Exceptions\PhpIniNotFoundException;
use App\Exceptions\PhpIniNotWritableException;
use App\Exceptions\PhpIniReadException;
use App\Exceptions\PhpIniWriteException;
use App\Support\EnvironmentDetector;
use App\Support\IniEditor;

/**
 * Abstract base class for PHP extension drivers.
 *
 * Provides common functionality for detecting, enabling, and configuring
 * PHP extensions. Child classes must define extension-specific patterns
 * and configuration blocks.
 *
 * Uses the Template Method pattern for configure() to eliminate
 * file I/O duplication across drivers.
 */
abstract class AbstractPhpExtensionDriver implements DebuggerDriver
{
    /**
     * Regex pattern to match the extension directive in php.ini.
     * Must be defined by child classes.
     */
    protected const EXTENSION_PATTERN = '';

    /**
     * Start marker for the configuration block in php.ini.
     * Must be defined by child classes.
     */
    protected const BLOCK_MARKER_START = '';

    /**
     * End marker for the configuration block in php.ini.
     * Must be defined by child classes.
     */
    protected const BLOCK_MARKER_END = '';

    public function __construct(
        protected readonly EnvironmentDetector $env,
        protected readonly IniEditor $iniEditor = new IniEditor(),
    ) {
    }

    public function isInstalled(): bool
    {
        return $this->env->isExtensionLoaded($this->getName());
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

        return $this->iniEditor->isLineEnabled($content, static::EXTENSION_PATTERN);
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

        return $this->iniEditor->hasLine($content, static::EXTENSION_PATTERN);
    }

    public function setEnabled(bool $enabled): bool
    {
        $iniPath = $this->env->findPhpIniPath();
        if ($iniPath === null) {
            throw PhpIniNotFoundException::create();
        }

        if (!is_writable($iniPath)) {
            throw PhpIniNotWritableException::forPath($iniPath);
        }

        $content = file_get_contents($iniPath);
        if ($content === false) {
            throw PhpIniReadException::forPath($iniPath);
        }

        if ($enabled) {
            if ($this->iniEditor->hasLine($content, static::EXTENSION_PATTERN)) {
                $content = $this->iniEditor->uncommentLine($content, static::EXTENSION_PATTERN);
            } else {
                $extensionName = $this->getName();
                $directive = $this->getExtensionDirectivePrefix() . $extensionName;
                $content = $this->iniEditor->appendLine($content, $directive);
            }
        } else {
            $content = $this->iniEditor->commentLine($content, static::EXTENSION_PATTERN);
        }

        if (file_put_contents($iniPath, $content) === false) {
            throw PhpIniWriteException::forPath($iniPath);
        }

        return true;
    }

    /**
     * Configure the extension by writing settings to php.ini.
     *
     * Template Method: handles all file I/O operations and delegates
     * the creation of the configuration block to buildIniBlock().
     *
     * Child classes can override this method to add pre/post processing
     * (e.g., Pcov neutralizes Xdebug coverage before calling parent).
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

        // Remove any previous configuration block
        $cleaned = $this->stripExistingBlock($existing);

        // Get driver-specific configuration block
        $block = $this->buildIniBlock($config);

        if (file_put_contents($iniPath, $cleaned . $block) === false) {
            throw PhpIniWriteException::forPath($iniPath);
        }

        return true;
    }

    // -----------------------------------------------------------------
    //  Abstract methods (must be implemented by child classes)
    // -----------------------------------------------------------------

    /**
     * Build the INI configuration block for this extension.
     *
     * @param Config $config Configuration settings
     * @return string The INI block to append to php.ini
     */
    abstract protected function buildIniBlock(Config $config): string;

    // -----------------------------------------------------------------
    //  Protected helpers (available to child classes)
    // -----------------------------------------------------------------

    /**
     * Resolve the php.ini path — use Config value or fall back to auto-detect.
     */
    protected function resolveIniPath(string $configPath): string
    {
        if ($configPath !== '') {
            return $configPath;
        }

        $detected = $this->env->findPhpIniPath();
        if ($detected === null) {
            throw PhpIniNotFoundException::create();
        }

        return $detected;
    }

    /**
     * Remove any previously written configuration block from INI content.
     */
    protected function stripExistingBlock(string $content): string
    {
        $start = preg_quote(static::BLOCK_MARKER_START, '/');
        $end = preg_quote(static::BLOCK_MARKER_END, '/');
        $pattern = "/\\n?{$start}.*?{$end}\\n?/s";

        return preg_replace($pattern, '', $content) ?? $content;
    }

    /**
     * Get the extension directive prefix (extension= or zend_extension=).
     *
     * Override in child classes if needed.
     */
    protected function getExtensionDirectivePrefix(): string
    {
        return 'extension=';
    }
}
