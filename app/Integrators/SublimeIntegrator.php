<?php

declare(strict_types=1);

namespace App\Integrators;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;

/**
 * Sublime Text IDE integrator.
 *
 * Creates or updates a `*.sublime-project` file with
 * Creates or updates a `*.sublime-project` file with Xdebug debug settings.
 */
final class SublimeIntegrator implements IdeIntegrator
{
    public function getName(): string
    {
        return 'sublime';
    }

    public function generateConfig(DebuggerDriver $debugger, string $projectPath): void
    {
        $projectFile = $this->findOrCreateProjectFile($projectPath);

        $data = $this->loadProjectFile($projectFile);

        $data['settings'] = $data['settings'] ?? [];

        $data['settings']['xdebug'] = $this->buildXdebugSettings($debugger);

        file_put_contents(
            $projectFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
        );
    }

    // -----------------------------------------------------------------
    //  Internal
    // -----------------------------------------------------------------

    /**
     * Find an existing .sublime-project file, or create a new one
     * named after the project directory.
     */
    private function findOrCreateProjectFile(string $projectPath): string
    {
        $existing = glob($projectPath . '/*.sublime-project');

        if ($existing !== false && $existing !== []) {
            return $existing[0];
        }

        $dirName = basename($projectPath);

        return $projectPath . '/' . $dirName . '.sublime-project';
    }

    /**
     * Load and decode an existing project file, or return a skeleton.
     *
     * @return array<string, mixed>
     */
    private function loadProjectFile(string $path): array
    {
        if (!is_file($path)) {
            return [
                'folders' => [
                    ['path' => '.'],
                ],
            ];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['folders' => [['path' => '.']]];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : ['folders' => [['path' => '.']]];
    }

    /**
     * Build the xdebug settings block for Sublime.
     *
     * @return array<string, mixed>
     */
    private function buildXdebugSettings(DebuggerDriver $debugger): array
    {
        return [
            'url' => 'http://localhost',
            'ide_key' => 'sublime.xdebug',
            'port' => 9003,
            'super_globals' => true,
            'close_on_stop' => true,
            'debug' => true,
            'debugger_engine' => $debugger->getName(),
            'path_mapping' => [],
        ];
    }
}
