<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Executes OS-appropriate shell commands to install PHP extensions.
 *
 * Delegates command resolution to InstallationAdvisor and runs
 * the command via proc_open, capturing stdout/stderr.
 */
final readonly class ExtensionInstaller
{
    public function __construct(
        private EnvironmentDetector $env,
        private InstallationAdvisor $advisor,
    ) {
    }

    /**
     * Whether auto-installation is possible in the current environment.
     *
     * Docker and Windows environments require manual steps, so we
     * return false and fall back to printed instructions.
     */
    public function canAutoInstall(): bool
    {
        if ($this->env->isDocker() && !$this->env->isOfficialPhpDockerImage()) {
            return false;
        }

        return $this->env->getOs() !== EnvironmentDetector::OS_WINDOWS;
    }

    /**
     * Run the OS-appropriate install command for the given extension.
     *
     * @param string                                  $extensionName e.g. 'xdebug', 'pcov'
     * @param callable(string $line): void|null       $onOutput      Real-time output callback
     */
    public function install(string $extensionName, ?callable $onOutput = null): InstallResult
    {
        if (!$this->canAutoInstall()) {
            return InstallResult::failure(
                'Auto-install is not supported in this environment (Docker or Windows). '
                . 'Please follow the manual instructions.',
                126,
            );
        }

        $command = $this->advisor->getInstallCommand(strtolower($extensionName));

        return $this->execute($command, $onOutput);
    }

    /**
     * Execute a shell command and capture its output.
     *
     * @param callable(string $line): void|null $onOutput
     */
    private function execute(string $command, ?callable $onOutput = null): InstallResult
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return InstallResult::failure("Failed to execute: {$command}");
        }

        // Close stdin immediately — we don't send input.
        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';

        // Read stdout line by line for real-time feedback.
        while (($line = fgets($pipes[1])) !== false) {
            $stdout .= $line;
            if ($onOutput !== null) {
                $onOutput(rtrim($line, "\n\r"));
            }
        }

        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return InstallResult::failure(
                $stderr !== '' ? $stderr : $stdout,
                $exitCode,
            );
        }

        return InstallResult::success($stdout);
    }
}
