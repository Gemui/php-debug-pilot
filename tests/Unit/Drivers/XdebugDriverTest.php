<?php

declare(strict_types=1);

namespace Tests\Unit\Drivers;

use App\Config;
use App\Drivers\XdebugDriver;
use App\Support\EnvironmentDetector;
use PHPUnit\Framework\TestCase;

final class XdebugDriverTest extends TestCase
{
    private XdebugDriver $driver;
    private EnvironmentDetector $env;
    private string $tmpIni;

    protected function setUp(): void
    {
        $this->env = new EnvironmentDetector();
        $this->driver = new XdebugDriver($this->env);
        $this->tmpIni = tempnam(sys_get_temp_dir(), 'xdebug_test_') ?: '/tmp/xdebug_test_fallback.ini';
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

    public function testGetNameReturnsXdebug(): void
    {
        $this->assertSame('xdebug', $this->driver->getName());
    }

    public function testIsInstalledReturnsBool(): void
    {
        $this->assertIsBool($this->driver->isInstalled());
    }

    // -----------------------------------------------------------------
    //  configure()
    // -----------------------------------------------------------------

    public function testConfigureWritesXdebugBlock(): void
    {
        file_put_contents($this->tmpIni, "; existing content\n");

        $config = new Config(
            phpIniPath: $this->tmpIni,
            clientHost: '192.168.1.100',
            clientPort: 9003,
            ideKey: 'VSCODE',
        );

        $result = $this->driver->configure($config);

        $this->assertTrue($result);

        $content = file_get_contents($this->tmpIni);
        $this->assertStringContainsString('xdebug.mode', $content);
        $this->assertStringContainsString('xdebug.client_host         = 192.168.1.100', $content);
        $this->assertStringContainsString('xdebug.client_port         = 9003', $content);
        $this->assertStringContainsString('xdebug.idekey              = VSCODE', $content);
        $this->assertStringContainsString('xdebug.start_with_request  = yes', $content);
        $this->assertStringContainsString('; existing content', $content);
    }

    public function testConfigureIsIdempotent(): void
    {
        file_put_contents($this->tmpIni, "; base\n");

        $config = new Config(phpIniPath: $this->tmpIni);

        $this->driver->configure($config);
        $this->driver->configure($config);

        $content = file_get_contents($this->tmpIni);

        // The marker should appear exactly once.
        $this->assertSame(
            1,
            substr_count($content, '; >>> PHP Debug Pilot — Xdebug Configuration <<<')
        );
    }

    public function testConfigureThrowsOnUnwritablePath(): void
    {
        $config = new Config(phpIniPath: '/nonexistent/path/php.ini');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to php.ini');

        $this->driver->configure($config);
    }

    public function testConfigureUsesAutoClientHost(): void
    {
        file_put_contents($this->tmpIni, '');

        $config = new Config(phpIniPath: $this->tmpIni, clientHost: 'auto');

        $this->driver->configure($config);

        $content = file_get_contents($this->tmpIni);
        $expectedHost = $this->env->getClientHost();

        $this->assertStringContainsString("xdebug.client_host         = {$expectedHost}", $content);
    }

    // -----------------------------------------------------------------
    //  verify()
    // -----------------------------------------------------------------

    public function testVerifyReturnsHealthCheckResult(): void
    {
        $result = $this->driver->verify();

        $this->assertSame('xdebug', $result->driverName);
        $this->assertIsBool($result->passed);
        $this->assertIsArray($result->messages);
        $this->assertNotEmpty($result->messages);
    }
}
