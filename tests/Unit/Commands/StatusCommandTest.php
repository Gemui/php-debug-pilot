<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Contracts\DebuggerDriver;
use App\DriverManager;
use Tests\TestCase;

final class StatusCommandTest extends TestCase
{
    private DriverManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new DriverManager();
        $this->app->instance(DriverManager::class, $this->manager);
    }

    public function testCommandDisplaysExtensionTable(): void
    {
        $this->manager->registerDebugger($this->createMockDebugger('xdebug', installed: true, enabled: true));
        $this->manager->registerDebugger($this->createMockDebugger('pcov', installed: true, enabled: false));

        $this->artisan('status')
            ->expectsOutputToContain('xdebug')
            ->expectsOutputToContain('pcov')
            ->assertSuccessful();
    }

    public function testCommandShowsWarningWhenNoDriversRegistered(): void
    {
        $this->artisan('status')
            ->expectsOutputToContain('No debugger drivers')
            ->assertSuccessful();
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createMockDebugger(string $name, bool $installed = true, bool $enabled = true): DebuggerDriver
    {
        $mock = $this->createMock(DebuggerDriver::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isInstalled')->willReturn($installed);
        $mock->method('isEnabled')->willReturn($enabled);

        return $mock;
    }
}
