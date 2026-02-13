<?php

declare(strict_types=1);

namespace Tests\Unit\Integrators;

use App\Contracts\DebuggerDriver;
use App\Integrators\PhpStormIntegrator;
use PHPUnit\Framework\TestCase;

final class PhpStormIntegratorTest extends TestCase
{
    private PhpStormIntegrator $integrator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->integrator = new PhpStormIntegrator();
        $this->tmpDir = sys_get_temp_dir() . '/phpstorm_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // -----------------------------------------------------------------
    //  Identity & Detection
    // -----------------------------------------------------------------

    public function testGetNameReturnsPhpstorm(): void
    {
        $this->assertSame('phpstorm', $this->integrator->getName());
    }

    public function testIsDetectedReturnsTrueWhenIdeaDirExists(): void
    {
        mkdir($this->tmpDir . '/.idea', 0755);

        $this->assertTrue($this->integrator->isDetected($this->tmpDir));
    }

    public function testIsDetectedReturnsFalseWhenNoIdeaDir(): void
    {
        $this->assertFalse($this->integrator->isDetected($this->tmpDir));
    }

    // -----------------------------------------------------------------
    //  generateConfig()
    // -----------------------------------------------------------------

    public function testGenerateConfigCreatesXmlFile(): void
    {
        $debugger = $this->createMockDebugger('xdebug');

        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $file = $this->tmpDir . '/.idea/runConfigurations/PHP_Debug_Pilot_Xdebug.xml';
        $this->assertFileExists($file);

        $xml = file_get_contents($file);
        $this->assertStringContainsString('PhpRemoteDebugConfigurationType', $xml);
        $this->assertStringContainsString('Debug Pilot — Xdebug', $xml);
        $this->assertStringContainsString('PHPSTORM', $xml);
        $this->assertStringContainsString('DebugPilot', $xml);
    }

    public function testGenerateConfigCreatesServerDefinition(): void
    {
        $debugger = $this->createMockDebugger('xdebug');

        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $phpXml = $this->tmpDir . '/.idea/php.xml';
        $this->assertFileExists($phpXml);

        $xml = file_get_contents($phpXml);
        $this->assertStringContainsString('/var/www/html', $xml);
        $this->assertStringContainsString('DebugPilot', $xml);
    }

    public function testGenerateConfigCreatesValidXml(): void
    {
        $debugger = $this->createMockDebugger('xdebug');
        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $file = $this->tmpDir . '/.idea/runConfigurations/PHP_Debug_Pilot_Xdebug.xml';

        $doc = new \DOMDocument();
        $loaded = $doc->loadXML(file_get_contents($file));

        $this->assertTrue($loaded, 'Generated XML should be valid.');
    }

    public function testGenerateConfigCreatesRunConfigurationsDir(): void
    {
        $debugger = $this->createMockDebugger('xdebug');

        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $this->assertDirectoryExists($this->tmpDir . '/.idea/runConfigurations');
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createMockDebugger(string $name): DebuggerDriver
    {
        $mock = $this->createMock(DebuggerDriver::class);
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
