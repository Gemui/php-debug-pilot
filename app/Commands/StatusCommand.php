<?php

declare(strict_types=1);

namespace App\Commands;

use App\DriverManager;
use LaravelZero\Framework\Commands\Command;

/**
 * CLI command that displays a table showing the current status
 * (installed / enabled) of all registered debugger extensions.
 *
 * Usage:
 *   php debug-pilot status
 */
final class StatusCommand extends Command
{
    protected $signature = 'status';

    protected $description = 'Show the current status of all registered debugger extensions';

    public function handle(DriverManager $driverManager): int
    {
        $this->info('');
        $this->info('🔍 PHP Debug Pilot — Extension Status');
        $this->info(str_repeat('=', 40));
        $this->newLine();

        $drivers = $driverManager->getAvailableDebuggers();

        if ($drivers === []) {
            $this->warn('No debugger drivers are registered.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($drivers as $driver) {
            $rows[] = [
                $driver->getName(),
                ($driver->isInstalled() || $driver->hasIniDirective()) ? '✅' : '❌',
                $driver->isEnabled() ? '✅' : '❌',
            ];
        }

        $this->table(
            ['Driver', 'Installed', 'Enabled'],
            $rows,
        );

        return self::SUCCESS;
    }
}
