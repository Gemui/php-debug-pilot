<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Contracts\DebuggerDriver;
use App\DriverManager;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use Tests\TestCase;

final class ToggleCommandTest extends TestCase
{
    private DriverManager $manager;
    private EnvironmentDetector $env;
    private InstallationAdvisor $advisor;
    private ExtensionInstaller $installer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->installer = new ExtensionInstaller($this->env, $this->advisor);
        $this->manager = new DriverManager();

        $this->app->instance(DriverManager::class, $this->manager);
        $this->app->instance(ExtensionInstaller::class, $this->installer);
        $this->app->instance(InstallationAdvisor::class, $this->advisor);
    }

    public function testCommandEnablesDisabledButInstalledExtension(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(false);
        $driver->method('isInstalled')->willReturn(true);
        $driver->expects($this->once())->method('setEnabled')->with(true)->willReturn(true);

        $this->manager->registerDebugger($driver);

        $this->artisan('toggle', ['extension' => 'xdebug'])
            ->expectsOutputToContain('enabled')
            ->assertSuccessful();
    }

    public function testCommandDisablesEnabledExtension(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(true);
        $driver->method('isInstalled')->willReturn(true);
        $driver->expects($this->once())->method('setEnabled')->with(false)->willReturn(true);

        $this->manager->registerDebugger($driver);

        $this->artisan('toggle', ['extension' => 'xdebug'])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function testCommandBlocksEnableWhenNotInstalledAndUserDeclinesInstall(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(false);
        $driver->method('isInstalled')->willReturn(false);
        $driver->method('hasIniDirective')->willReturn(false);
        $driver->expects($this->never())->method('setEnabled');

        $this->manager->registerDebugger($driver);

        $this->artisan('toggle', ['extension' => 'xdebug'])
            ->expectsOutputToContain('not installed')
            ->expectsConfirmation('Would you like to install it now?', 'no')
            ->assertSuccessful();
    }

    public function testCommandFailsForUnknownExtension(): void
    {
        $this->artisan('toggle', ['extension' => 'nonexistent'])
            ->assertFailed();
    }

    public function testCommandShowsErrorWhenToggleFails(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(true);
        $driver->method('isInstalled')->willReturn(true);
        $driver->method('setEnabled')->willThrowException(new \RuntimeException('Permission denied'));

        $this->manager->registerDebugger($driver);

        $this->artisan('toggle', ['extension' => 'xdebug'])
            ->expectsOutputToContain('Permission denied')
            ->assertFailed();
    }

    public function testCommandRemindsUserToRestartPhp(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(true);
        $driver->method('isInstalled')->willReturn(true);
        $driver->method('setEnabled')->willReturn(true);

        $this->manager->registerDebugger($driver);

        $this->artisan('toggle', ['extension' => 'xdebug'])
            ->expectsOutputToContain('restart')
            ->assertSuccessful();
    }
}
