<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\Marketplace\Input;
use Piwik\Common;
use Piwik\Plugins\Marketplace\Consumer;

/**
 */
class PurchaseType
{
    const TYPE_FREE = 'free';
    const TYPE_PAID = 'paid';
    const TYPE_ALL  = '';

    /**
     * @var Consumer
     */
    private $consumer;

    public function __construct(Consumer $consumer)
    {
        $this->consumer = $consumer;
    }

    public function getPurchaseType()
    {
        $defaultType = static::TYPE_FREE;

        $consumer = $this->consumer->getConsumer();
        if (!empty($consumer)) {
            // we load paid plugins by default for valid and for expired licenses (for expired license so they see the
            // warning about the expired license etc)
            $defaultType = static::TYPE_PAID;
        }

        $type = Common::getRequestVar('type', $defaultType, 'string');

        return $type;
    }

}
