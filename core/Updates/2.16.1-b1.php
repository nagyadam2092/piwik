<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Updates;

use Piwik\Config;
use Piwik\Plugin;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;

/**
 * Update for version 2.16.1-b1.
 */
class Updates_2_16_1_b1 extends PiwikUpdates
{
    private $marketplacEnabledConfigSetting = 'enable_marketplace';

    public function doUpdate(Updater $updater)
    {
        $isMarketplaceEnabled = Config::getInstance()->General[$this->marketplacEnabledConfigSetting];

        $this->removeOldMarketplaceEnabledConfig();

        $pluginManager = Plugin\Manager::getInstance();
        $pluginName = 'Marketplace';

        if ($isMarketplaceEnabled &&
            !$pluginManager->getInstance()->isPluginActivated($pluginName)) {
            $pluginManager->activatePlugin($pluginName);
        }
    }

    private function removeOldMarketplaceEnabledConfig()
    {
        $config = Config::getInstance();
        $general = $config->General;

        if (array_key_exists($this->marketplacEnabledConfigSetting, $general)) {
            unset($general[$this->marketplacEnabledConfigSetting]);

            $config->General = $general;
            $config->forceSave();
        }
    }
}
