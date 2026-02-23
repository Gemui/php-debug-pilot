<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for IDE integrators (e.g., VS Code, Sublime).
 *
 * Each integrator handles generation of IDE-specific debug configuration files.
 */
interface IdeIntegrator
{
    /**
     * Get the unique identifier for this IDE.
     *
     * @return string e.g., 'vscode', 'sublime'
     */
    public function getName(): string;

    /**
     * Generate the IDE-specific debug configuration file(s).
     *
     * @param DebuggerDriver $debugger The active debugger driver.
     * @param string         $projectPath Absolute path to the project root.
     */
    public function generateConfig(DebuggerDriver $debugger, string $projectPath): void;
}
