<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\StatusCommand;
use App\Contracts\DebuggerDriver;
use App\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class StatusCommandTest extends TestCase
{
    public function testCommandIsRegisteredWithCorrectName(): void
    {
        $manager = new DriverManager();
        $command = new StatusCommand($manager);

        $this->assertSame('status', $command->getName());
    }

    public function testCommandDisplaysExtensionTable(): void
    {
        $manager = new DriverManager();
        $manager->registerDebugger($this->createMockDebugger('xdebug', installed: true, enabled: true));
        $manager->registerDebugger($this->createMockDebugger('pcov', installed: true, enabled: false));

        $tester = $this->createCommandTester($manager);
        $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('xdebug', $output);
        $this->assertStringContainsString('pcov', $output);
        $this->assertStringContainsString('Driver', $output);
        $this->assertStringContainsString('Installed', $output);
        $this->assertStringContainsString('Enabled', $output);
    }

    public function testCommandShowsWarningWhenNoDriversRegistered(): void
    {
        $manager = new DriverManager();

        $tester = $this->createCommandTester($manager);
        $tester->execute([]);

        $output = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No debugger drivers', $output);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createCommandTester(DriverManager $manager): CommandTester
    {
        $command = new StatusCommand($manager);

        $app = new Application('test', '0.0.0');
        $app->add($command);

        return new CommandTester($app->find('status'));
    }

    private function createMockDebugger(string $name, bool $installed = true, bool $enabled = true): DebuggerDriver
    {
        $mock = $this->createMock(DebuggerDriver::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isInstalled')->willReturn($installed);
        $mock->method('isEnabled')->willReturn($enabled);

        return $mock;
    }
}
