<?php

declare(strict_types=1);

namespace Tests\Unit\Integrators;

use App\Contracts\DebuggerDriver;
use App\Integrators\VsCodeIntegrator;
use PHPUnit\Framework\TestCase;

final class VsCodeIntegratorTest extends TestCase
{
    private VsCodeIntegrator $integrator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->integrator = new VsCodeIntegrator();
        $this->tmpDir = sys_get_temp_dir() . '/vscode_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // -----------------------------------------------------------------
    //  Identity & Detection
    // -----------------------------------------------------------------

    public function testGetNameReturnsVscode(): void
    {
        $this->assertSame('vscode', $this->integrator->getName());
    }

    public function testIsDetectedReturnsTrueWhenVscodeDirExists(): void
    {
        mkdir($this->tmpDir . '/.vscode', 0755);

        $this->assertTrue($this->integrator->isDetected($this->tmpDir));
    }

    public function testIsDetectedReturnsFalseWhenNoVscodeDir(): void
    {
        $this->assertFalse($this->integrator->isDetected($this->tmpDir));
    }

    // -----------------------------------------------------------------
    //  generateConfig()
    // -----------------------------------------------------------------

    public function testGenerateConfigCreatesLaunchJson(): void
    {
        $debugger = $this->createMockDebugger('xdebug');

        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $file = $this->tmpDir . '/.vscode/launch.json';
        $this->assertFileExists($file);

        $data = json_decode(file_get_contents($file), true);
        $this->assertSame('0.2.0', $data['version']);
        $this->assertCount(1, $data['configurations']);
        $this->assertSame('Listen for Xdebug', $data['configurations'][0]['name']);
        $this->assertSame(9003, $data['configurations'][0]['port']);
    }

    public function testGenerateConfigMergesIntoExistingLaunchJson(): void
    {
        // Create a pre-existing launch.json with a different config.
        $vscodeDir = $this->tmpDir . '/.vscode';
        mkdir($vscodeDir, 0755, true);

        $existing = [
            'version' => '0.2.0',
            'configurations' => [
                ['name' => 'My Custom Config', 'type' => 'node', 'request' => 'launch'],
            ],
        ];
        file_put_contents($vscodeDir . '/launch.json', json_encode($existing, JSON_PRETTY_PRINT));

        $debugger = $this->createMockDebugger('xdebug');
        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $data = json_decode(file_get_contents($vscodeDir . '/launch.json'), true);

        // Should now have 2 configs: the existing one + the new one.
        $this->assertCount(2, $data['configurations']);
        $this->assertSame('My Custom Config', $data['configurations'][0]['name']);
        $this->assertSame('Listen for Xdebug', $data['configurations'][1]['name']);
    }

    public function testGenerateConfigReplacesExistingConfigWithSameName(): void
    {
        $vscodeDir = $this->tmpDir . '/.vscode';
        mkdir($vscodeDir, 0755, true);

        $debugger = $this->createMockDebugger('xdebug');

        // Run twice — should NOT create duplicate entries.
        $this->integrator->generateConfig($debugger, $this->tmpDir);
        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $data = json_decode(file_get_contents($vscodeDir . '/launch.json'), true);
        $this->assertCount(1, $data['configurations']);
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
