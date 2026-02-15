<?php

declare(strict_types=1);

namespace App;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;
use InvalidArgumentException;

/**
 * Central registry that resolves debugger drivers and IDE integrators.
 *
 * Drivers / integrators are registered at bootstrap time and then
 * looked up by name throughout the application lifetime.
 */
final class DriverManager
{
    /** @var array<string, DebuggerDriver> */
    private array $debuggers = [];

    /** @var array<string, IdeIntegrator> */
    private array $integrators = [];

    // -----------------------------------------------------------------
    //  Registration
    // -----------------------------------------------------------------

    /**
     * Register a debugger driver.
     */
    public function registerDebugger(DebuggerDriver $driver): self
    {
        $this->debuggers[$driver->getName()] = $driver;

        return $this;
    }

    /**
     * Register an IDE integrator.
     */
    public function registerIntegrator(IdeIntegrator $integrator): self
    {
        $this->integrators[$integrator->getName()] = $integrator;

        return $this;
    }

    // -----------------------------------------------------------------
    //  Resolution
    // -----------------------------------------------------------------

    /**
     * Resolve a debugger driver by its name.
     *
     * @throws InvalidArgumentException If no driver with the given name is registered.
     */
    public function resolveDebugger(string $name): DebuggerDriver
    {
        return $this->debuggers[$name]
            ?? throw new InvalidArgumentException(
                sprintf('Debugger driver "%s" is not registered. Available: [%s]', $name, implode(', ', array_keys($this->debuggers)))
            );
    }

    /**
     * Resolve an IDE integrator by its name.
     *
     * @throws InvalidArgumentException If no integrator with the given name is registered.
     */
    public function resolveIntegrator(string $name): IdeIntegrator
    {
        return $this->integrators[$name]
            ?? throw new InvalidArgumentException(
                sprintf('IDE integrator "%s" is not registered. Available: [%s]', $name, implode(', ', array_keys($this->integrators)))
            );
    }

    // -----------------------------------------------------------------
    //  Discovery
    // -----------------------------------------------------------------

    /**
     * Return all registered debugger drivers.
     *
     * @return DebuggerDriver[]
     */
    public function getAvailableDebuggers(): array
    {
        return array_values($this->debuggers);
    }

    /**
     * Return all registered IDE integrators.
     *
     * @return IdeIntegrator[]
     */
    public function getAvailableIntegrators(): array
    {
        return array_values($this->integrators);
    }

    /**
     * Auto-detect the IDE used in the given project path.
     *
     * Iterates through all registered integrators and returns the
     * first one that reports itself as detected. Returns null if
     * no IDE could be identified.
     */
    public function detectIde(string $projectPath): ?IdeIntegrator
    {
        foreach ($this->integrators as $integrator) {
            if ($integrator->isDetected($projectPath)) {
                return $integrator;
            }
        }

        return null;
    }

    /**
     * Return only installed debugger drivers.
     *
     * @return DebuggerDriver[]
     */
    public function getInstalledDebuggers(): array
    {
        return array_values(
            array_filter($this->debuggers, fn(DebuggerDriver $d) => $d->isInstalled() || $d->hasIniDirective())
        );
    }
}
