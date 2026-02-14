<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use App\DriverManager;
use App\Drivers\PcovDriver;
use App\Drivers\XdebugDriver;
use App\Integrators\PhpStormIntegrator;
use App\Integrators\SublimeIntegrator;
use App\Integrators\VsCodeIntegrator;
use App\Support\EnvironmentDetector;
use App\Support\ExtensionInstaller;
use App\Support\InstallationAdvisor;
use Illuminate\Support\ServiceProvider;

/**
 * Registers all application services into the IoC container.
 *
 * Replaces the manual DI wiring that was previously in bin/debug-pilot.
 */
class DebugPilotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ---------------------------------------------------------------
        //  Support services
        // ---------------------------------------------------------------

        $this->app->singleton(EnvironmentDetector::class, function () {
            return new EnvironmentDetector();
        });

        $this->app->singleton(InstallationAdvisor::class, function ($app) {
            return new InstallationAdvisor($app->make(EnvironmentDetector::class));
        });

        $this->app->singleton(ExtensionInstaller::class, function ($app) {
            return new ExtensionInstaller(
                $app->make(EnvironmentDetector::class),
                $app->make(InstallationAdvisor::class),
            );
        });

        // ---------------------------------------------------------------
        //  DriverManager with pre-registered drivers & integrators
        // ---------------------------------------------------------------

        $this->app->singleton(DriverManager::class, function ($app) {
            $env = $app->make(EnvironmentDetector::class);

            $manager = new DriverManager();

            $manager
                ->registerDebugger(new XdebugDriver($env))
                ->registerDebugger(new PcovDriver($env));

            $manager
                ->registerIntegrator(new VsCodeIntegrator())
                ->registerIntegrator(new PhpStormIntegrator())
                ->registerIntegrator(new SublimeIntegrator());

            return $manager;
        });
    }
}
