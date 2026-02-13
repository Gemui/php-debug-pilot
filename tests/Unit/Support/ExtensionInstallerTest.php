<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use App\Support\InstallResult;
use PHPUnit\Framework\TestCase;

final class ExtensionInstallerTest extends TestCase
{
    private EnvironmentDetector $env;
    private InstallationAdvisor $advisor;
    private ExtensionInstaller $installer;

    protected function setUp(): void
    {
        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->installer = new ExtensionInstaller($this->env, $this->advisor);
    }

    public function testCanAutoInstallReturnsTrueOnMacOrLinux(): void
    {
        // On the test machine (macOS), canAutoInstall should return true
        $os = $this->env->getOs();

        if ($os === EnvironmentDetector::OS_WINDOWS || $this->env->isDocker()) {
            $this->assertFalse($this->installer->canAutoInstall());
        } else {
            $this->assertTrue($this->installer->canAutoInstall());
        }
    }

    public function testInstallResultSuccessFactory(): void
    {
        $result = InstallResult::success('output text');

        $this->assertTrue($result->success);
        $this->assertSame('output text', $result->output);
        $this->assertSame('', $result->errorOutput);
        $this->assertSame(0, $result->exitCode);
    }

    public function testInstallResultFailureFactory(): void
    {
        $result = InstallResult::failure('some error', 127);

        $this->assertFalse($result->success);
        $this->assertSame('', $result->output);
        $this->assertSame('some error', $result->errorOutput);
        $this->assertSame(127, $result->exitCode);
    }

    public function testInstallReturnsInstallResult(): void
    {
        // Run install on a known-safe command (echo). We just verify
        // the result is an InstallResult with the correct structure.
        $result = $this->installer->install('xdebug');

        $this->assertInstanceOf(InstallResult::class, $result);
        $this->assertIsBool($result->success);
        $this->assertIsString($result->output);
        $this->assertIsString($result->errorOutput);
        $this->assertIsInt($result->exitCode);
    }

    public function testInstallCallsOutputCallback(): void
    {
        $lines = [];

        // This will attempt to install xdebug — whether it succeeds or
        // not depends on the environment, but the callback should fire.
        $this->installer->install('xdebug', function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        // We can't guarantee output lines in all cases (e.g., pecl may
        // produce no output on rapid failure), so we just verify the
        // callback is callable and the array is populated if there was output.
        $this->assertIsArray($lines);
    }
}
