<?php


namespace Iidev\CloverPayments\Model;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Subscription extends \Qualiteam\SkinActXPaymentsSubscriptions\Model\Subscription
{
    /**
     * Process change status
     *
     * @return void
     */
    protected function processStopped()
    {
        $this->getOrigProfile()->setMembership(null);
    }
}
