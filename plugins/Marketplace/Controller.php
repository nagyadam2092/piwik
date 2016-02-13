<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace;

use Piwik\Common;
use Piwik\Date;
use Piwik\Filesystem;
use Piwik\Http;
use Piwik\Log;
use Piwik\Nonce;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugins\CorePluginsAdmin\Controller as PluginsController;
use Piwik\Plugins\CorePluginsAdmin\CorePluginsAdmin;
use Piwik\Plugins\CorePluginsAdmin\PluginInstaller;
use Piwik\Plugins\Marketplace\Input\Mode;
use Piwik\Plugins\Marketplace\Input\PluginName;
use Piwik\Plugins\Marketplace\Input\PurchaseType;
use Piwik\Plugins\Marketplace\Input\Sort;
use Piwik\ProxyHttp;
use Piwik\SettingsPiwik;
use Piwik\Url;
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
     * @var PluginInstaller
     */
    private $pluginInstaller;

    /**
     * Controller constructor.
     * @param Plugins $plugins
     */
    public function __construct(Plugins $plugins, Api\Client $marketplaceApi, Consumer $consumer, PluginInstaller $pluginInstaller)
    {
        $this->plugins = $plugins;
        $this->marketplaceApi = $marketplaceApi;
        $this->consumer = $consumer;
        $this->pluginInstaller = $pluginInstaller;

        parent::__construct();
    }

    public function expiredLicense()
    {
        Piwik::checkUserIsNotAnonymous();
        Piwik::checkUserHasSomeViewAccess();

        $pluginName = new PluginName('module');
        $pluginName = $pluginName->getPluginName();

        $view = new View('@Marketplace/expired-license');
        $this->setBasicVariablesView($view);

        $view->pluginName = $pluginName;
        $view->deactivateNonce = Nonce::getNonce(PluginsController::DEACTIVATE_NONCE);
        $view->isTrackerPlugin = false;

        $isEmbeddedViaXhr = false;
        if (Common::getRequestVar('widget', 0, 'int')) {
            $isEmbeddedViaXhr = true;
        } elseif (!empty($_SERVER['REQUEST_METHOD']) && 'post' === strtolower($_SERVER['REQUEST_METHOD'])) {
            $isEmbeddedViaXhr = true;
        }

        $view->isEmbeddedViaXhr = $isEmbeddedViaXhr;

        $pluginManager = $this->getPluginManager();

        try {
            $plugin = $pluginManager->getLoadedPlugin($pluginName);
            if ($plugin) {
                $view->isTrackerPlugin = $pluginManager->isTrackerPlugin($plugin);
            }
        } catch (Exception $e) {

        }

        return $view->render();
    }

    public function pluginDetails()
    {
        $view = $this->configureViewAndCheckPermission('@Marketplace/plugin-details');

        $pluginName = new PluginName();
        $pluginName = $pluginName->getPluginName();

        $activeTab  = Common::getRequestVar('activeTab', '', 'string');
        if ('changelog' !== $activeTab) {
            $activeTab = '';
        }

        try {
            $plugin = $this->plugins->getPluginInfo($pluginName);

            if (empty($plugin['name'])) {
                throw new Exception('Plugin does not exist');
            }
        } catch (Exception $e) {
            $plugin = null;
            $view->errorMessage = $e->getMessage();
        }

        $view->plugin       = $plugin;
        $view->isSuperUser  = Piwik::hasUserSuperUserAccess();
        $view->installNonce = Nonce::getNonce(PluginsController::INSTALL_NONCE);
        $view->updateNonce  = Nonce::getNonce(PluginsController::UPDATE_NONCE);
        $view->activeTab    = $activeTab;
        $view->isAutoUpdatePossible = SettingsPiwik::isAutoUpdatePossible();
        $view->isAutoUpdateEnabled = SettingsPiwik::isAutoUpdateEnabled();

        return $view->render();
    }

    public function download()
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->dieIfPluginsAdminIsDisabled();

        $pluginName = new PluginName();
        $pluginName = $pluginName->getPluginName();

        Nonce::checkNonce($pluginName);

        $filename = $pluginName . '.zip';

        try {
            $pathToPlugin = $this->marketplaceApi->download($pluginName);
            ProxyHttp::serverStaticFile($pathToPlugin, 'application/zip', $expire = 0, $start = false, $end = false, $filename);
        } catch (Exception $e) {
            Common::sendResponseCode(500);
            Log::warning('Could not download file . ' . $e->getMessage());
        }

        if (!empty($pathToPlugin)) {
            Filesystem::deleteFileIfExists($pathToPlugin);
        }
    }

    public function overview()
    {
        $view = $this->configureViewAndCheckPermission('@Marketplace/overview');

        $show  = Common::getRequestVar('show', 'plugins', 'string');
        $query = Common::getRequestVar('query', '', 'string', $_POST);

        $sort = new Sort();
        $sort = $sort->getSort();

        $purchaseType = new PurchaseType($this->consumer);
        $type = $purchaseType->getPurchaseType();

        $mode = new Mode();
        $mode = $mode->getMode();

        // we're fetching all available plugins to decide which tabs need to be shown in the UI and to know the number
        // of total available plugins
        $freePlugins = $this->plugins->getAllFreePlugins();
        $paidPlugins = $this->plugins->getAllPaidPlugins();
        $allThemes   = $this->plugins->getAllThemes();

        $showThemes  = ($show === 'themes');
        $showPlugins = !$showThemes;
        $showPaid    = ($type === PurchaseType::TYPE_PAID);
        $showFree    = !$showPaid;

        if ($showPlugins && $showPaid) {
            $type = PurchaseType::TYPE_PAID;
            $view->numAvailablePlugins = count($paidPlugins);
        } elseif ($showPlugins && $showFree) {
            $type = PurchaseType::TYPE_FREE;
            $view->numAvailablePlugins = count($freePlugins);
        } else {
            $type = PurchaseType::TYPE_ALL;
            $view->numAvailablePlugins = count($allThemes);
        }

        $pluginsToShow = $this->plugins->searchPlugins($query, $sort, $showThemes, $type);

        $consumer = $this->consumer->getConsumer();

        if (!empty($consumer['expireDate'])) {
            $expireDate = Date::factory($consumer['expireDate']);
            $consumer['expireDateLong'] = $expireDate->getLocalized(Date::DATE_FORMAT_LONG);
        }

        $paidPluginsToInstallAtOnce = array();
        if (SettingsPiwik::isAutoUpdatePossible()) {
            foreach ($paidPlugins as $paidPlugin) {
                if ($this->canPluginBeInstalled($paidPlugin)
                    || !$this->getPluginManager()->isPluginActivated($paidPlugin['name'])) {
                    $paidPluginsToInstallAtOnce[] = $paidPlugin['name'];
                }
            }
        }

        $view->paidPluginsToInstallAtOnce = $paidPluginsToInstallAtOnce;
        $view->distributor = $this->consumer->getDistributor();
        $view->whitelistedGithubOrgs = $this->consumer->getWhitelistedGithubOrgs();
        $view->hasAccessToPaidPlugins = $this->consumer->hasAccessToPaidPlugins();
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
        $view->deactivateNonce = Nonce::getNonce(PluginsController::DEACTIVATE_NONCE);
        $view->activateNonce = Nonce::getNonce(PluginsController::ACTIVATE_NONCE);
        $view->isSuperUser = Piwik::hasUserSuperUserAccess();
        $view->isPluginsAdminEnabled = CorePluginsAdmin::isPluginsAdminEnabled();
        $view->isAutoUpdatePossible = SettingsPiwik::isAutoUpdatePossible();
        $view->isAutoUpdateEnabled = SettingsPiwik::isAutoUpdateEnabled();

        return $view->render();
    }

    public function installAllPaidPlugins()
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->dieIfPluginsAdminIsDisabled();
        Plugin\ControllerAdmin::displayWarningIfConfigFileNotWritable();

        Nonce::checkNonce(PluginsController::INSTALL_NONCE);

        $paidPlugins = $this->plugins->getAllPaidPlugins();

        $hasErrors = false;
        foreach ($paidPlugins as $paidPlugin) {
            if (!$this->canPluginBeInstalled($paidPlugin)) {
                continue;
            }

            $pluginName = $paidPlugin['name'];

            try {

                $this->pluginInstaller->installOrUpdatePluginFromMarketplace($pluginName);

            } catch (\Exception $e) {

                $notification = new Notification($e->getMessage());
                $notification->context = Notification::CONTEXT_ERROR;
                Notification\Manager::notify('Marketplace_Install' . $pluginName, $notification);

                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            Url::redirectToReferrer();
            return;
        }

        $pluginManager = $this->getPluginManager();
        $dependency = new Plugin\Dependency();

        for ($i = 0; $i <= 10; $i++) {
            foreach ($paidPlugins as $index => $paidPlugin) {
                $pluginName = $paidPlugin['name'];

                if ($pluginManager->isPluginActivated($pluginName)) {
                    unset($paidPlugins[$index]);
                    continue;
                }

                if (empty($paidPlugin['require'])
                    || !$dependency->hasDependencyToDisabledPlugin($paidPlugin['require'])) {

                    unset($paidPlugins[$index]);

                    try {
                        $this->getPluginManager()->activatePlugin($pluginName);
                    } catch (Exception $e) {

                        $hasErrors = true;
                        $notification = new Notification($e->getMessage());
                        $notification->context = Notification::CONTEXT_ERROR;
                        Notification\Manager::notify('Marketplace_Install' . $pluginName, $notification);
                    }
                }
            }
        }

        if ($hasErrors) {
            $notification = new Notification('Some paid plugins were not installed successfully');
            $notification->context = Notification::CONTEXT_INFO;
        } else {
            $notification = new Notification('All paid plugins were successfully installed.');
            $notification->context = Notification::CONTEXT_SUCCESS;
        }

        Notification\Manager::notify('Marketplace_InstallAll', $notification);

        Url::redirectToReferrer();
    }

    private function dieIfPluginsAdminIsDisabled()
    {
        if (!CorePluginsAdmin::isPluginsAdminEnabled()) {
            throw new \Exception('Enabling, disabling and uninstalling plugins has been disabled by Piwik admins.
            Please contact your Piwik admins with your request so they can assist you.');
        }
    }

    private function getPluginManager()
    {
        return Plugin\Manager::getInstance();
    }

    private function canPluginBeInstalled($plugin)
    {
        if (empty($plugin['isDownloadable'])) {
            return false;
        }

        $pluginName = $plugin['name'];
        $pluginManager = $this->getPluginManager();

        $isAlreadyInstalled = $pluginManager->isPluginInstalled($pluginName)
            || $pluginManager->isPluginLoaded($pluginName)
            || $pluginManager->isPluginActivated($pluginName);

        return !$isAlreadyInstalled;
    }

    protected function configureViewAndCheckPermission($template)
    {
        Piwik::checkUserIsNotAnonymous();

        $view = new View($template);
        $this->setBasicVariablesView($view);
        $this->displayWarningIfConfigFileNotWritable();

        $view->errorMessage = '';

        return $view;
    }
}
