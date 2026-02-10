<?php

declare(strict_types=1);

namespace Tests\Unit\Drivers;

use App\Config;
use App\Drivers\PcovDriver;
use App\Support\EnvironmentDetector;
use PHPUnit\Framework\TestCase;

final class PcovDriverTest extends TestCase
{
    private PcovDriver $driver;
    private string $tmpIni;

    protected function setUp(): void
    {
        $env = new EnvironmentDetector();
        $this->driver = new PcovDriver($env);
        $this->tmpIni = tempnam(sys_get_temp_dir(), 'pcov_test_') ?: '/tmp/pcov_test_fallback.ini';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpIni)) {
            unlink($this->tmpIni);
        }
    }

    // -----------------------------------------------------------------
    //  Identity
    // -----------------------------------------------------------------

    public function testGetNameReturnsPcov(): void
    {
        $this->assertSame('pcov', $this->driver->getName());
    }

    public function testIsInstalledReturnsBool(): void
    {
        $this->assertIsBool($this->driver->isInstalled());
    }

    // -----------------------------------------------------------------
    //  configure()
    // -----------------------------------------------------------------

    public function testConfigureWritesPcovBlock(): void
    {
        file_put_contents($this->tmpIni, "; existing\n");

        $config = new Config(phpIniPath: $this->tmpIni);

        $result = $this->driver->configure($config);

        $this->assertTrue($result);

        $content = file_get_contents($this->tmpIni);
        $this->assertStringContainsString('pcov.enabled   = 1', $content);
        $this->assertStringContainsString('pcov.directory = .', $content);
        $this->assertStringContainsString('; existing', $content);
    }

    public function testConfigureIsIdempotent(): void
    {
        file_put_contents($this->tmpIni, "; base\n");

        $config = new Config(phpIniPath: $this->tmpIni);

        $this->driver->configure($config);
        $this->driver->configure($config);

        $content = file_get_contents($this->tmpIni);

        $this->assertSame(
            1,
            substr_count($content, '; >>> PHP Debug Pilot — Pcov Configuration <<<')
        );
    }

    public function testConfigureDisablesXdebugCoverageMode(): void
    {
        // Simulate an INI that has Xdebug coverage mode enabled.
        file_put_contents($this->tmpIni, "xdebug.mode = debug,coverage\n");

        $config = new Config(phpIniPath: $this->tmpIni);
        $this->driver->configure($config);

        $content = file_get_contents($this->tmpIni);

        // 'coverage' should have been stripped from the xdebug.mode line.
        $this->assertMatchesRegularExpression('/^xdebug\.mode\s*=\s*debug$/m', $content);
        // Ensure the xdebug.mode line does NOT contain 'coverage'.
        $this->assertDoesNotMatchRegularExpression('/^xdebug\.mode\s*=.*coverage/m', $content);
    }

    public function testConfigureSetsXdebugModeToOffWhenOnlyCoverage(): void
    {
        file_put_contents($this->tmpIni, "xdebug.mode = coverage\n");

        $config = new Config(phpIniPath: $this->tmpIni);
        $this->driver->configure($config);

        $content = file_get_contents($this->tmpIni);
        $this->assertStringContainsString('xdebug.mode = off', $content);
    }

    public function testConfigureThrowsOnUnwritablePath(): void
    {
        $config = new Config(phpIniPath: '/nonexistent/path/php.ini');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to php.ini');

        $this->driver->configure($config);
    }

    // -----------------------------------------------------------------
    //  verify()
    // -----------------------------------------------------------------

    public function testVerifyReturnsHealthCheckResult(): void
    {
        $result = $this->driver->verify();

        $this->assertSame('pcov', $result->driverName);
        $this->assertIsBool($result->passed);
        $this->assertIsArray($result->messages);
        $this->assertNotEmpty($result->messages);
    }
}
