<?php

declare(strict_types=1);

namespace App\Commands;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use App\DriverManager;
use App\Support\EnvironmentDetector;
use App\Support\InstallationAdvisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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
    public function __construct(
        private readonly DriverManager $driverManager,
        private readonly EnvironmentDetector $env,
        private readonly InstallationAdvisor $advisor,
    ) {
        parent::__construct('setup');
    }

    protected function configure(): void
    {
        $this
            ->addOption('project-path', 'p', InputOption::VALUE_REQUIRED, 'Project root path', getcwd())
            ->addOption('debugger', 'd', InputOption::VALUE_REQUIRED, 'Debugger name (skip prompt)')
            ->addOption('ide', 'i', InputOption::VALUE_REQUIRED, 'IDE name (skip prompt)')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Override client host', 'auto')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Override client port', '9003');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🚀 PHP Debug Pilot — Setup');

        // ---------------------------------------------------------------
        //  1. Environment detection
        // ---------------------------------------------------------------
        $this->printEnvironmentInfo($io);

        $projectPath = (string) $input->getOption('project-path');

        // ---------------------------------------------------------------
        //  2. Select debugger
        // ---------------------------------------------------------------
        $debugger = $this->selectDebugger($input, $io);
        if ($debugger === null) {
            return Command::FAILURE;
        }

        // Check if installed
        if (!$debugger->isInstalled()) {
            $io->warning("The '{$debugger->getName()}' extension is not installed.");
            $io->block($this->advisor->getInstallInstructions($debugger->getName()), null, 'fg=yellow');

            $proceed = $io->confirm('Continue anyway? (configuration will be written but may not take effect)', false);
            if (!$proceed) {
                $io->info('Setup cancelled. Install the extension first, then re-run.');
                return Command::SUCCESS;
            }
        }

        // ---------------------------------------------------------------
        //  3. Select IDE
        // ---------------------------------------------------------------
        $ide = $this->selectIde($input, $io, $projectPath);
        if ($ide === null) {
            return Command::FAILURE;
        }

        // ---------------------------------------------------------------
        //  4. Build Config & Execute
        // ---------------------------------------------------------------
        $config = $this->buildConfig($input);

        $io->section('Configuring ' . $debugger->getName() . '…');

        try {
            $debugger->configure($config);
            $io->success("✅ {$debugger->getName()} configuration written.");
        } catch (\Throwable $e) {
            $io->error("❌ Failed to configure {$debugger->getName()}: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $io->section('Generating ' . $ide->getName() . ' configuration…');

        try {
            $ide->generateConfig($debugger, $projectPath);
            $io->success("✅ {$ide->getName()} configuration created.");
        } catch (\Throwable $e) {
            $io->error("❌ Failed to generate {$ide->getName()} config: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // ---------------------------------------------------------------
        //  5. Health-check
        // ---------------------------------------------------------------
        $io->section('Running health-check…');
        $result = $debugger->verify();

        foreach ($result->messages as $msg) {
            $io->writeln("  {$msg}");
        }

        if ($result->passed) {
            $io->newLine();
            $io->success('🎉 All done! Your debug environment is ready.');
        } else {
            $io->newLine();
            $io->warning('Setup completed with warnings. Review the messages above.');
        }

        return Command::SUCCESS;
    }

    // -----------------------------------------------------------------
    //  Private helpers
    // -----------------------------------------------------------------

    private function printEnvironmentInfo(SymfonyStyle $io): void
    {
        $os = $this->env->getOs();
        $php = $this->env->getPhpVersion();
        $docker = $this->env->isDocker() ? 'Yes' : 'No';
        $iniPath = $this->env->findPhpIniPath() ?? '(not found)';
        $host = $this->env->getClientHost();

        $io->definitionList(
            ['OS' => $os],
            ['PHP' => $php],
            ['Docker' => $docker],
            ['php.ini' => $iniPath],
            ['Client Host' => $host],
        );
    }

    private function selectDebugger(InputInterface $input, SymfonyStyle $io): ?DebuggerDriver
    {
        $name = $input->getOption('debugger');

        if (is_string($name) && $name !== '') {
            try {
                return $this->driverManager->resolveDebugger($name);
            } catch (\InvalidArgumentException $e) {
                $io->error($e->getMessage());
                return null;
            }
        }

        $available = $this->driverManager->getAvailableDebuggers();
        if ($available === []) {
            $io->error('No debugger drivers registered.');
            return null;
        }

        $names = array_map(fn(DebuggerDriver $d) => $d->getName(), $available);

        $choice = $io->choice('Which debugger would you like to configure?', $names, $names[0]);

        return $this->driverManager->resolveDebugger($choice);
    }

    private function selectIde(InputInterface $input, SymfonyStyle $io, string $projectPath): ?IdeIntegrator
    {
        $name = $input->getOption('ide');

        if (is_string($name) && $name !== '') {
            try {
                return $this->driverManager->resolveIntegrator($name);
            } catch (\InvalidArgumentException $e) {
                $io->error($e->getMessage());
                return null;
            }
        }

        // Auto-detect
        $detected = $this->driverManager->detectIde($projectPath);
        if ($detected !== null) {
            $confirm = $io->confirm(
                "Detected IDE: <info>{$detected->getName()}</info>. Use this?",
                true
            );
            if ($confirm) {
                return $detected;
            }
        }

        // Manual selection
        $available = $this->driverManager->getAvailableIntegrators();
        if ($available === []) {
            $io->error('No IDE integrators registered.');
            return null;
        }

        $names = array_map(fn(IdeIntegrator $i) => $i->getName(), $available);
        $choice = $io->choice('Which IDE would you like to configure?', $names, $names[0]);

        return $this->driverManager->resolveIntegrator($choice);
    }

    private function buildConfig(InputInterface $input): Config
    {
        $iniPath = $this->env->findPhpIniPath() ?? '';
        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');

        return new Config(
            phpIniPath: $iniPath,
            clientHost: $host,
            clientPort: $port,
        );
    }
}
