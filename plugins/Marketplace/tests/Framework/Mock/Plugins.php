<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace\tests\Framework\Mock;

class Plugins extends \Piwik\Plugins\Marketplace\Plugins {

    public $paidPlugins = array();

    public function __construct()
    {
    }

    public function getAllPaidPlugins()
    {
        return $this->paidPlugins;
    }
}
