<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Unit;
use Piwik\Config;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Service;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group Marketplace
 * @group ConsumerTest
 * @group Consumer
 * @group Plugins
 */
class ConsumerTest extends IntegrationTestCase
{
    /**
     * @var Service
     */
    private $service;

    public function setUp()
    {
        $this->service = new Service();
    }

    public function test_getWhitelistedGithubOrgs_shouldReturnAnEmptyArrayWhenNoGithubOrgsConfigured()
    {
        $this->assertSame(array(), $this->buildConsumer()->getWhitelistedGithubOrgs());
    }

    public function test_getWhitelistedGithubOrgs_shouldReturnAListOfConfiguredGithubOrgs()
    {
        $setting = Config::getInstance()->Marketplace['whitelisted_github_orgs'];

        Config::getInstance()->Marketplace['whitelisted_github_orgs'] = 'piwik,PiwikPRO,tsteur,sgiehl';
        $this->assertSame(array('piwik', 'PiwikPRO', 'tsteur', 'sgiehl'), $this->buildConsumer()->getWhitelistedGithubOrgs());

        Config::getInstance()->Marketplace['whitelisted_github_orgs'] = $setting;
    }

    private function buildConsumer()
    {
        return \Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer::build($this->service);
    }

}
