<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Centralized OS, Docker, and PHP environment detection.
 *
 * Provides auto-discovery of php.ini paths, Docker container
 * detection, and OS-aware client host resolution. Injected into
 * drivers so they never need to detect the environment themselves.
 */
final class EnvironmentDetector
{
    public const OS_MACOS = 'macos';
    public const OS_LINUX = 'linux';
    public const OS_WINDOWS = 'windows';

    // -----------------------------------------------------------------
    //  OS Detection
    // -----------------------------------------------------------------

    /**
     * Detect the current operating system.
     *
     * @return string One of the OS_* constants.
     */
    public function getOs(): string
    {
        return match (true) {
            PHP_OS_FAMILY === 'Darwin' => self::OS_MACOS,
            PHP_OS_FAMILY === 'Windows' => self::OS_WINDOWS,
            default => self::OS_LINUX,
        };
    }

    // -----------------------------------------------------------------
    //  Docker Detection
    // -----------------------------------------------------------------

    /**
     * Detect whether the current process runs inside a Docker container.
     */
    public function isDocker(): bool
    {
        // File dropped by Docker into every container.
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // cgroup v1 / v2 fallback (Linux only).
        if (is_readable('/proc/1/cgroup')) {
            $cgroup = (string) file_get_contents('/proc/1/cgroup');

            return str_contains($cgroup, 'docker') || str_contains($cgroup, 'kubepods');
        }

        return false;
    }

    /**
     * Whether the current Docker container is based on an official PHP image.
     *
     * Official PHP Docker images ship with `docker-php-ext-enable` and `pecl`,
     * making it safe to auto-install extensions at runtime.
     */
    public function isOfficialPhpDockerImage(): bool
    {
        return $this->isDocker()
            && (is_executable('/usr/local/bin/docker-php-ext-enable')
                || is_executable('/usr/local/bin/pecl'));
    }

    // -----------------------------------------------------------------
    //  PHP INI Path
    // -----------------------------------------------------------------

    /**
     * Auto-locate the loaded php.ini file.
     *
     * Falls back to OS-specific common paths when `php_ini_loaded_file()`
     * returns nothing (e.g., embedded SAPIs).
     */
    public function findPhpIniPath(): ?string
    {
        // 1. Ask PHP itself (most reliable).
        $loaded = php_ini_loaded_file();
        if ($loaded !== false && $loaded !== '') {
            return $loaded;
        }

        // 2. Fallback: OS-specific common locations.
        $phpMajorMinor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        $candidates = match ($this->getOs()) {
            self::OS_MACOS => [
                "/opt/homebrew/etc/php/{$phpMajorMinor}/php.ini",
                "/usr/local/etc/php/{$phpMajorMinor}/php.ini",
            ],
            self::OS_WINDOWS => [
                'C:\\php\\php.ini',
                'C:\\xampp\\php\\php.ini',
            ],
            default => [ // Linux
                "/etc/php/{$phpMajorMinor}/cli/php.ini",
                "/etc/php/{$phpMajorMinor}/apache2/php.ini",
                '/etc/php.ini',
                '/usr/local/etc/php/php.ini', // Docker official images
            ],
        };

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    //  Client Host Resolution
    // -----------------------------------------------------------------

    /**
     * Determine the correct debug client host for the current environment.
     *
     * Inside Docker on macOS / Windows → `host.docker.internal`.
     * Inside Docker on Linux           → Docker gateway IP (`172.17.0.1`).
     * Outside Docker                   → `localhost`.
     */
    public function getClientHost(): string
    {
        if (!$this->isDocker()) {
            return 'localhost';
        }

        return match ($this->getOs()) {
            self::OS_LINUX => $this->getDockerGatewayIp(),
            default => 'host.docker.internal',
        };
    }

    // -----------------------------------------------------------------
    //  Misc Helpers
    // -----------------------------------------------------------------

    /**
     * Return the running PHP version string.
     */
    public function getPhpVersion(): string
    {
        return PHP_VERSION;
    }

    /**
     * Check if a PHP extension is loaded.
     */
    public function isExtensionLoaded(string $name): bool
    {
        return extension_loaded($name);
    }

    // -----------------------------------------------------------------
    //  Additional INI Files (conf.d)
    // -----------------------------------------------------------------

    /**
     * Get the list of additional .ini files that PHP scans (conf.d).
     *
     * Parses the output of php_ini_scanned_files() to discover files
     * like /etc/php/8.x/cli/conf.d/20-xdebug.ini.
     *
     * @return string[] Absolute paths to additional ini files.
     */
    public function getAdditionalIniFiles(): array
    {
        $scanned = php_ini_scanned_files();

        if ($scanned === false || $scanned === '') {
            return [];
        }

        // php_ini_scanned_files() returns a comma-separated list of file paths.
        $files = array_map('trim', explode(',', $scanned));

        return array_filter($files, fn(string $f) => $f !== '' && is_file($f));
    }

    /**
     * Check if a regex pattern matches content in any additional ini file.
     *
     * @param  string $pattern Regex pattern (without delimiters) to search for.
     * @return string|null     The path of the first matching file, or null.
     */
    public function findPatternInAdditionalIni(string $pattern): ?string
    {
        $regex = '#^\\s*;?\\s*' . $pattern . '#m';

        foreach ($this->getAdditionalIniFiles() as $file) {
            $content = @file_get_contents($file);
            if ($content !== false && preg_match($regex, $content)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Check if a regex pattern matches an uncommented (enabled) line
     * in any additional ini file.
     *
     * @param  string $pattern Regex pattern (without delimiters) to search for.
     * @return string|null     The path of the first matching file, or null.
     */
    public function findEnabledPatternInAdditionalIni(string $pattern): ?string
    {
        $regex = '#^\\s*(?!;)' . $pattern . '#m';

        foreach ($this->getAdditionalIniFiles() as $file) {
            $content = @file_get_contents($file);
            if ($content !== false && preg_match($regex, $content)) {
                return $file;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    //  Private
    // -----------------------------------------------------------------

    /**
     * Attempt to discover the Docker bridge gateway IP on Linux.
     */
    private function getDockerGatewayIp(): string
    {
        // Try reading from /proc/net/route (default gateway).
        if (is_readable('/proc/net/route')) {
            $lines = file('/proc/net/route', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $fields = preg_split('/\s+/', $line);
                    if ($fields !== false && isset($fields[1], $fields[2]) && $fields[1] === '00000000') {
                        $hex = $fields[2];
                        // Little-endian hex → IP
                        $ip = long2ip((int) hexdec(
                            $hex[6] . $hex[7] . $hex[4] . $hex[5] . $hex[2] . $hex[3] . $hex[0] . $hex[1]
                        ));

                        return $ip ?: '172.17.0.1';
                    }
                }
            }
        }

        return '172.17.0.1';
    }
}
