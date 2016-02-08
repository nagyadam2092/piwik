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
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\SettingsServer;
use Piwik\Version;
use Exception as PhpException;
use Psr\Log\LoggerInterface;

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

    /**
     * @var Cache\Lazy
     */
    private $cache;

    /**
     * @var Plugin\Manager
     */
    private $pluginManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Service $service, Cache\Lazy $cache, LoggerInterface $logger)
    {
        $this->service = $service;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->pluginManager = Plugin\Manager::getInstance();
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

    private function getRandomTmpPluginDownloadFilename()
    {
        $tmpPluginPath = StaticContainer::get('path.tmp') . '/latest/plugins/';

        // we generate a random unique id as filename to prevent any user could possibly download zip directly by
        // opening $piwikDomain/tmp/latest/plugins/$pluginName.zip in the browser. Instead we make it harder here
        // and try to make sure to delete file in case of any error.
        $tmpPluginFolder = Common::generateUniqId();

        return $tmpPluginPath . $tmpPluginFolder . '.zip';
    }

    public function download($pluginOrThemeName)
    {
        @ignore_user_abort(true);
        SettingsServer::setMaxExecutionTime(0);

        $downloadUrl = $this->getDownloadUrl($pluginOrThemeName);

        if (empty($downloadUrl)) {
            return false;
        }

        // in the beginning we allowed to specify a download path but this way we make sure security is always taken
        // care of and we always generate a random download filename.
        $target = $this->getRandomTmpPluginDownloadFilename();

        Filesystem::deleteFileIfExists($target);

        $success = $this->service->download($downloadUrl, $target, static::HTTP_REQUEST_TIMEOUT);

        if ($success) {
            return $target;
        }

        return false;
    }

    /**
     * @param \Piwik\Plugin[] $plugins
     * @return array|mixed
     */
    private function checkUpdates($plugins)
    {
        $params = array();

        foreach ($plugins as $plugin) {
            $pluginName = $plugin->getPluginName();
            if (!$this->pluginManager->isPluginBundledWithCore($pluginName)) {
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
     * @return array
     */
    public function getInfoOfPluginsHavingUpdate($plugins)
    {
        $hasUpdates = $this->checkUpdates($plugins);

        $pluginDetails = array();

        foreach ($hasUpdates as $pluginHavingUpdate) {
            if (empty($pluginHavingUpdate)) {
                continue;
            }

            try {
                $plugin = $this->getPluginInfo($pluginHavingUpdate['name']);
            } catch (PhpException $e) {
                $this->logger->error($e->getMessage());
                $plugin = null;
            }

            if (!empty($plugin)) {
                $plugin['repositoryChangelogUrl'] = $pluginHavingUpdate['repositoryChangelogUrl'];
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

        $result = $this->cache->fetch($cacheId);

        if ($result !== false) {
            return $result;
        }

        try {
            $result = $this->service->fetch($action, $params);
        } catch (Service\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        $this->cache->save($cacheId, $result, self::CACHE_TIMEOUT_IN_SECONDS);

        return $result;
    }

    public function clearAllCacheEntries()
    {
        $this->cache->flushAll();
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
