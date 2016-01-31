<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace\Api;

use Piwik\Cache;
use Piwik\Http;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\SettingsServer;
use Piwik\Version;

/**
 *
 */
class Client
{
    const CACHE_TIMEOUT_IN_SECONDS = 1200;
    const HTTP_REQUEST_TIMEOUT = 60;

    /**
     * @var Service
     */
    private $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function getPluginInfo($name)
    {
        $action = sprintf('plugins/%s/info', $name);

        return $this->fetch($action, array());
    }

    public function getConsumer()
    {
        try {
            $consumer = $this->fetch('consumer', array());
        } catch (Exception $e) {
            $consumer = null;
        }

        return $consumer;
    }

    public function download($pluginOrThemeName, $target)
    {
        @ignore_user_abort(true);
        SettingsServer::setMaxExecutionTime(0);

        $downloadUrl = $this->getDownloadUrl($pluginOrThemeName);

        if (empty($downloadUrl)) {
            return false;
        }

        $success = $this->service->download($downloadUrl, $target, static::HTTP_REQUEST_TIMEOUT);

        return $success;
    }

    /**
     * @param \Piwik\Plugin[] $plugins
     * @return array|mixed
     */
    public function checkUpdates($plugins)
    {
        $params = array();

        foreach ($plugins as $plugin) {
            $pluginName = $plugin->getPluginName();
            if (!\Piwik\Plugin\Manager::getInstance()->isPluginBundledWithCore($pluginName)) {
                $params[] = array('name' => $plugin->getPluginName(), 'version' => $plugin->getVersion());
            }
        }

        if (empty($params)) {
            return array();
        }

        $params = array('plugins' => $params);

        $hasUpdates = $this->fetch('plugins/checkUpdates', array('plugins' => json_encode($params)));

        if (empty($hasUpdates)) {
            return array();
        }

        return $hasUpdates;
    }

    /**
     * @param  \Piwik\Plugin[] $plugins
     * @param  bool $themesOnly
     * @return array
     */
    public function getInfoOfPluginsHavingUpdate($plugins, $themesOnly)
    {
        $hasUpdates = $this->checkUpdates($plugins);

        $pluginDetails = array();

        foreach ($hasUpdates as $pluginHavingUpdate) {
            $plugin = $this->getPluginInfo($pluginHavingUpdate['name']);
            $plugin['repositoryChangelogUrl'] = $pluginHavingUpdate['repositoryChangelogUrl'];

            if (!empty($plugin['isTheme']) == $themesOnly) {
                $pluginDetails[] = $plugin;
            }
        }

        return $pluginDetails;
    }

    public function searchForPlugins($keywords, $query, $sort, $purchaseType)
    {
        $response = $this->fetch('plugins', array('keywords' => $keywords, 'query' => $query, 'sort' => $sort, 'purchase_type' => $purchaseType));

        if (!empty($response['plugins'])) {
            return $response['plugins'];
        }

        return array();
    }

    public function searchForThemes($keywords, $query, $sort, $purchaseType)
    {
        $response = $this->fetch('themes', array('keywords' => $keywords, 'query' => $query, 'sort' => $sort, 'purchase_type' => $purchaseType));

        if (!empty($response['plugins'])) {
            return $response['plugins'];
        }

        return array();
    }

    private function fetch($action, $params)
    {
        ksort($params); // sort params so cache is reused more often even if param order is different
        $query = http_build_query($params);
        $cacheId = $this->getCacheKey($action, $query);

        $cache  = $this->buildCache();
        $result = $cache->fetch($cacheId);

        if ($result !== false) {
            return $result;
        }

        try {
            $result = $this->service->fetch($action, $params);
        } catch (Service\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        $cache->save($cacheId, $result, self::CACHE_TIMEOUT_IN_SECONDS);

        return $result;
    }

    public function clearAllCacheEntries()
    {
        $this->buildCache()->flushAll();
    }

    private function buildCache()
    {
        return Cache::getLazyCache();
    }

    private function getCacheKey($action, $query)
    {
        $version = $this->service->getVersion();

        return sprintf('marketplace.api.%s.%s.%s', $version, str_replace('/', '.', $action), md5($query));
    }

    /**
     * @param  $pluginOrThemeName
     * @throws Exception
     * @return string
     */
    public function getDownloadUrl($pluginOrThemeName)
    {
        $plugin = $this->getPluginInfo($pluginOrThemeName);

        if (empty($plugin['versions'])) {
            throw new Exception('Plugin has no versions.');
        }

        $latestVersion = array_pop($plugin['versions']);
        $downloadUrl = $latestVersion['download'];

        return $this->service->getDomain() . $downloadUrl . '?coreVersion=' . Version::VERSION;
    }

}
