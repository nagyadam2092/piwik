<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace;

use Piwik\Date;
use Piwik\PiwikPro\Advertising;
use Piwik\Plugin\Dependency as PluginDependency;
use Piwik\Plugin;

/**
 *
 */
class Plugins
{
    /**
     * @var Api\Client
     */
    private $marketplaceClient;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var Advertising
     */
    private $advertising;
    
    public function __construct(Api\Client $marketplaceClient, Consumer $consumer, Advertising $advertising)
    {
        $this->marketplaceClient = $marketplaceClient;
        $this->consumer = $consumer;
        $this->advertising = $advertising;
    }

    public function getPluginInfo($pluginName)
    {
        $plugin = $this->marketplaceClient->getPluginInfo($pluginName);
        $plugin = $this->enrichPluginInformation($plugin);

        return $plugin;
    }

    public function getAvailablePluginNames($themesOnly)
    {
        if ($themesOnly) {
            $plugins = $this->marketplaceClient->searchForThemes('', '', '', '');
        } else {
            $plugins = $this->marketplaceClient->searchForPlugins('', '', '', '');
        }

        $names = array();
        foreach ($plugins as $plugin) {
            $names[] = $plugin['name'];
        }

        return $names;
    }

    public function getAllAvailablePluginNames()
    {
        return array_merge(
            $this->getAvailablePluginNames(true),
            $this->getAvailablePluginNames(false)
        );
    }

    public function searchPlugins($query, $sort, $themesOnly, $purchaseType = '')
    {
        if ($themesOnly) {
            $plugins = $this->marketplaceClient->searchForThemes('', $query, $sort, $purchaseType);
        } else {
            $plugins = $this->marketplaceClient->searchForPlugins('', $query, $sort, $purchaseType);
        }

        $whitelistedDistributors = $this->consumer->getWhitelistedGithubOrgs();

        foreach ($plugins as $key => $plugin) {
            if (!empty($whitelistedDistributors) &&
                !$this->isPluginDevelopedByDistributors($plugin, $whitelistedDistributors)) {
                // for piwik pro clients we do not allow to install any 3rd party plugins
                unset($plugins[$key]);
            } else {
                $plugins[$key] = $this->enrichPluginInformation($plugin);
            }
        }

        return $plugins;
    }

    private function isPluginDevelopedByDistributors($plugin, $whitelistedDistributors)
    {
        if (empty($plugin['owner'])) {
            return false;
        }

        $whitelistedDistributors = array_map('strtolower', $whitelistedDistributors);

        $owner = strtolower($plugin['owner']);

        return in_array($owner, $whitelistedDistributors, $strict = true);
    }

    private function getPluginUpdateInformation($plugin)
    {
        if (empty($plugin['name'])) {
            return;
        }

        $pluginsHavingUpdate = $this->getPluginsHavingUpdate($plugin['isTheme']);

        foreach ($pluginsHavingUpdate as $pluginHavingUpdate) {
            if ($plugin['name'] == $pluginHavingUpdate['name']) {
                return $pluginHavingUpdate;
            }
        }
    }

    private function hasPluginUpdate($plugin)
    {
        $update = $this->getPluginUpdateInformation($plugin);

        return !empty($update);
    }

    /**
     * @param bool $themesOnly
     * @return array
     */
    public function getPluginsHavingUpdate($themesOnly)
    {
        $pluginManager = \Piwik\Plugin\Manager::getInstance();
        $pluginManager->loadAllPluginsAndGetTheirInfo();
        $loadedPlugins = $pluginManager->getLoadedPlugins();

        try {
            $pluginsHavingUpdate = $this->marketplaceClient->getInfoOfPluginsHavingUpdate($loadedPlugins, $themesOnly);
        } catch (\Exception $e) {
            $pluginsHavingUpdate = array();
        }

        foreach ($pluginsHavingUpdate as $key => $updatePlugin) {
            foreach ($loadedPlugins as $loadedPlugin) {
                if (!empty($updatePlugin['name'])
                    && $loadedPlugin->getPluginName() == $updatePlugin['name']
                ) {
                    $updatePlugin['currentVersion'] = $loadedPlugin->getVersion();
                    $updatePlugin['isActivated'] = $pluginManager->isPluginActivated($updatePlugin['name']);
                    $pluginsHavingUpdate[$key] = $this->addMissingRequirements($updatePlugin);
                    break;
                }
            }
        }

        // remove plugins that have updates but for some reason are not loaded
        foreach ($pluginsHavingUpdate as $key => $updatePlugin) {
            if (empty($updatePlugin['currentVersion'])) {
                unset($pluginsHavingUpdate[$key]);
            }
        }

        return $pluginsHavingUpdate;
    }

    private function enrichPluginInformation($plugin)
    {
        $plugin['isInstalled']  = Plugin\Manager::getInstance()->isPluginLoaded($plugin['name']);
        $plugin['canBeUpdated'] = $plugin['isInstalled'] && $this->hasPluginUpdate($plugin);
        $plugin['lastUpdated'] = $this->toShortDate($plugin['lastUpdated']);

        if (!empty($plugin['owner'])
            && strtolower($plugin['owner']) === 'piwikpro'
            && !empty($plugin['homepage'])
            && strpos($plugin['homepage'], 'pk_campaign') === false) {
            $plugin['homepage'] = $this->advertising->addPromoCampaignParametersToUrl($plugin['homepage'], Advertising::CAMPAIGN_NAME_UPGRADE_TO_PRO, 'Marketplace', $plugin['name']);
        }

        if ($plugin['canBeUpdated']) {
            $pluginUpdate = $this->getPluginUpdateInformation($plugin);
            $plugin['repositoryChangelogUrl'] = $pluginUpdate['repositoryChangelogUrl'];
            $plugin['currentVersion']         = $pluginUpdate['currentVersion'];
        }

        if (!empty($plugin['activity']['lastCommitDate'])
            && false === strpos($plugin['activity']['lastCommitDate'], '0000')
            && false === strpos($plugin['activity']['lastCommitDate'], '1970')) {
            $plugin['activity']['lastCommitDate'] = $this->toLongDate($plugin['activity']['lastCommitDate']);
        } else {
            $plugin['activity']['lastCommitDate'] = null;
        }

        if (!empty($plugin['versions'])) {
            foreach ($plugin['versions'] as $index => $version) {
                $plugin['versions'][$index]['release'] = $this->toLongDate($version['release']);
            }
        }

        $plugin = $this->addMissingRequirements($plugin);

        return $plugin;
    }

    private function toLongDate($date)
    {
        if (!empty($date)) {
            $date = Date::factory($date)->getLocalized(Date::DATE_FORMAT_LONG);
        }

        return $date;
    }

    private function toShortDate($date)
    {
        if (!empty($date)) {
            $date = Date::factory($date)->getLocalized(Date::DATE_FORMAT_SHORT);
        }

        return $date;
    }

    /**
     * @param $plugin
     */
    private function addMissingRequirements($plugin)
    {
        $plugin['missingRequirements'] = array();

        if (empty($plugin['versions']) || !is_array($plugin['versions'])) {
            return $plugin;
        }

        $latestVersion = $plugin['versions'][count($plugin['versions']) - 1];

        if (empty($latestVersion['requires'])) {
            return $plugin;
        }

        $requires = $latestVersion['requires'];

        $dependency = new PluginDependency();
        $plugin['missingRequirements'] = $dependency->getMissingDependencies($requires);

        return $plugin;
    }
}
