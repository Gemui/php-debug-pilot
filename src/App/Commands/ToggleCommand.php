<?php

declare(strict_types=1);

namespace App\Commands;

use App\DriverManager;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to toggle a debugger extension on or off.
 *
 * When enabling, checks if the extension is installed first and
 * offers to install it (mirroring the SetupCommand flow).
 *
 * Usage:
 *   php bin/debug-pilot toggle xdebug
 *   php bin/debug-pilot toggle pcov
 */
final class ToggleCommand extends Command
{
    public function __construct(
        private readonly DriverManager $driverManager,
        private readonly ExtensionInstaller $installer,
        private readonly InstallationAdvisor $advisor,
    ) {
        parent::__construct('toggle');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Enable or disable a debugger extension')
            ->addArgument('extension', InputArgument::REQUIRED, 'Extension name (e.g. xdebug, pcov)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = strtolower((string) $input->getArgument('extension'));

        // Resolve the driver
        try {
            $driver = $this->driverManager->resolveDebugger($name);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $wasEnabled = $driver->isEnabled();
        $newState = !$wasEnabled;

        // ---------------------------------------------------------------
        //  Guard: cannot enable an extension that is not installed
        // ---------------------------------------------------------------
        if ($newState && !$driver->isInstalled() && !$driver->hasIniDirective()) {
            $io->warning("The '{$name}' extension is not installed.");

            if ($this->installer->canAutoInstall()) {
                $install = $io->confirm('Would you like to install it now?', true);
                if ($install) {
                    $io->text('Running: <info>' . $this->advisor->getInstallCommand($name) . '</info>');
                    $result = $this->installer->install($name, function (string $line) use ($io): void {
                        $io->writeln("  │ {$line}");
                    });

                    if ($result->success) {
                        $io->success("✅ {$name} installed successfully.");
                    } else {
                        $io->error("❌ Installation failed (exit {$result->exitCode}).");
                        return Command::FAILURE;
                    }
                } else {
                    $io->info('Cannot enable an extension that is not installed. Install it first, then re-run.');
                    return Command::SUCCESS;
                }
            } else {
                $io->block($this->advisor->getInstallInstructions($name), null, 'fg=yellow');
                $io->info('Install the extension first, then re-run this command.');
                return Command::SUCCESS;
            }
        }

        $action = $newState ? 'Enabling' : 'Disabling';

        $io->section("{$action} {$name}…");

        try {
            $driver->setEnabled($newState);
        } catch (\Throwable $e) {
            $io->error("❌ Failed to {$action} {$name}: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $stateLabel = $newState ? 'enabled' : 'disabled';

        $io->success([
            "✅ {$name} is now {$stateLabel}.",
            '',
            '⚠️  Please restart your PHP process (php-fpm, Apache, or terminal)',
            'for the change to take effect.',
        ]);

        return Command::SUCCESS;
    }
}
