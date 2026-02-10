<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\SetupCommand;
use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use App\DriverManager;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use App\Support\InstallationAdvisor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class SetupCommandTest extends TestCase
{
    private DriverManager $manager;
    private EnvironmentDetector $env;
    private InstallationAdvisor $advisor;
    private string $tmpDir;
    private string $tmpIni;

    protected function setUp(): void
    {
        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->manager = new DriverManager();

        $this->tmpDir = sys_get_temp_dir() . '/setup_cmd_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->tmpIni = tempnam(sys_get_temp_dir(), 'setup_ini_') ?: '/tmp/setup_ini_fallback.ini';
        file_put_contents($this->tmpIni, "; base php.ini\n");
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);

        if (file_exists($this->tmpIni)) {
            unlink($this->tmpIni);
        }
    }

    // -----------------------------------------------------------------
    //  Tests
    // -----------------------------------------------------------------

    public function testCommandIsRegisteredWithCorrectName(): void
    {
        $command = $this->createCommand();

        $this->assertSame('setup', $command->getName());
    }

    public function testCommandRunsWithPreSelectedDebuggerAndIde(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: true);
        $ide = $this->createMockIde('vscode');

        $this->manager->registerDebugger($debugger);
        $this->manager->registerIntegrator($ide);

        $tester = $this->createCommandTester();

        $tester->execute([
            '--debugger' => 'xdebug',
            '--ide' => 'vscode',
            '--project-path' => $this->tmpDir,
            '--host' => 'localhost',
            '--port' => '9003',
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('PHP Debug Pilot', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCommandShowsInstallHintWhenDebuggerNotInstalled(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: false);
        $ide = $this->createMockIde('vscode');

        $this->manager->registerDebugger($debugger);
        $this->manager->registerIntegrator($ide);

        $tester = $this->createCommandTester();

        // Answer "no" to "Continue anyway?"
        $tester->setInputs(['no']);

        $tester->execute([
            '--debugger' => 'xdebug',
            '--ide' => 'vscode',
            '--project-path' => $this->tmpDir,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('not installed', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCommandFailsForUnknownDebugger(): void
    {
        $tester = $this->createCommandTester();

        $tester->execute([
            '--debugger' => 'nonexistent',
            '--project-path' => $this->tmpDir,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testCommandFailsForUnknownIde(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: true);
        $this->manager->registerDebugger($debugger);

        $tester = $this->createCommandTester();

        $tester->execute([
            '--debugger' => 'xdebug',
            '--ide' => 'nonexistent',
            '--project-path' => $this->tmpDir,
        ]);

        $this->assertSame(1, $tester->getStatusCode());
    }

    public function testCommandDisplaysEnvironmentInfo(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: true);
        $ide = $this->createMockIde('vscode');

        $this->manager->registerDebugger($debugger);
        $this->manager->registerIntegrator($ide);

        $tester = $this->createCommandTester();

        $tester->execute([
            '--debugger' => 'xdebug',
            '--ide' => 'vscode',
            '--project-path' => $this->tmpDir,
        ]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString(PHP_VERSION, $output);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createCommand(): SetupCommand
    {
        return new SetupCommand($this->manager, $this->env, $this->advisor);
    }

    private function createCommandTester(): CommandTester
    {
        $app = new Application('test', '0.0.0');
        $app->add($this->createCommand());
        $app->setDefaultCommand('setup', true);

        $command = $app->find('setup');

        return new CommandTester($command);
    }

    private function createMockDebugger(string $name, bool $installed = true): DebuggerDriver
    {
        $mock = $this->createMock(DebuggerDriver::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isInstalled')->willReturn($installed);
        $mock->method('configure')->willReturn(true);
        $mock->method('verify')->willReturn(
            HealthCheckResult::pass($name, ['✅ All good.'])
        );

        return $mock;
    }

    private function createMockIde(string $name): IdeIntegrator
    {
        $mock = $this->createMock(IdeIntegrator::class);
        $mock->method('getName')->willReturn($name);

        return $mock;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
