<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Config;
use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use App\DriverManager;
use App\HealthCheckResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DriverManagerTest extends TestCase
{
    private DriverManager $manager;

    protected function setUp(): void
    {
        $this->manager = new DriverManager();
    }

    // -----------------------------------------------------------------
    //  Registration & Resolution — Debuggers
    // -----------------------------------------------------------------

    public function testRegisterAndResolveDebugger(): void
    {
        $driver = $this->createMockDebugger('xdebug');

        $this->manager->registerDebugger($driver);

        $this->assertSame($driver, $this->manager->resolveDebugger('xdebug'));
    }

    public function testResolveUnknownDebuggerThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Debugger driver "unknown"');

        $this->manager->resolveDebugger('unknown');
    }

    // -----------------------------------------------------------------
    //  Registration & Resolution — Integrators
    // -----------------------------------------------------------------

    public function testRegisterAndResolveIntegrator(): void
    {
        $integrator = $this->createMockIntegrator('vscode');

        $this->manager->registerIntegrator($integrator);

        $this->assertSame($integrator, $this->manager->resolveIntegrator('vscode'));
    }

    public function testResolveUnknownIntegratorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('IDE integrator "unknown"');

        $this->manager->resolveIntegrator('unknown');
    }

    // -----------------------------------------------------------------
    //  Discovery
    // -----------------------------------------------------------------

    public function testGetAvailableDebuggersReturnsAll(): void
    {
        $xdebug = $this->createMockDebugger('xdebug');
        $pcov = $this->createMockDebugger('pcov');

        $this->manager->registerDebugger($xdebug);
        $this->manager->registerDebugger($pcov);

        $available = $this->manager->getAvailableDebuggers();

        $this->assertCount(2, $available);
        $this->assertSame($xdebug, $available[0]);
        $this->assertSame($pcov, $available[1]);
    }

    public function testGetAvailableIntegratorsReturnsAll(): void
    {
        $vscode = $this->createMockIntegrator('vscode');
        $phpstorm = $this->createMockIntegrator('phpstorm');

        $this->manager->registerIntegrator($vscode);
        $this->manager->registerIntegrator($phpstorm);

        $available = $this->manager->getAvailableIntegrators();

        $this->assertCount(2, $available);
        $this->assertSame($vscode, $available[0]);
        $this->assertSame($phpstorm, $available[1]);
    }

    public function testGetInstalledDebuggersFiltersCorrectly(): void
    {
        $installed = $this->createMockDebugger('xdebug', isInstalled: true);
        $notInstalled = $this->createMockDebugger('pcov', isInstalled: false, hasIniDirective: false);

        $this->manager->registerDebugger($installed);
        $this->manager->registerDebugger($notInstalled);

        $result = $this->manager->getInstalledDebuggers();

        $this->assertCount(1, $result);
        $this->assertSame($installed, $result[0]);
    }

    public function testGetInstalledDebuggersIncludesDisabledWithIniDirective(): void
    {
        $disabled = $this->createMockDebugger('xdebug', isInstalled: false, hasIniDirective: true);

        $this->manager->registerDebugger($disabled);

        $result = $this->manager->getInstalledDebuggers();

        $this->assertCount(1, $result);
        $this->assertSame($disabled, $result[0]);
    }

    // -----------------------------------------------------------------
    //  IDE Auto-Detection
    // -----------------------------------------------------------------

    public function testDetectIdeReturnsFirstDetected(): void
    {
        $vscode = $this->createMockIntegrator('vscode', isDetected: false);
        $phpstorm = $this->createMockIntegrator('phpstorm', isDetected: true);

        $this->manager->registerIntegrator($vscode);
        $this->manager->registerIntegrator($phpstorm);

        $result = $this->manager->detectIde('/some/project');

        $this->assertSame($phpstorm, $result);
    }

    public function testDetectIdeReturnsNullWhenNoneDetected(): void
    {
        $vscode = $this->createMockIntegrator('vscode', isDetected: false);
        $this->manager->registerIntegrator($vscode);

        $this->assertNull($this->manager->detectIde('/some/project'));
    }

    // -----------------------------------------------------------------
    //  Fluent API
    // -----------------------------------------------------------------

    public function testRegisterReturnsSelfForChaining(): void
    {
        $driver = $this->createMockDebugger('xdebug');
        $integrator = $this->createMockIntegrator('vscode');

        $result1 = $this->manager->registerDebugger($driver);
        $result2 = $this->manager->registerIntegrator($integrator);

        $this->assertSame($this->manager, $result1);
        $this->assertSame($this->manager, $result2);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createMockDebugger(string $name, bool $isInstalled = true, bool $hasIniDirective = true): DebuggerDriver
    {
        $mock = $this->createMock(DebuggerDriver::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isInstalled')->willReturn($isInstalled);
        $mock->method('hasIniDirective')->willReturn($hasIniDirective);

        return $mock;
    }

    private function createMockIntegrator(string $name, bool $isDetected = false): IdeIntegrator
    {
        $mock = $this->createMock(IdeIntegrator::class);
        $mock->method('getName')->willReturn($name);
        $mock->method('isDetected')->willReturn($isDetected);

        return $mock;
    }
}
