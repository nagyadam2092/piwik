<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Log;
use Piwik\Metrics\Formatter;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\CorePluginsAdmin\Controller as PluginsController;
use Piwik\Plugins\CorePluginsAdmin\CorePluginsAdmin;
use Piwik\ProxyHttp;
use Piwik\SettingsPiwik;
use Piwik\View;
use Exception;

/**
 * A controller let's you for example create a page that can be added to a menu. For more information read our guide
 * http://developer.piwik.org/guides/mvc-in-piwik or have a look at the our API references for controller and view:
 * http://developer.piwik.org/api-reference/Piwik/Plugin/Controller and
 * http://developer.piwik.org/api-reference/Piwik/View
 */
class Controller extends \Piwik\Plugin\ControllerAdmin
{
    private $validSortMethods = array('popular', 'newest', 'alpha');
    private $defaultSortMethod = 'popular';

    /**
     * @var Plugins
     */
    private $plugins;

    /**
     * @var Api\Client
     */
    private $marketplaceApi;

    /**
     * @var Consumer
     */
    private $consumer;

    /**
     * Controller constructor.
     * @param Plugins $plugins
     */
    public function __construct(Plugins $plugins, Api\Client $marketplaceApi, Consumer $consumer)
    {
        $this->plugins = $plugins;
        $this->marketplaceApi = $marketplaceApi;
        $this->consumer = $consumer;

        parent::__construct();
    }

    public function pluginDetails()
    {
        static::dieIfMarketplaceIsDisabled();

        $pluginName = Common::getRequestVar('pluginName', null, 'string');
        $activeTab  = Common::getRequestVar('activeTab', '', 'string');
        if ('changelog' !== $activeTab) {
            $activeTab = '';
        }

        $view = $this->configureView('@Marketplace/plugin-details');

        try {
            $view->plugin = $this->plugins->getPluginInfo($pluginName);
            $view->isSuperUser  = Piwik::hasUserSuperUserAccess();
            $view->installNonce = Nonce::getNonce(PluginsController::INSTALL_NONCE);
            $view->updateNonce  = Nonce::getNonce(PluginsController::UPDATE_NONCE);
            $view->activeTab    = $activeTab;
            $view->isMultiServerEnvironment = SettingsPiwik::isMultiServerEnvironment();
        } catch (\Exception $e) {
            $view->errorMessage = $e->getMessage();
        }

        return $view->render();
    }

    public function download()
    {
        static::dieIfMarketplaceIsDisabled();
        Piwik::checkUserHasSuperUserAccess();
        Nonce::checkNonce(PluginsController::INSTALL_NONCE);

        $pluginName = Common::getRequestVar('pluginName');

        if (!Plugin\Manager::getInstance()->isValidPluginName($pluginName)){
            throw new Exception('Invalid plugin name given');
        }

        // we generate a random unique id as filename to prevent any user could possibly download zip directly by
        // opening $piwikDomain/tmp/latest/plugins/$pluginName.zip in the browser. Instead we make it harder here
        // and try to make sure to delete file in case of any error.
        $target = StaticContainer::get('path.tmp') . '/latest/plugins/' . Common::generateUniqId() . '.zip';
        $filename = $pluginName . '.zip';

        try {
            $this->marketplaceApi->download($pluginName, $target);
            ProxyHttp::serverStaticFile($target, 'application/zip', $expire = 0, $start = false, $end = false, $filename);
        } catch (Exception $e) {
            Common::sendResponseCode(500);
            Log::warning('Could not download file . ' . $e->getMessage());
        }

        Filesystem::deleteFileIfExists($target);
    }

    public function overview()
    {
        self::dieIfMarketplaceIsDisabled();

        $show = Common::getRequestVar('show', 'plugins', 'string');
        $query = Common::getRequestVar('query', '', 'string', $_POST);
        $sort = Common::getRequestVar('sort', $this->defaultSortMethod, 'string');

        $defaultType = 'free';
        if ($this->consumer->hasAccessToPaidPlugins()) {
            $defaultType = 'paid';
        }

        $type = Common::getRequestVar('type', $defaultType, 'string');

        if (!in_array($sort, $this->validSortMethods)) {
            $sort = $this->defaultSortMethod;
        }
        $mode = Common::getRequestVar('mode', 'admin', 'string');
        if (!in_array($mode, array('user', 'admin'))) {
            $mode = 'admin';
        }

        // we're fetching all available plugins to decide which tabs need to be shown in the UI and to know the number
        // of total available plugins
        $freePlugins = $this->plugins->searchPlugins($noQuery = '', $this->defaultSortMethod, $themes = false, 'free');
        $paidPlugins = $this->plugins->searchPlugins($noQuery = '', $this->defaultSortMethod, $themes = false, 'paid');
        $allThemes   = $this->plugins->searchPlugins($noQuery = '', $this->defaultSortMethod, $themes = true);

        $view = $this->configureView('@Marketplace/overview');

        $showThemes   = ($show === 'themes');
        $showPlugins = !$showThemes;
        $showPaid    = ($type === 'paid');
        $showFree    = !$showPaid;

        if ($showPaid && $showPlugins) {
            $type = 'paid';
        } elseif ($showFree && $showPlugins) {
            $type = 'free';
        } else {
            $type = ''; // show all themes
        }

        $pluginsToShow = $this->plugins->searchPlugins($query, $sort, $showThemes, $type);

        $consumer = $this->consumer->getConsumer();

        if (!empty($consumer['expireDate'])) {
            $expireDate = Date::factory($consumer['expireDate']);
            $consumer['expireDateLong'] = $expireDate->getLocalized(Date::DATE_FORMAT_LONG);

            $seconds = $expireDate->getTimestamp() - Date::now()->getTimestamp();

            $formatter = new Formatter();
            $consumer['expireDateDiff'] = $formatter->getPrettyTimeFromSeconds($seconds, $displayTimeAsSentence = true, $round = true);
        }

        $view->isMultiServerEnvironment = SettingsPiwik::isMultiServerEnvironment();
        $view->distributor = $this->consumer->getDistributor();
        $view->whitelistedGithubOrgs = $this->consumer->getWhitelistedGithubOrgs();
        $view->hasAccessToPaidPlugins = $this->consumer->hasAccessToPaidPlugins();
        $view->numAvailablePlugins = count($paidPlugins) + count($freePlugins) + count($allThemes);
        $view->pluginsToShow = $pluginsToShow;
        $view->consumer = $consumer;
        $view->paidPlugins = $paidPlugins;
        $view->freePlugins = $freePlugins;
        $view->themes = $allThemes;
        $view->showThemes = $showThemes;
        $view->showPlugins = $showPlugins;
        $view->showFree = $showFree;
        $view->showPaid = $showPaid;
        $view->mode = $mode;
        $view->query = $query;
        $view->sort = $sort;
        $view->installNonce = Nonce::getNonce(PluginsController::INSTALL_NONCE);
        $view->updateNonce = Nonce::getNonce(PluginsController::UPDATE_NONCE);
        $view->isSuperUser = Piwik::hasUserSuperUserAccess();

        return $view->render();
    }

    private function dieIfMarketplaceIsDisabled()
    {
        if (!Marketplace::isMarketplaceEnabled()) {
            throw new \Exception('The Marketplace feature has been disabled.
            You may enable the Marketplace by changing the config entry "[Marketplace]enabled = 0" to 1.
            Please contact your Piwik admins with your request so they can assist.');
        }

        $this->dieIfPluginsAdminIsDisabled();
    }

    private function dieIfPluginsAdminIsDisabled()
    {
        if (!CorePluginsAdmin::isPluginsAdminEnabled()) {
            throw new \Exception('Enabling, disabling and uninstalling plugins has been disabled by Piwik admins.
            Please contact your Piwik admins with your request so they can assist you.');
        }
    }

    protected function configureView($template)
    {
        Piwik::checkUserIsNotAnonymous();

        $view = new View($template);
        $this->setBasicVariablesView($view);
        $this->displayWarningIfConfigFileNotWritable();

        $view->errorMessage = '';

        return $view;
    }
}
