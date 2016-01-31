<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Marketplace;

use Exception;
use Piwik\Option;
use Piwik\Piwik;
use Piwik\Plugins\Marketplace\Api\Client;
use Piwik\Plugins\Marketplace\Api\Service;

/**
 * API for plugin Marketplace
 *
 * @method static \Piwik\Plugins\Marketplace\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /**
     * @var Client
     */
    private $marketplaceClient;

    /**
     * @var Service
     */
    private $marketplaceService;

    public function __construct(Service $service, Client $client)
    {
        $this->marketplaceClient  = $client;
        $this->marketplaceService = $service;
    }

    /**
     * Deletes an existing license key if one is set.
     *
     * @return bool
     */
    public function deleteLicenseKey()
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->setLicenseKey(null);
        return true;
    }

    /**
     * Saves the given license key in case the key is actually valid (exists on the Piwik Marketplace and is not
     * yet expired).
     *
     * @param string $licenseKey
     * @return bool
     *
     * @throws Exception In case of an invalid license key
     * @throws Service\Exception In case of any network problems
     */
    public function saveLicenseKey($licenseKey)
    {
        Piwik::checkUserHasSuperUserAccess();

        $this->marketplaceService->authenticate($licenseKey);

        try {
            $consumer = $this->marketplaceService->fetch('consumer', array());
        } catch (Api\Service\Exception $e) {
            if ($e->getCode() === Api\Service\Exception::HTTP_ERROR) {
                throw $e;
            }

            $consumer = false;
        }

        if (empty($consumer['name'])) {
            throw new Exception('Entered license key is not valid');
        }

        $this->setLicenseKey($licenseKey);

        return true;
    }

    private function setLicenseKey($licenseKey)
    {
        $key = new LicenseKey();
        $key->set($licenseKey);

        $this->marketplaceClient->clearAllCacheEntries();
    }

}
