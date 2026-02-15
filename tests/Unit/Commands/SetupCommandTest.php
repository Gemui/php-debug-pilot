<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use App\DriverManager;
use App\HealthCheckResult;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use Tests\TestCase;

final class SetupCommandTest extends TestCase
{
    private DriverManager $manager;
    private EnvironmentDetector $env;
    private InstallationAdvisor $advisor;
    private ExtensionInstaller $installer;
    private string $tmpDir;
    private string $tmpIni;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->installer = new ExtensionInstaller($this->env, $this->advisor);
        $this->manager = new DriverManager();

        $this->tmpDir = sys_get_temp_dir() . '/setup_cmd_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $this->tmpIni = tempnam(sys_get_temp_dir(), 'setup_ini_') ?: '/tmp/setup_ini_fallback.ini';
        file_put_contents($this->tmpIni, "; base php.ini\n");

        // Bind our custom instances into the container
        $this->app->instance(DriverManager::class, $this->manager);
        $this->app->instance(EnvironmentDetector::class, $this->env);
        $this->app->instance(InstallationAdvisor::class, $this->advisor);
        $this->app->instance(ExtensionInstaller::class, $this->installer);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);

        if (file_exists($this->tmpIni)) {
            unlink($this->tmpIni);
        }

        parent::tearDown();
    }

    // -----------------------------------------------------------------
    //  Tests
    // -----------------------------------------------------------------

    public function testCommandRunsWithPreSelectedDebuggerAndIde(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: true);
        $ide = $this->createMockIde('vscode');

        $this->manager->registerDebugger($debugger);
        $this->manager->registerIntegrator($ide);

        $this->artisan('setup', [
            '--debugger' => 'xdebug',
            '--ide' => 'vscode',
            '--project-path' => $this->tmpDir,
            '--host' => 'localhost',
            '--port' => '9003',
        ])->assertSuccessful();
    }

    public function testCommandShowsInstallHintWhenDebuggerNotInstalled(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: false, hasIniDirective: false);
        $ide = $this->createMockIde('vscode');

        $this->manager->registerDebugger($debugger);
        $this->manager->registerIntegrator($ide);

        // On macOS/Linux canAutoInstall() returns true, so it asks "Would you like to install it now?"
        // Answer "no" to skip install, then it continues to configure
        $this->artisan('setup', [
            '--debugger' => 'xdebug',
            '--ide' => 'vscode',
            '--project-path' => $this->tmpDir,
        ])->expectsConfirmation('Would you like to install it now?', 'no')
            ->assertSuccessful();
    }

    public function testCommandEnablesDisabledExtensionInsteadOfInstalling(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: false, hasIniDirective: true);

        // Should NOT call verify() because restart is required
        $debugger->expects($this->never())->method('verify');

        $ide = $this->createMockIde('vscode');

        $this->manager->registerDebugger($debugger);
        $this->manager->registerIntegrator($ide);

        $this->artisan('setup', [
            '--debugger' => 'xdebug',
            '--ide' => 'vscode',
            '--project-path' => $this->tmpDir,
            '--host' => 'localhost',
            '--port' => '9003',
        ])->expectsOutputToContain('disabled')
            ->expectsConfirmation('Would you like to enable it now?', 'yes')
            ->expectsOutputToContain('requires a PHP restart')
            ->assertSuccessful();
    }

    public function testCommandFailsForUnknownDebugger(): void
    {
        $this->artisan('setup', [
            '--debugger' => 'nonexistent',
            '--project-path' => $this->tmpDir,
        ])->assertFailed();
    }

    public function testCommandFailsForUnknownIde(): void
    {
        $debugger = $this->createMockDebugger('xdebug', installed: true);
        $this->manager->registerDebugger($debugger);

        $this->artisan('setup', [
            '--debugger' => 'xdebug',
            '--ide' => 'nonexistent',
            '--project-path' => $this->tmpDir,
        ])->assertFailed();
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createMockDebugger(string $name, bool $installed = true, bool $hasIniDirective = true): DebuggerDriver
    {
        $mock = $this->createMock(DebuggerDriver::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isInstalled')->willReturn($installed);
        $mock->method('hasIniDirective')->willReturn($hasIniDirective);
        $mock->method('configure')->willReturn(true);
        $mock->method('setEnabled')->willReturn(true);
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
