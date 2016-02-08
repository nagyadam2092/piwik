<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace;

use Piwik\API\Request;
use Piwik\Cache;
use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Piwik;
use Piwik\Plugin;
use Exception;

class Marketplace extends \Piwik\Plugin
{
    /**
     * @see Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Request.getRenamedModuleAndAction' => 'getRenamedModuleAndAction',
        );
    }

    public function getRenamedModuleAndAction(&$module, &$action)
    {
        $pluginManager = self::getPluginManager();

        if ($pluginManager->isPluginBundledWithCore($module)) {
            // make sure to never accidentally hide a core plugin
            return;
        }

        $expiredPlugins = StaticContainer::get('Piwik\Plugins\Marketplace\Plugins\Expired');
        $expiredPlugins = $expiredPlugins->getNamesOfExpiredPaidPlugins();

        if (!empty($module) && in_array($module, $expiredPlugins, $strict = true)) {
            if ($pluginManager->isValidPluginName($module) && Request::isApiRequest($_GET)) {
                throw new Exception(Piwik::translate('Marketplace_PluginExpiredApiError', $module));
            }

            $module = 'Marketplace';
            $action = 'expiredLicense';
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/Marketplace/stylesheets/marketplace.less";
        $stylesheets[] = "plugins/Marketplace/stylesheets/plugin-details.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/Marketplace/javascripts/licensekey.js";
        $jsFiles[] = "plugins/Marketplace/javascripts/marketplace.js";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'Marketplace_LicenseKeyUpdatedSuccess';
        $translationKeys[] = 'Marketplace_LicenseKeyDeletedSuccess';
    }

    public static function isMarketplaceEnabled()
    {
        return self::getPluginManager()->isPluginActivated('Marketplace');
    }

    private static function getPluginManager()
    {
        return Plugin\Manager::getInstance();
    }

}
