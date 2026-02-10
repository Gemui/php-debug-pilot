<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\EnvironmentDetector;
use PHPUnit\Framework\TestCase;

final class EnvironmentDetectorTest extends TestCase
{
    private EnvironmentDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new EnvironmentDetector();
    }

    // -----------------------------------------------------------------
    //  OS Detection
    // -----------------------------------------------------------------

    public function testGetOsReturnsValidValue(): void
    {
        $os = $this->detector->getOs();

        $this->assertContains($os, [
            EnvironmentDetector::OS_MACOS,
            EnvironmentDetector::OS_LINUX,
            EnvironmentDetector::OS_WINDOWS,
        ]);
    }

    public function testGetOsMatchesCurrentPlatform(): void
    {
        $expected = match (PHP_OS_FAMILY) {
            'Darwin' => EnvironmentDetector::OS_MACOS,
            'Windows' => EnvironmentDetector::OS_WINDOWS,
            default => EnvironmentDetector::OS_LINUX,
        };

        $this->assertSame($expected, $this->detector->getOs());
    }

    // -----------------------------------------------------------------
    //  Docker Detection
    // -----------------------------------------------------------------

    public function testIsDockerReturnsBool(): void
    {
        $this->assertIsBool($this->detector->isDocker());
    }

    // -----------------------------------------------------------------
    //  PHP INI Path
    // -----------------------------------------------------------------

    public function testFindPhpIniPathReturnsStringOrNull(): void
    {
        $result = $this->detector->findPhpIniPath();

        if ($result !== null) {
            $this->assertFileExists($result);
            $this->assertStringEndsWith('.ini', $result);
        } else {
            $this->assertNull($result);
        }
    }

    public function testFindPhpIniPathMatchesPhpIniLoadedFile(): void
    {
        $loaded = php_ini_loaded_file();

        if ($loaded !== false && $loaded !== '') {
            // When PHP reports an ini, our detector should return the same.
            $this->assertSame($loaded, $this->detector->findPhpIniPath());
        } else {
            // If PHP can't find its own ini, we just accept whatever we get.
            $this->assertTrue(true);
        }
    }

    // -----------------------------------------------------------------
    //  Client Host
    // -----------------------------------------------------------------

    public function testGetClientHostReturnsNonEmptyString(): void
    {
        $host = $this->detector->getClientHost();

        $this->assertNotEmpty($host);
        $this->assertIsString($host);
    }

    public function testGetClientHostReturnsLocalhostOutsideDocker(): void
    {
        if ($this->detector->isDocker()) {
            $this->markTestSkipped('Running inside Docker — cannot test localhost fallback.');
        }

        $this->assertSame('localhost', $this->detector->getClientHost());
    }

    // -----------------------------------------------------------------
    //  PHP Version & Extensions
    // -----------------------------------------------------------------

    public function testGetPhpVersionMatchesConstant(): void
    {
        $this->assertSame(PHP_VERSION, $this->detector->getPhpVersion());
    }

    public function testIsExtensionLoadedForCoreExtension(): void
    {
        // 'Core' is always loaded.
        $this->assertTrue($this->detector->isExtensionLoaded('Core'));
    }

    public function testIsExtensionLoadedForFakeExtension(): void
    {
        $this->assertFalse($this->detector->isExtensionLoaded('nonexistent_extension_xyz'));
    }
}
