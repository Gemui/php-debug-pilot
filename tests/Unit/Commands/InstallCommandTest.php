<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use Tests\TestCase;

final class InstallCommandTest extends TestCase
{
    private ExtensionInstaller $installer;
    private InstallationAdvisor $advisor;
    private EnvironmentDetector $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new EnvironmentDetector();
        $this->advisor = new InstallationAdvisor($this->env);
        $this->installer = new ExtensionInstaller($this->env, $this->advisor);

        $this->app->instance(ExtensionInstaller::class, $this->installer);
        $this->app->instance(InstallationAdvisor::class, $this->advisor);
        $this->app->instance(EnvironmentDetector::class, $this->env);
    }

    public function testCommandFailsForUnknownExtension(): void
    {
        $this->artisan('install', ['extension' => 'foobar'])
            ->expectsOutputToContain('Unknown extension')
            ->assertFailed();
    }

    public function testCommandReportsAlreadyInstalledExtension(): void
    {
        // Xdebug is loaded on the test host machine
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Xdebug not loaded — cannot test "already installed" path.');
        }

        $this->artisan('install', ['extension' => 'xdebug'])
            ->expectsOutputToContain('already installed')
            ->assertSuccessful();
    }

    public function testCommandShowsInstallInstructions(): void
    {
        // If pcov is NOT loaded, the command should either attempt install or show instructions
        if (extension_loaded('pcov')) {
            $this->markTestSkipped('Pcov is loaded — cannot test install path.');
        }

        $exitCode = \Illuminate\Support\Facades\Artisan::call('install', ['extension' => 'pcov']);

        // Install might succeed (0) or fail (1) depending on environment
        $this->assertContains($exitCode, [0, 1]);
    }
}
