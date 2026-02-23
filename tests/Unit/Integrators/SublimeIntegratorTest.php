<?php

declare(strict_types=1);

namespace Tests\Unit\Integrators;

use App\Contracts\DebuggerDriver;
use App\Integrators\SublimeIntegrator;
use PHPUnit\Framework\TestCase;

final class SublimeIntegratorTest extends TestCase
{
    private SublimeIntegrator $integrator;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->integrator = new SublimeIntegrator();
        $this->tmpDir = sys_get_temp_dir() . '/sublime_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // -----------------------------------------------------------------
    //  Identity
    // -----------------------------------------------------------------

    public function testGetNameReturnsSublime(): void
    {
        $this->assertSame('sublime', $this->integrator->getName());
    }

    // -----------------------------------------------------------------
    //  generateConfig()
    // -----------------------------------------------------------------

    public function testGenerateConfigCreatesNewSublimeProject(): void
    {
        $debugger = $this->createMockDebugger('xdebug');

        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $dirName = basename($this->tmpDir);
        $file = $this->tmpDir . '/' . $dirName . '.sublime-project';

        $this->assertFileExists($file);

        $data = json_decode(file_get_contents($file), true);
        $this->assertArrayHasKey('folders', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('xdebug', $data['settings']);
        $this->assertSame(9003, $data['settings']['xdebug']['port']);
        $this->assertSame('xdebug', $data['settings']['xdebug']['debugger_engine']);
    }

    public function testGenerateConfigUpdatesExistingSublimeProject(): void
    {
        $existing = [
            'folders' => [['path' => '.']],
            'settings' => ['font_size' => 14],
        ];
        file_put_contents($this->tmpDir . '/myapp.sublime-project', json_encode($existing));

        $debugger = $this->createMockDebugger('xdebug');
        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $data = json_decode(file_get_contents($this->tmpDir . '/myapp.sublime-project'), true);

        // Original settings preserved.
        $this->assertSame(14, $data['settings']['font_size']);
        // Xdebug settings added.
        $this->assertArrayHasKey('xdebug', $data['settings']);
    }

    public function testGenerateConfigContainsPathMappings(): void
    {
        $debugger = $this->createMockDebugger('xdebug');
        $this->integrator->generateConfig($debugger, $this->tmpDir);

        $dirName = basename($this->tmpDir);
        $data = json_decode(file_get_contents($this->tmpDir . '/' . $dirName . '.sublime-project'), true);

        $this->assertArrayHasKey('path_mapping', $data['settings']['xdebug']);
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
