<?php

declare(strict_types=1);

namespace App\Integrators;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;

/**
 * PhpStorm IDE integrator.
 *
 * Generates an XML run-configuration file under `.idea/runConfigurations/`
 * for "PHP Remote Debug" sessions.
 */
final class PhpStormIntegrator implements IdeIntegrator
{
    public function getName(): string
    {
        return 'phpstorm';
    }

    public function isDetected(string $projectPath): bool
    {
        return is_dir($projectPath . '/.idea');
    }

    public function generateConfig(DebuggerDriver $debugger, string $projectPath): void
    {
        $configDir = $projectPath . '/.idea/runConfigurations';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $fileName = 'PHP_Debug_Pilot_' . ucfirst($debugger->getName()) . '.xml';
        $filePath = $configDir . '/' . $fileName;

        $xml = $this->buildXmlConfig($debugger);

        file_put_contents($filePath, $xml);
    }

    // -----------------------------------------------------------------
    //  Internal
    // -----------------------------------------------------------------

    /**
     * Build the PhpStorm XML run-configuration for PHP Remote Debug.
     */
    private function buildXmlConfig(DebuggerDriver $debugger): string
    {
        $name = 'Debug Pilot — ' . ucfirst($debugger->getName());
        $ideKey = 'PHPSTORM';

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <component name="ProjectRunConfigurationManager">
          <configuration default="false" name="{$name}" type="PhpRemoteDebugConfigurationType" factoryName="PHP Remote Debug">
            <option name="serverName" value="Docker" />
            <option name="ide_key" value="{$ideKey}" />
            <option name="path_mappings">
              <mapping local-root="\$PROJECT_DIR\$" remote-root="/var/www/html" />
            </option>
            <method v="2" />
          </configuration>
        </component>

        XML;
    }
}
