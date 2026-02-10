<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for IDE integrators (e.g., VS Code, PhpStorm, Sublime).
 *
 * Each integrator handles detection and generation of
 * IDE-specific debug configuration files.
 */
interface IdeIntegrator
{
    /**
     * Get the unique identifier for this IDE.
     *
     * @return string e.g., 'vscode', 'phpstorm', 'sublime'
     */
    public function getName(): string;

    /**
     * Detect whether this IDE is being used in the given project.
     *
     * @param string $projectPath Absolute path to the project root.
     */
    public function isDetected(string $projectPath): bool;

    /**
     * Generate the IDE-specific debug configuration file(s).
     *
     * @param DebuggerDriver $debugger The active debugger driver.
     * @param string         $projectPath Absolute path to the project root.
     */
    public function generateConfig(DebuggerDriver $debugger, string $projectPath): void;
}
