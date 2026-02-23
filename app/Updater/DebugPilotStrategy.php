<?php

namespace App\Updater;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;

/**
 * Custom GitHub release strategy that sets the PHAR asset name to "debug-pilot"
 * before resolving the download URL.
 *
 * Laravel Zero's built-in Provider never calls setPharName(), which causes the
 * download URL to end with a bare slash instead of the actual filename.
 */
class DebugPilotStrategy extends GithubStrategy implements StrategyInterface
{
    public function getCurrentRemoteVersion(Updater $updater): mixed
    {
        $this->setPharName('debug-pilot');

        return parent::getCurrentRemoteVersion($updater);
    }
}
