<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Integration\Plugins;

use Piwik\Cache\Backend\ArrayCache;
use Piwik\Cache\Eager;
use Piwik\Plugins\Marketplace\API;
use Piwik\Plugins\Marketplace\Plugins\Expired;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Plugins;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik\Plugin;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer as ConsumerBuilder;

/**
 * @group Marketplace
 * @group ExpiredTest
 * @group Expired
 * @group Plugins
 */
class ExpiredTest extends IntegrationTestCase
{
    /**
     * @var Plugins
     */
    private $plugins;

    /**
     * @var Eager
     */
    private $cache;

    private $cacheKey = 'Marketplace_ExpiredPlugins';

    public function setUp()
    {
        parent::setUp();

        $this->plugins = new Plugins();
        $this->plugins->paidPlugins = array(
            array('name' => 'SecurityInfo'), array('name' => 'TreemapVisualization'), array('name' => 'AnyNotActivatedPlugin')
        );

        $this->cache = new Eager(new ArrayCache(), 'test');
    }

    public function test_getNamesOfExpiredPaidPlugins_shouldNeverHaveExpiredPlugins_IfValidLicenseKeyIsEntered()
    {
        $expired = $this->buildExpiredWithValidLicense();

        $this->assertSame(array(), $expired->getNamesOfExpiredPaidPlugins());
    }

    public function test_getNamesOfExpiredPaidPlugins_shouldCacheAnyResult()
    {
        $this->assertFalse($this->cache->contains($this->cacheKey));

        $this->buildExpiredWithValidLicense()->getNamesOfExpiredPaidPlugins();

        $this->assertTrue($this->cache->contains($this->cacheKey));
        $this->assertSame(array(), $this->cache->fetch($this->cacheKey));
    }

    public function test_getNamesOfExpiredPaidPlugins_shouldReturnEmptyArray_IfNotValidLicenseKeyButNoPaidPluginsInstalled()
    {
        $this->plugins->paidPlugins = array();

        $expired = $this->buildExpiredWithInvalidLicense();
        $this->assertSame(array(), $expired->getNamesOfExpiredPaidPlugins());
    }

    public function test_getNamesOfExpiredPaidPlugins_shouldReturnActivatedPaidPlugins_IfNotValidLicenseKey()
    {
        $expired = $this->buildExpiredWithInvalidLicense();
        $this->assertSame(array('SecurityInfo', 'TreemapVisualization'), $expired->getNamesOfExpiredPaidPlugins());
    }

    public function test_getNamesOfExpiredPaidPlugins_shouldCache_IfNotValidLicenseKeyButPaidPluginsInstalled()
    {
        $this->buildExpiredWithInvalidLicense()->getNamesOfExpiredPaidPlugins();
        $this->assertSame(array('SecurityInfo', 'TreemapVisualization'), $this->cache->fetch($this->cacheKey));
    }

    private function buildExpiredWithValidLicense()
    {
        $consumer = ConsumerBuilder::buildValidLicense();
        return $this->buildExpired($consumer);
    }

    private function buildExpiredWithInvalidLicense()
    {
        $consumer = ConsumerBuilder::buildInvalidLicense();
        return $this->buildExpired($consumer);
    }

    private function buildExpired($consumer)
    {
        return new Expired($consumer, $this->plugins, $this->cache);
    }

}
