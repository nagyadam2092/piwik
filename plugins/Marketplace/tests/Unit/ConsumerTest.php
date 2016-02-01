<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Unit;
use Piwik\Plugins\Marketplace\Distributor;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Service;
use Piwik\Plugins\Marketplace\tests\Framework\Mock\Consumer as ConsumerBuilder;

/**
 * @group Marketplace
 * @group ConsumerTest
 * @group Consumer
 * @group Plugins
 */
class ConsumerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Service
     */
    private $service;

    public function setUp()
    {
        $this->service = new Service();
    }

    /**
     * @dataProvider getConsumerNotAuthenticated
     */
    public function test_getConsumer_shouldReturnNull_WhenNotAuthenticedBecauseNoTokenSetOrInvalidToken($fixture)
    {
        $this->service->returnFixture($fixture);
        $this->assertNull($this->buildConsumer()->getConsumer());
    }

    public function test_getConsumer_shouldReturnConsumerInformation_WhenExpiredRecently()
    {
        $this->service->returnFixture('v2.0_consumer-access_token-valid_but_expired_recently.json');

        $expected = array (
            'distributor' => array (
                'id' => 1,
                'name' => 'Piwik PRO',
                'githubOrg' => 'PiwikPRO',
                'homepage' => 'https://piwik.pro',
                'email' => 'contact@piwik.pro',
            ),
            'isValid' => false,
            'isExpired' => true,
            'expireDate' => '2016-01-30 23:59:59',
            'isExpiredSoon' => false
        );

        $this->assertSame($expected, $this->buildConsumer()->getConsumer());
    }

    public function test_getConsumer_shouldReturnConsumerInformation_WhenValid()
    {
        $this->service->returnFixture('v2.0_consumer-access_token-valid_access_no_custom_plugin.json');

        $expected = array (
            'distributor' => array (
                'id' => 1,
                'name' => 'Piwik PRO',
                'githubOrg' => 'PiwikPRO',
                'homepage' => 'https://piwik.pro',
                'email' => 'contact@piwik.pro',
            ),
            'isValid' => true,
            'isExpired' => false,
            'expireDate' => '2035-02-13 23:59:59',
            'isExpiredSoon' => false
        );

        $this->assertSame($expected, $this->buildConsumer()->getConsumer());
    }

    /**
     * @dataProvider getConsumerAuthenticatedAndValidFixtureNames
     */
    public function test_getConsumer_shouldReturnConsumerInfo_WhenAuthenticated($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $consumer = $this->buildConsumer()->getConsumer();

        $this->assertNotEmpty($consumer);
        $this->assertTrue(is_array($consumer));
    }

    /**
     * @dataProvider getConsumerNotValidTokensFixtureNames
     */
    public function test_hasAccessToPaidPlugins_WhenNotValidToken($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $this->assertFalse($this->buildConsumer()->hasAccessToPaidPlugins());
    }

    /**
     * @dataProvider getConsumerAuthenticatedAndValidFixtureNames
     */
    public function test_hasAccessToPaidPlugins_WhenValidToken($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $this->assertTrue($this->buildConsumer()->hasAccessToPaidPlugins());
    }

    /**
     * @dataProvider getConsumerNotAuthenticated
     */
    public function test_getDistributor_WhenNotAuthenticated($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $this->assertNull($this->buildConsumer()->getDistributor());
    }

    /**
     * @dataProvider getConsumerAuthenticatedFixtureNames
     */
    public function test_getDistributor_WhenAuthenticated($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $distributor = $this->buildConsumer()->getDistributor();
        $this->assertTrue($distributor instanceof Distributor);
        $this->assertSame('Piwik PRO', $distributor->getName());
        $this->assertSame('PiwikPRO', $distributor->getGithubOrg());
        $this->assertSame('https://piwik.pro', $distributor->getHomepage());
        $this->assertSame('contact@piwik.pro', $distributor->getEmail());
    }

    /**
     * @dataProvider getConsumerNotValidTokensFixtureNames
     */
    public function test_getWhitelistedGithubOrgs_WhenNotValidToken($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $this->assertSame(array(), $this->buildConsumer()->getWhitelistedGithubOrgs());
    }

    /**
     * @dataProvider getConsumerAuthenticatedAndValidFixtureNames
     */
    public function test_getWhitelistedGithubOrgs_WhenValidToken($fixtureName)
    {
        $this->service->returnFixture($fixtureName);

        $this->assertSame(array('Piwik', 'PiwikPRO'), $this->buildConsumer()->getWhitelistedGithubOrgs());
    }

    public function getConsumerNotAuthenticated()
    {
        return array(
            array('v2.0_consumer.json'), // this fixture is result of no access token set at all
            array('v2.0_consumer-access_token-not_existing_token.json'),
            array('v2.0_consumer-access_token-valid_but_expired.json'), // expired more than a year ago
        );
    }

    // someone might be authenticated but the token is not valid anymore because exired
    public function getConsumerNotValidTokensFixtureNames()
    {
        $tokens   = $this->getConsumerNotAuthenticated();
        $tokens[] = array('v2.0_consumer-access_token-valid_but_expired_recently.json');

        return $tokens;
    }

    public function getConsumerAuthenticatedAndValidFixtureNames()
    {
        return array(
            array('v2.0_consumer-access_token-valid_access_no_custom_plugin.json'),
            array('v2.0_consumer-access_token-valid_not_expired_and_one_custom_plugin.json'),
            array('v2.0_consumer-access_token-valid_never_expires_and_two_custom_plugins.json'),
        );
    }

    public function getConsumerAuthenticatedFixtureNames()
    {
        $tokens   = $this->getConsumerAuthenticatedAndValidFixtureNames();
        $tokens[] = array('v2.0_consumer-access_token-valid_but_expired_recently.json');

        return $tokens;
    }

    public function test_buildValidLicenseKey()
    {
        $this->assertTrue(Consumer::buildValidLicense()->hasAccessToPaidPlugins());
    }

    public function test_buildExpiredLicenseKey()
    {
        $consumer = Consumer::buildExpiredLicense()->getConsumer();

        $this->assertFalse($consumer['isValid']);
        $this->assertTrue($consumer['isExpired']);
    }

    public function test_buildInvalidLicenseKey()
    {
        $consumer = Consumer::buildInvalidLicense()->getConsumer();

        $this->assertNull($consumer);
    }

    private function buildConsumer()
    {
        return ConsumerBuilder::build($this->service);
    }
}
