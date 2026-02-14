<?php

declare(strict_types=1);

namespace App\Commands;

use App\DriverManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command that displays a table showing the current status
 * (installed / enabled) of all registered debugger extensions.
 *
 * Usage:
 *   php bin/debug-pilot status
 */
final class StatusCommand extends Command
{
    public function __construct(
        private readonly DriverManager $driverManager,
    ) {
        parent::__construct('status');
    }

    protected function configure(): void
    {
        $this->setDescription('Show the current status of all registered debugger extensions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔍 PHP Debug Pilot — Extension Status');

        $drivers = $this->driverManager->getAvailableDebuggers();

        if ($drivers === []) {
            $io->warning('No debugger drivers are registered.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($drivers as $driver) {
            $rows[] = [
                $driver->getName(),
                ($driver->isInstalled() || $driver->hasIniDirective()) ? '✅' : '❌',
                $driver->isEnabled() ? '✅' : '❌',
            ];
        }

        $io->table(
            ['Driver', 'Installed', 'Enabled'],
            $rows,
        );

        return Command::SUCCESS;
    }
}
