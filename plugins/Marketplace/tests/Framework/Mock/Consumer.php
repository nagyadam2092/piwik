<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Framework\Mock;

use \Piwik\Plugins\Marketplace\Consumer as ActualConsumer;

class Consumer {

    public static function build($service)
    {
        $client = Client::build($service);
        return new ActualConsumer($client);
    }

    public static function buildInvalidLicense()
    {
        $service = new Service();
        $service->returnFixture('v2.0_consumer.json');
        return static::build($service);
    }

    public static function buildValidLicense()
    {
        $service = new Service();
        $service->returnFixture('v2.0_consumer-access_token-valid_never_expires_and_two_custom_plugins.json');
        return static::build($service);
    }

    public static function buildExpiredLicense()
    {
        $service = new Service();
        $service->returnFixture('v2.0_consumer-access_token-valid_but_expired_recently.json');
        return static::build($service);
    }
}
