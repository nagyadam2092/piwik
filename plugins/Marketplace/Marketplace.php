<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace;

use Piwik\Config;
use Piwik\Plugin;

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
        );
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
        return (bool) Config::getInstance()->Marketplace['enabled'] &&
               Plugin\Manager::getInstance()->isPluginActivated('Marketplace');
    }

}
