<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Provides OS-specific installation commands and human-readable
 * instructions for PHP debugger extensions.
 */
final readonly class InstallationAdvisor
{
    public function __construct(
        private EnvironmentDetector $env,
    ) {
    }

    /**
     * Get the shell command to install a PHP extension.
     *
     * @param string $extensionName e.g., 'xdebug', 'pcov'
     */
    public function getInstallCommand(string $extensionName): string
    {
        $ext = strtolower($extensionName);

        if ($this->env->isDocker()) {
            return $this->dockerCommand($ext);
        }

        return match ($this->env->getOs()) {
            EnvironmentDetector::OS_MACOS => $this->macCommand($ext),
            EnvironmentDetector::OS_WINDOWS => $this->windowsCommand($ext),
            default => $this->linuxCommand($ext),
        };
    }

    /**
     * Get the Dockerfile-style command (with RUN prefix) for use in
     * Dockerfiles and printed instructions.
     */
    public function getDockerfileCommand(string $extensionName): string
    {
        $ext = strtolower($extensionName);

        return match ($ext) {
            'xdebug' => 'RUN pecl install xdebug && docker-php-ext-enable xdebug',
            'pcov' => 'RUN pecl install pcov && docker-php-ext-enable pcov',
            default => "RUN pecl install {$ext} && docker-php-ext-enable {$ext}",
        };
    }

    /**
     * Get human-friendly, multi-line installation instructions.
     *
     * @param string $extensionName e.g., 'xdebug', 'pcov'
     */
    public function getInstallInstructions(string $extensionName): string
    {
        $ext = strtolower($extensionName);
        $os = $this->env->isDocker() ? 'Docker' : match ($this->env->getOs()) {
            EnvironmentDetector::OS_MACOS => 'macOS',
            EnvironmentDetector::OS_WINDOWS => 'Windows',
            default => 'Linux',
        };
        $php = $this->env->getPhpVersion();

        // Use Dockerfile-style command for instructions in Docker environments
        $command = $this->env->isDocker()
            ? $this->getDockerfileCommand($ext)
            : $this->getInstallCommand($ext);

        $lines = [
            "The '{$ext}' extension is not installed.",
            '',
            "Detected environment: {$os} (PHP {$php})",
            '',
            'Install it with:',
            "  {$command}",
            '',
            'After installing, restart your PHP process or web server.',
        ];

        return implode("\n", $lines);
    }

    // -----------------------------------------------------------------
    //  OS-specific command builders
    // -----------------------------------------------------------------

    private function macCommand(string $ext): string
    {
        return match ($ext) {
            'xdebug' => 'pecl install xdebug',
            'pcov' => 'pecl install pcov',
            default => "pecl install {$ext}",
        };
    }

    private function linuxCommand(string $ext): string
    {
        $packageManager = $this->detectLinuxPackageManager();

        return match ($packageManager) {
            'apt' => match ($ext) {
                    'xdebug' => 'sudo apt install -y php-xdebug',
                    'pcov' => 'sudo apt install -y php-pcov',
                    default => "sudo apt install -y php-{$ext}",
                },
            'yum', 'dnf' => match ($ext) {
                    'xdebug' => "sudo {$packageManager} install -y php-pecl-xdebug",
                    'pcov' => "sudo {$packageManager} install -y php-pecl-pcov",
                    default => "sudo {$packageManager} install -y php-pecl-{$ext}",
                },
            default => "pecl install {$ext}",
        };
    }

    private function windowsCommand(string $ext): string
    {
        return match ($ext) {
            'xdebug' => 'Download the correct DLL from https://xdebug.org/wizard and add it to php.ini',
            'pcov' => 'pecl install pcov',
            default => "pecl install {$ext}",
        };
    }

    private function dockerCommand(string $ext): string
    {
        return match ($ext) {
            'xdebug' => 'pecl install xdebug && docker-php-ext-enable xdebug',
            'pcov' => 'pecl install pcov && docker-php-ext-enable pcov',
            default => "pecl install {$ext} && docker-php-ext-enable {$ext}",
        };
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Detect which Linux package manager is available.
     */
    private function detectLinuxPackageManager(): string
    {
        foreach (['apt', 'dnf', 'yum'] as $pm) {
            $result = @exec("which {$pm} 2>/dev/null", $output, $code);
            if ($code === 0 && $result !== false && $result !== '') {
                return $pm;
            }
        }

        return 'pecl'; // fallback
    }
}
