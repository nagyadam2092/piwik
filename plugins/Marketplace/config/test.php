<?php

use Interop\Container\ContainerInterface;
use Piwik\Plugins\Marketplace\Api\Service;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer as MockConsumer;
use Piwik\Plugins\Marketplace\LicenseKey;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Service as MockService;
use Piwik\Plugins\Marketplace\Input\PurchaseType;
use Piwik\Container\StaticContainer;

return array(
    'MarketplaceEndpoint' => function (ContainerInterface $c) {
        $domain = 'http://plugins.piwik.org';
        $updater = $c->get('Piwik\Plugins\CoreUpdater\Updater');

        if ($updater->isUpdatingOverHttps()) {
            $domain = str_replace('http://', 'https://', $domain);
        }

        return $domain;
    },
    'Piwik\Plugins\Marketplace\Consumer' => function (ContainerInterface $c) {
        $consumerTest = $c->get('test.vars.consumer');
        $licenseKey = new LicenseKey();

        if ($consumerTest == 'validLicense') {
            $consumer = MockConsumer::buildValidLicense();
            $licenseKey->set('123456789');
        } elseif ($consumerTest == 'expiredLicense') {
            $consumer = MockConsumer::buildExpiredLicense();
            $licenseKey->set('123456789');
        } else {
            $consumer = MockConsumer::buildInvalidLicense();
            $licenseKey->set(null);
        }

        return $consumer;
    },
    'Piwik\Plugins\Marketplace\Api\Service' => DI\decorate(function ($previous, ContainerInterface $c) {
        if (!$c->get('test.vars.mockMarketplaceApiService')) {
            return $previous;
        }

        // for ui tests
        $service = new MockService();

        $key = new LicenseKey();
        $accessToken = $key->get();

        $service->authenticate($accessToken);

        $service->setOnDownloadCallback(function ($action, $params) use ($service) {
            if ($action === 'consumer' && $service->getAccessToken() === 'valid') {
                return $service->getFixtureContent('v2.0_consumer-access_token-valid_never_expires_and_two_custom_plugins.json');
            } elseif ($action === 'plugins' && empty($params['purchase_type']) && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins.json');
            } elseif ($action === 'plugins' && !$service->hasAccessToken() && $params['purchase_type'] === PurchaseType::TYPE_PAID && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-paid-access_token-not_existing_token.json');
            } elseif ($action === 'plugins' && !$service->hasAccessToken() && $params['purchase_type'] === PurchaseType::TYPE_FREE && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-free-access_token-not_existing_token.json');
            } elseif ($action === 'plugins' && $service->hasAccessToken() && $params['purchase_type'] === PurchaseType::TYPE_PAID && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-paid-access_token-valid_not_expired_and_one_custom_plugin.json');
            } elseif ($action === 'plugins' && $service->hasAccessToken() && $params['purchase_type'] === PurchaseType::TYPE_FREE && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_plugins-purchase_type-free-access_token-valid_not_expired_and_one_custom_plugin.json');
            } elseif ($action === 'themes' && empty($params['purchase_type']) && empty($params['query'])) {
                return $service->getFixtureContent('v2.0_themes.json');
            } elseif ($action === 'plugins/Barometer/info') {
                return $service->getFixtureContent('v2.0_plugins_Barometer_info.json');
            } elseif ($action === 'plugins/TreemapVisualization/info') {
                return $service->getFixtureContent('v2.0_plugins_TreemapVisualization_info.json');
            } elseif ($action === 'plugins/CustomPlugin1/info' && $service->hasAccessToken()) {
                return $service->getFixtureContent('v2.0_plugins_CustomPlugin1_info-access_token-valid_not_expired_and_one_custom_plugin.json');
            } elseif ($action === 'plugins/PaidPlugin1/info' && $service->hasAccessToken()) {
                return $service->getFixtureContent('v2.0_plugins_PaidPlugin1_info-access_token-valid_not_expired_and_one_custom_plugin.json');
            } elseif ($action === 'plugins/CustomPlugin1/info' && !$service->hasAccessToken()) {
                return $service->getFixtureContent('v2.0_plugins_CustomPlugin1_info-access_token-not_existing_token.json');
            } elseif ($action === 'plugins/PaidPlugin1/info' && !$service->hasAccessToken()) {
                return $service->getFixtureContent('v2.0_plugins_PaidPlugin1_info-access_token-not_existing_token.json');
            } elseif ($action === 'plugins/checkUpdates') {
                return $service->getFixtureContent('v2.0_plugins_checkUpdates-pluginspluginsnameAnonymousPi.json');
            }
        });

        return $service;
    }),
    'observers.global' => DI\add(array(

        array('Request.getRenamedModuleAndAction', function (&$module, &$action) {
            $redirectIfModule = StaticContainer::get('test.vars.showExpiredLicenseIfModule');

            if (!empty($redirectIfModule) && $module === $redirectIfModule) {
                $module = 'Marketplace';
                $action = 'expiredLicense';
            }
        }),

    )),
);