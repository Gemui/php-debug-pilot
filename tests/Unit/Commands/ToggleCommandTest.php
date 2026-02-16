<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Contracts\DebuggerDriver;
use App\DriverManager;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstallationService;
use App\Support\ExtensionReadyResult;
use Tests\TestCase;

final class ToggleCommandTest extends TestCase
{
    private DriverManager $manager;
    private EnvironmentDetector $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new EnvironmentDetector();
        $this->manager = new DriverManager();

        $this->app->instance(DriverManager::class, $this->manager);
        $this->app->instance(EnvironmentDetector::class, $this->env);

        // Mock ExtensionInstallationService to always return success
        $mockService = $this->createMock(ExtensionInstallationService::class);
        $mockService->method('ensureExtensionReady')
            ->willReturn(ExtensionReadyResult::success());
        $this->app->instance(ExtensionInstallationService::class, $mockService);
    }

    public function testCommandEnablesDisabledButInstalledExtension(): void
    {
        $driver = $this->createMock(DebuggerDriver::class);
        $driver->method('getName')->willReturn('xdebug');
        $driver->method('isEnabled')->willReturn(false);
        $driver->method('isInstalled')->willReturn(true);

        $this->manager->registerDebugger($driver);

        // Mock service to return success (extension is already installed)
        $mockService = $this->createMock(ExtensionInstallationService::class);
        $mockService->method('ensureExtensionReady')
            ->willReturn(ExtensionReadyResult::success());
        $this->app->instance(ExtensionInstallationService::class, $mockService);

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

        $this->manager->registerDebugger($driver);

        // Mock service to return failure when user declines
        $mockService = $this->createMock(ExtensionInstallationService::class);
        $mockService->method('ensureExtensionReady')
            ->willReturn(ExtensionReadyResult::failure('Cannot proceed without installing xdebug.'));
        $this->app->instance(ExtensionInstallationService::class, $mockService);

        $this->artisan('toggle', ['extension' => 'xdebug'])
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
