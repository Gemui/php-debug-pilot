<?php

declare(strict_types=1);

namespace App\Commands;

use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Standalone command to install a PHP extension.
 *
 * Usage:
 *   php bin/debug-pilot install xdebug
 *   php bin/debug-pilot install pcov
 */
final class InstallCommand extends Command
{
    private const KNOWN_EXTENSIONS = ['xdebug', 'pcov'];

    public function __construct(
        private readonly ExtensionInstaller $installer,
        private readonly InstallationAdvisor $advisor,
        private readonly EnvironmentDetector $env,
    ) {
        parent::__construct('install');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Install a PHP debugger extension')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name (xdebug, pcov)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ext = strtolower((string) $input->getArgument('extension'));

        if (!in_array($ext, self::KNOWN_EXTENSIONS, true)) {
            $io->error("Unknown extension '{$ext}'. Supported: " . implode(', ', self::KNOWN_EXTENSIONS));
            return Command::FAILURE;
        }

        // Already installed?
        if ($this->env->isExtensionLoaded($ext)) {
            $io->success("The '{$ext}' extension is already installed and loaded.");
            return Command::SUCCESS;
        }

        // Can we auto-install?
        if (!$this->installer->canAutoInstall()) {
            $io->warning("Auto-installation is not available in this environment.");
            $io->block($this->advisor->getInstallInstructions($ext), null, 'fg=yellow');
            return Command::SUCCESS;
        }

        $io->section("Installing {$ext}…");
        $io->text("Running: <info>{$this->advisor->getInstallCommand($ext)}</info>");
        $io->newLine();

        $result = $this->installer->install($ext, function (string $line) use ($io): void {
            $io->writeln("  │ {$line}");
        });

        if ($result->success) {
            $io->newLine();
            $io->success("✅ {$ext} installed successfully.");
            return Command::SUCCESS;
        }

        $io->newLine();
        $io->error("❌ Installation failed (exit code {$result->exitCode}).");
        if ($result->errorOutput !== '') {
            $io->block($result->errorOutput, 'STDERR', 'fg=red');
        }

        return Command::FAILURE;
    }
}
