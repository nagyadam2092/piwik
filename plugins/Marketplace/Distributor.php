<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Marketplace;

use Piwik\Plugin;

/**
 * A distributor is someone who can distribute paid plugins on the Piwik Marketplace. A distributor usually represents
 * Piwik PRO but could be also any other distributor that was configured on the Marketplace.
 */
class Distributor
{
    private $distributor = array('name' => '', 'githubOrg' => '', 'homepage' => '', 'email' => '');
    
    public function __construct($distributor)
    {
        foreach ($this->distributor as $key => $value) {
            if (isset($distributor[$key])) {
                $this->distributor[$key] = $distributor[$key];
            }
        }
    }

    public function getName()
    {
        return $this->distributor['name'];
    }

    public function getGithubOrg()
    {
        return $this->distributor['githubOrg'];
    }

    public function getHomepage()
    {
        return $this->distributor['homepage'];
    }

    public function getEmail()
    {
        return $this->distributor['email'];
    }

}
