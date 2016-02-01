<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace\Plugins;

use Piwik\Cache;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\Consumer;
use Piwik\Plugins\Marketplace\Plugins;
use Exception;

/**
 *
 */
class Expired
{
    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * @var Plugins
     */
    private $plugins;

    /**
     * @var Plugin\Manager
     */
    private $pluginManager;

    /**
     * @var Cache\Eager
     */
    private $cache;

    private $cacheKey = 'Marketplace_ExpiredPlugins';

    public function __construct(Consumer $consumer, Plugins $plugins, Cache\Eager $cache)
    {
        $this->consumer = $consumer;
        $this->plugins = $plugins;
        $this->pluginManager = Plugin\Manager::getInstance();
        $this->cache = $cache;
    }

    public function getNamesOfExpiredPaidPlugins()
    {
        // it is very important this is cached, otherwise performance may decrease a lot. Eager cache is currently
        // cached for 12 hours. In case we lower ttl for eager cache it might be worth considering to change to another
        // cache
        if ($this->cache->contains($this->cacheKey)) {
            $expiredPlugins = $this->cache->fetch($this->cacheKey);
        } else {
            $expiredPlugins = $this->getPluginNamesToExpireInCaseLicenseKeyExpired();
            $this->cache->save($this->cacheKey, $expiredPlugins);
        }

        return $expiredPlugins;
    }

    public function clearCache()
    {
        $this->cache->delete($this->cacheKey);
    }

    private function getPluginNamesToExpireInCaseLicenseKeyExpired()
    {
        try {
            if ($this->consumer->hasAccessToPaidPlugins()) {
                // user still has access to paid plugins, no need to do anything
                return array();
            }
        } catch (Exception $e) {
            // in case of any problems, especially with internet connection or marketplace, we should not disable
            // any problems as it might be a false alarm.

            return array();
        }

        $paidPlugins = $this->plugins->getAllPaidPlugins();

        $pluginNames = array();
        foreach ($paidPlugins as $paidPlugin) {
            if ($this->pluginManager->isPluginActivated($paidPlugin['name'])) {
                $pluginNames[] = $paidPlugin['name'];
            }
        }

        return $pluginNames;
    }
}
