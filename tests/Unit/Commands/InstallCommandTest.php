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

    public function testCommandFailsForUnknownExtensionMessage(): void
    {
        $this->artisan('install', ['extension' => 'pcov'])
            ->expectsOutputToContain('Unknown extension')
            ->assertFailed();
    }
}
