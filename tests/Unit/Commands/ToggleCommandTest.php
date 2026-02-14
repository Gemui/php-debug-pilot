<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\ToggleCommand;
use App\Contracts\DebuggerDriver;
use App\DriverManager;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class ToggleCommandTest extends TestCase
{
    private EnvironmentDetector $env;
    private InstallationAdvisor $advisor;
    private ExtensionInstaller $installer;

    protected function setUp(): void
    {
        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->installer = new ExtensionInstaller($this->env, $this->advisor);
    }

    public function testCommandIsRegisteredWithCorrectName(): void
    {
        $manager = new DriverManager();
        $command = new ToggleCommand($manager, $this->installer, $this->advisor);

        $this->assertSame('toggle', $command->getName());
    }

    public function testCommandEnablesDisabledButInstalledExtension(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(false);
        $driver->method('isInstalled')->willReturn(true);
        $driver->expects($this->once())->method('setEnabled')->with(true)->willReturn(true);

        $manager = new DriverManager();
        $manager->registerDebugger($driver);

        $tester = $this->createCommandTester($manager);
        $tester->execute(['extension' => 'xdebug']);

        $output = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('enabled', $output);
    }

    public function testCommandDisablesEnabledExtension(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(true);
        $driver->method('isInstalled')->willReturn(true);
        $driver->expects($this->once())->method('setEnabled')->with(false)->willReturn(true);

        $manager = new DriverManager();
        $manager->registerDebugger($driver);

        $tester = $this->createCommandTester($manager);
        $tester->execute(['extension' => 'xdebug']);

        $output = $tester->getDisplay();

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('disabled', $output);
    }

    public function testCommandBlocksEnableWhenNotInstalledAndUserDeclinesInstall(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(false);
        $driver->method('isInstalled')->willReturn(false);
        $driver->expects($this->never())->method('setEnabled');

        $manager = new DriverManager();
        $manager->registerDebugger($driver);

        $tester = $this->createCommandTester($manager);
        $tester->setInputs(['no']);
        $tester->execute(['extension' => 'xdebug']);

        $output = preg_replace('/\s+/', ' ', $tester->getDisplay());

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('not installed', $output);
    }

    public function testCommandFailsForUnknownExtension(): void
    {
        $manager = new DriverManager();

        $tester = $this->createCommandTester($manager);
        $tester->execute(['extension' => 'nonexistent']);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testCommandShowsErrorWhenToggleFails(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(true);
        $driver->method('isInstalled')->willReturn(true);
        $driver->method('setEnabled')->willThrowException(new \RuntimeException('Permission denied'));

        $manager = new DriverManager();
        $manager->registerDebugger($driver);

        $tester = $this->createCommandTester($manager);
        $tester->execute(['extension' => 'xdebug']);

        $output = preg_replace('/\s+/', ' ', $tester->getDisplay());

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Permission denied', $output);
    }

    public function testCommandRemindsUserToRestartPhp(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(true);
        $driver->method('isInstalled')->willReturn(true);
        $driver->method('setEnabled')->willReturn(true);

        $manager = new DriverManager();
        $manager->registerDebugger($driver);

        $tester = $this->createCommandTester($manager);
        $tester->execute(['extension' => 'xdebug']);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('restart', $output);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createCommandTester(DriverManager $manager): CommandTester
    {
        $command = new ToggleCommand($manager, $this->installer, $this->advisor);

        $app = new Application('test', '0.0.0');
        $app->add($command);

        return new CommandTester($app->find('toggle'));
    }
}
