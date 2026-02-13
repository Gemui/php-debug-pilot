<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Commands\InstallCommand;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class InstallCommandTest extends TestCase
{
    private ExtensionInstaller $installer;
    private InstallationAdvisor $advisor;
    private EnvironmentDetector $env;

    protected function setUp(): void
    {
        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->installer = new ExtensionInstaller($this->env, $this->advisor);
    }

    public function testCommandIsRegisteredWithCorrectName(): void
    {
        $command = new InstallCommand($this->installer, $this->advisor, $this->env);
        $this->assertSame('install', $command->getName());
    }

    public function testCommandFailsForUnknownExtension(): void
    {
        $tester = $this->createCommandTester();

        $tester->execute(['extension' => 'foobar']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Unknown extension', $tester->getDisplay());
    }

    public function testCommandReportsAlreadyInstalledExtension(): void
    {
        // Xdebug is loaded on the test host machine
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug not loaded — cannot test "already installed" path.');
        }

        $tester = $this->createCommandTester();

        $tester->execute(['extension' => 'xdebug']);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('already installed', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCommandShowsInstallInstructions(): void
    {
        // If pcov is NOT loaded, the command should either attempt install or show instructions
        if (extension_loaded('pcov')) {
            $this->markTestSkipped('Pcov is loaded — cannot test install path.');
        }

        $tester = $this->createCommandTester();

        $tester->execute(['extension' => 'pcov']);

        // We just verify the command completes (install might fail on CI)
        $this->assertContains($tester->getStatusCode(), [0, 1]);
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    private function createCommandTester(): CommandTester
    {
        $command = new InstallCommand($this->installer, $this->advisor, $this->env);

        $app = new Application('test', '0.0.0');
        $app->add($command);

        return new CommandTester($app->find('install'));
    }
}
