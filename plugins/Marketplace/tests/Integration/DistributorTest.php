<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Integration;

use Piwik\Plugins\Marketplace\Distributor;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group Plugins
 * @group Marketplace
 * @group DistributorTest
 * @group Distributor
 */
class DistributorTest extends IntegrationTestCase
{
    public function test__construct_no_data()
    {
        $distributor = $this->buildDistributor(array());
        $this->assertSame('', $distributor->getEmail());
        $this->assertSame('', $distributor->getGithubOrg());
        $this->assertSame('', $distributor->getHomepage());
        $this->assertSame('', $distributor->getName());
    }

    public function test__construct_some_data()
    {
        $distributor = $this->buildDistributor(array('name' => 'test', 'homepage' => 'websiteurl', 'email' => null));
        $this->assertSame('', $distributor->getEmail());
        $this->assertSame('', $distributor->getGithubOrg());
        $this->assertSame('websiteurl', $distributor->getHomepage());
        $this->assertSame('test', $distributor->getName());
    }

    public function test__construct_all_data()
    {
        $distributor = $this->buildDistributor(array('name' => 'test', 'homepage' => 'websiteurl', 'githubOrg' => 'piwik', 'email' => 'foo@bar'));
        $this->assertSame('foo@bar', $distributor->getEmail());
        $this->assertSame('piwik', $distributor->getGithubOrg());
        $this->assertSame('websiteurl', $distributor->getHomepage());
        $this->assertSame('test', $distributor->getName());
    }

    private function buildDistributor($distributor)
    {
        return new Distributor($distributor);
    }

}
