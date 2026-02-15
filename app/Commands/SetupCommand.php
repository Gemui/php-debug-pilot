<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use App\DriverManager;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\select;
use function Laravel\Prompts\multiselect;

/**
 * Interactive CLI command that configures a PHP debugger and
 * generates IDE-specific debug configuration files.
 *
 * Flow:
 *  1. Auto-detect environment (OS, Docker, php.ini).
 *  2. Prompt for debugger selection.
 *  3. Prompt for IDE selection (auto-detect or manual).
 *  4. Run debugger configure + IDE generateConfig.
 *  5. Run health-check and report results.
 */
final class SetupCommand extends Command
{
    protected $signature = 'setup
        {--p|project-path= : Project root path}
        {--d|debugger= : Debugger name (skip prompt)}
        {--i|ide= : IDE name (skip prompt)}
        {--host=auto : Override client host}
        {--port=9003 : Override client port}
        {--xdebug-mode= : Xdebug modes, comma-separated (debug,develop,coverage,profile,trace)}';

    protected $description = 'Interactive setup wizard for PHP debugging';

    public function handle(
        DriverManager $driverManager,
        EnvironmentDetector $env,
        InstallationAdvisor $advisor,
        ExtensionInstaller $installer,
    ): int {
        $this->info('');
        $this->info('🚀 PHP Debug Pilot — Setup');
        $this->info(str_repeat('=', 40));
        $this->newLine();

        // ---------------------------------------------------------------
        //  1. Environment detection
        // ---------------------------------------------------------------
        $this->printEnvironmentInfo($env);

        $projectPath = $this->option('project-path') ?: (string) getcwd();

        // ---------------------------------------------------------------
        //  2. Select debugger
        // ---------------------------------------------------------------
        $debugger = $this->selectDebugger($driverManager);
        if ($debugger === null) {
            return self::FAILURE;
        }

        // Check if installed — offer to install automatically
        $requiresRestart = false;
        if (!$debugger->isInstalled() && !$debugger->hasIniDirective()) {
            $this->warn("The '{$debugger->getName()}' extension is not installed.");

            if ($installer->canAutoInstall()) {
                $install = $this->confirm('Would you like to install it now?', true);
                if (!$install) {
                    $this->info('Setup cancelled. Install the extension first, then re-run.');
                    return self::FAILURE;
                }

                $this->line('Running: <info>' . $advisor->getInstallCommand($debugger->getName()) . '</info>');
                $result = $installer->install($debugger->getName(), function (string $line): void {
                    $this->line("  │ {$line}");
                });

                if ($result->success) {
                    $this->info("✅ {$debugger->getName()} installed successfully.");
                    $requiresRestart = true;
                } else {
                    $this->error("❌ Installation failed (exit {$result->exitCode}).");
                    $this->info('Please install the extension manually, then re-run setup.');
                    return self::FAILURE;
                }
            } else {
                // Docker / Windows — show instructions only
                $this->warn($advisor->getInstallInstructions($debugger->getName()));
                $this->info('Please install the extension first, then re-run setup.');
                return self::FAILURE;
            }
        }

        // Extension is installed but disabled — offer to enable it
        if (!$debugger->isInstalled() && $debugger->hasIniDirective()) {
            $this->warn("The '{$debugger->getName()}' extension is disabled.");

            $enable = $this->confirm('Would you like to enable it now?', true);
            if ($enable) {
                try {
                    $debugger->setEnabled(true);
                    $this->info("✅ {$debugger->getName()} enabled.");
                    $requiresRestart = true;
                } catch (\Throwable $e) {
                    $this->error("❌ Failed to enable {$debugger->getName()}: {$e->getMessage()}");
                }
            }
        }

        // ---------------------------------------------------------------
        //  3. Select IDE
        // ---------------------------------------------------------------
        $ide = $this->selectIde($driverManager, $projectPath);
        if ($ide === null) {
            return self::FAILURE;
        }

        // ---------------------------------------------------------------
        //  4. Build Config & Execute
        // ---------------------------------------------------------------
        $xdebugMode = $this->resolveXdebugMode($debugger);
        $config = $this->buildConfig($env, $xdebugMode);

        $this->newLine();
        $this->info('Configuring ' . $debugger->getName() . '…');

        try {
            $debugger->configure($config);
            $this->info("✅ {$debugger->getName()} configuration written.");
        } catch (\Throwable $e) {
            $this->error("❌ Failed to configure {$debugger->getName()}: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Generating ' . $ide->getName() . ' configuration…');

        try {
            $ide->generateConfig($debugger, $projectPath);
            $this->info("✅ {$ide->getName()} configuration created.");
        } catch (\Throwable $e) {
            $this->error("❌ Failed to generate {$ide->getName()} config: {$e->getMessage()}");
            return self::FAILURE;
        }

        // ---------------------------------------------------------------
        //  5. Health-check
        // ---------------------------------------------------------------
        $this->newLine();
        $this->info('Running health-check…');

        if ($requiresRestart) {
            $this->newLine();
            $this->info('🎉 All done! Configuration files have been written.');
            $this->newLine();
            $this->warn("⚠️  {$debugger->getName()} was just installed/enabled and requires a PHP restart.");
            $this->line('Please restart your PHP process (php-fpm, Apache, or terminal) and then run:');
            $this->newLine();
            $this->line('    php bin/debug-pilot');
            $this->newLine();
            $this->line('to verify your setup with a full health-check.');
        } else {
            $result = $debugger->verify();

            foreach ($result->messages as $msg) {
                $this->line("  {$msg}");
            }

            if ($result->passed) {
                $this->newLine();
                $this->info('🎉 All done! Your debug environment is ready.');
            } else {
                $this->newLine();
                $this->warn('Setup completed with warnings. Review the messages above.');
            }
        }

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

    private function printEnvironmentInfo(EnvironmentDetector $env): void
    {
        $os = $env->getOs();
        $php = $env->getPhpVersion();
        $docker = $env->isDocker() ? 'Yes' : 'No';
        $iniPath = $env->findPhpIniPath() ?? '(not found)';
        $host = $env->getClientHost();

        $this->table(
            ['Property', 'Value'],
            [
                ['OS', $os],
                ['PHP', $php],
                ['Docker', $docker],
                ['php.ini', $iniPath],
                ['Client Host', $host],
            ],
        );
    }

    private function selectDebugger(DriverManager $driverManager): ?DebuggerDriver
    {
        $name = $this->option('debugger');

        if (is_string($name) && $name !== '') {
            try {
                return $driverManager->resolveDebugger($name);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return null;
            }
        }

        $available = $driverManager->getAvailableDebuggers();
        if ($available === []) {
            $this->error('No debugger drivers registered.');
            return null;
        }

        $names = array_map(fn(DebuggerDriver $d) => $d->getName(), $available);

        $choice = select(
            label: 'Which debugger would you like to configure?',
            options: $names,
            default: $names[0],
        );

        return $driverManager->resolveDebugger($choice);
    }

    private function selectIde(DriverManager $driverManager, string $projectPath): ?IdeIntegrator
    {
        $name = $this->option('ide');

        if (is_string($name) && $name !== '') {
            try {
                return $driverManager->resolveIntegrator($name);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return null;
            }
        }

        // Auto-detect
        $detected = $driverManager->detectIde($projectPath);
        if ($detected !== null) {
            $confirm = $this->confirm(
                "Detected IDE: {$detected->getName()}. Use this?",
                true
            );
            if ($confirm) {
                return $detected;
            }
        }

        // Manual selection
        $available = $driverManager->getAvailableIntegrators();
        if ($available === []) {
            $this->error('No IDE integrators registered.');
            return null;
        }

        $names = array_map(fn(IdeIntegrator $i) => $i->getName(), $available);
        $choice = select(
            label: 'Which IDE would you like to configure?',
            options: $names,
            default: $names[0],
        );

        return $driverManager->resolveIntegrator($choice);
    }

    private function buildConfig(EnvironmentDetector $env, string $xdebugMode = 'debug'): Config
    {
        $iniPath = $env->findPhpIniPath() ?? '';
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');

        return new Config(
            phpIniPath: $iniPath,
            clientHost: $host,
            clientPort: $port,
            xdebugMode: $xdebugMode,
        );
    }

    /**
     * Resolve the xdebug mode — from CLI option, interactive prompt, or default.
     */
    private function resolveXdebugMode(DebuggerDriver $debugger): string
    {
        // Only relevant for xdebug
        if ($debugger->getName() !== 'xdebug') {
            return 'off';
        }

        // CLI option takes priority
        $option = $this->option('xdebug-mode');
        if (is_string($option) && $option !== '') {
            return $option;
        }

        // Read current xdebug.mode to pre-select existing configuration
        $availableModes = ['debug', 'develop', 'coverage', 'profile', 'trace'];
        $currentMode = ini_get('xdebug.mode') ?: '';
        $currentModes = array_filter(
            array_map('trim', explode(',', $currentMode)),
            fn(string $m) => in_array($m, $availableModes, true),
        );
        $defaultModes = !empty($currentModes) ? array_values($currentModes) : ['debug'];

        // Interactive prompt
        $modes = multiselect(
            label: 'Which Xdebug modes would you like to enable?',
            options: $availableModes,
            default: $defaultModes,
            required: true,
            hint: 'Use space to select, enter to confirm.',
        );

        return implode(',', $modes);
    }
}
