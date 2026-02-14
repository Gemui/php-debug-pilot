<?php

declare(strict_types=1);

namespace App\Integrators;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;

/**
 * VS Code IDE integrator.
 *
 * Generates or merges a `.vscode/launch.json` with
 * a "Listen for Xdebug/Pcov" debug configuration.
 */
final class VsCodeIntegrator implements IdeIntegrator
{
    public function getName(): string
    {
        return 'vscode';
    }

    public function isDetected(string $projectPath): bool
    {
        return is_dir($projectPath . '/.vscode');
    }

    public function generateConfig(DebuggerDriver $debugger, string $projectPath): void
    {
        $vscodeDir = $projectPath . '/.vscode';
        $launchFile = $vscodeDir . '/launch.json';

        if (!is_dir($vscodeDir)) {
            mkdir($vscodeDir, 0755, true);
        }

        $newConfig = $this->buildDebugConfiguration($debugger);

        if (is_file($launchFile)) {
            $this->mergeIntoExisting($launchFile, $newConfig);
        } else {
            $this->writeNewLaunchJson($launchFile, $newConfig);
        }
    }

    // -----------------------------------------------------------------
    //  Internal
    // -----------------------------------------------------------------

    /**
     * Build a single VS Code debug configuration entry.
     *
     * @return array<string, mixed>
     */
    private function buildDebugConfiguration(DebuggerDriver $debugger): array
    {
        $name = ucfirst($debugger->getName());

        return [
            'name' => "Listen for {$name}",
            'type' => 'php',
            'request' => 'launch',
            'port' => 9003,
            'pathMappings' => [
                '/var/www/html' => '${workspaceFolder}',
            ],
            'hostname' => '0.0.0.0',
            'xdebugSettings' => [
                'max_data' => 65535,
                'show_hidden' => 1,
                'max_children' => 100,
            ],
        ];
    }

    /**
     * Merge a new configuration into an existing launch.json.
     *
     * Replaces any configuration with the same `name`, or appends.
     *
     * @param array<string, mixed> $newConfig
     */
    private function mergeIntoExisting(string $launchFile, array $newConfig): void
    {
        $raw = file_get_contents($launchFile);
        if ($raw === false) {
            $this->writeNewLaunchJson($launchFile, $newConfig);
            return;
        }

        // Strip single-line JS comments (// …) that VS Code allows but json_decode doesn't.
        $stripped = preg_replace('#^\s*//.*$#m', '', $raw) ?? $raw;

        /** @var array{version?: string, configurations?: list<array<string, mixed>>}|null $data */
        $data = json_decode($stripped, true);

        if (!is_array($data) || !isset($data['configurations'])) {
            $this->writeNewLaunchJson($launchFile, $newConfig);
            return;
        }

        // Replace existing config with same name, or append.
        $replaced = false;
        foreach ($data['configurations'] as $i => $cfg) {
            if (isset($cfg['name']) && $cfg['name'] === $newConfig['name']) {
                $data['configurations'][$i] = $newConfig;
                $replaced = true;
                break;
            }
        }

        if (!$replaced) {
            $data['configurations'][] = $newConfig;
        }

        file_put_contents(
            $launchFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    /**
     * Write a brand-new launch.json.
     *
     * @param array<string, mixed> $config
     */
    private function writeNewLaunchJson(string $launchFile, array $config): void
    {
        $data = [
            'version' => '0.2.0',
            'configurations' => [$config],
        ];

        file_put_contents(
            $launchFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }
}
