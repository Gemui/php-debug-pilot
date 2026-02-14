<?php

declare(strict_types=1);

namespace App\Integrators;

use App\Contracts\DebuggerDriver;
use App\Contracts\IdeIntegrator;

/**
 * PhpStorm IDE integrator.
 *
 * Generates an XML run-configuration file under `.idea/runConfigurations/`
 * for "PHP Remote Debug" sessions, plus a server definition in `.idea/php.xml`.
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

    // Also generate a PHP server definition so PhpStorm knows
    // about path mappings without manual configuration.
    $this->ensureServerConfig($projectPath);
  }

  // -----------------------------------------------------------------
  //  Internal
  // -----------------------------------------------------------------

  /**
   * Build the PhpStorm XML run-configuration for Xdebug listening.
   *
   * Uses the fully-qualified type ID that modern PhpStorm versions
   * (2021.3+) expect, rather than the legacy short name.
   */
  private function buildXmlConfig(DebuggerDriver $debugger): string
  {
    $name = 'Debug Pilot — ' . ucfirst($debugger->getName());
    $ideKey = 'PHPSTORM';
    $server = 'DebugPilot';

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<component name="ProjectRunConfigurationManager">
  <configuration default="false" name="{$name}" type="PhpRemoteDebug" factoryName="PHP Remote Debug">
    <option name="serverName" value="{$server}" />
    <option name="ide_key" value="{$ideKey}" />
    <method v="2" />
  </configuration>
</component>

XML;
  }

  /**
   * Write a PHP server definition to `.idea/php.xml` so PhpStorm
   * can resolve path mappings automatically.
   */
  private function ensureServerConfig(string $projectPath): void
  {
    $phpXml = $projectPath . '/.idea/php.xml';

    // Only write if the file doesn't already exist — avoids
    // clobbering a user-customised server config.
    if (file_exists($phpXml)) {
      return;
    }

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<project version="4">
  <component name="PhpProjectServersManager">
    <servers>
      <server host="localhost" id="debug-pilot" name="DebugPilot" port="80" use_path_mappings="true">
        <path_mappings>
          <mapping local-root="\$PROJECT_DIR\$" remote-root="/var/www/html" />
        </path_mappings>
      </server>
    </servers>
  </component>
</project>

XML;

    file_put_contents($phpXml, $xml);
  }
}
