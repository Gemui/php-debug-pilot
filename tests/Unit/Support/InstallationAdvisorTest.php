<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\EnvironmentDetector;
use App\Support\InstallationAdvisor;
use PHPUnit\Framework\TestCase;

final class InstallationAdvisorTest extends TestCase
{
    private InstallationAdvisor $advisor;
    private EnvironmentDetector $env;

    protected function setUp(): void
    {
        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
    }

    // -----------------------------------------------------------------
    //  getInstallCommand()
    // -----------------------------------------------------------------

    public function testGetInstallCommandReturnsNonEmptyString(): void
    {
        $cmd = $this->advisor->getInstallCommand('xdebug');

        $this->assertNotEmpty($cmd);
        $this->assertIsString($cmd);
    }

    public function testGetInstallCommandContainsExtensionName(): void
    {
        $cmd = $this->advisor->getInstallCommand('xdebug');
        $this->assertStringContainsString('xdebug', $cmd);

        $cmd = $this->advisor->getInstallCommand('pcov');
        $this->assertStringContainsString('pcov', $cmd);
    }

    public function testGetInstallCommandHandlesUnknownExtension(): void
    {
        $cmd = $this->advisor->getInstallCommand('some_custom_ext');

        $this->assertNotEmpty($cmd);
        $this->assertStringContainsString('some_custom_ext', $cmd);
    }

    public function testGetInstallCommandIsCaseInsensitive(): void
    {
        $lower = $this->advisor->getInstallCommand('xdebug');
        $upper = $this->advisor->getInstallCommand('XDEBUG');

        $this->assertSame($lower, $upper);
    }

    // -----------------------------------------------------------------
    //  getInstallInstructions()
    // -----------------------------------------------------------------

    public function testGetInstallInstructionsContainsExtensionName(): void
    {
        $instructions = $this->advisor->getInstallInstructions('xdebug');

        $this->assertStringContainsString('xdebug', $instructions);
    }

    public function testGetInstallInstructionsContainsPhpVersion(): void
    {
        $instructions = $this->advisor->getInstallInstructions('pcov');

        $this->assertStringContainsString(PHP_VERSION, $instructions);
    }

    public function testGetInstallInstructionsContainsOsName(): void
    {
        $instructions = $this->advisor->getInstallInstructions('xdebug');

        // Should mention the detected environment
        $this->assertMatchesRegularExpression('/(macOS|Linux|Windows|Docker)/', $instructions);
    }

    public function testGetInstallInstructionsContainsCommand(): void
    {
        $instructions = $this->advisor->getInstallInstructions('xdebug');
        $command = $this->advisor->getInstallCommand('xdebug');

        $this->assertStringContainsString($command, $instructions);
    }
}
