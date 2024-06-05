<?php

namespace Iidev\CloverPayments\Model\Payment;

use XCart\Extender\Mapping\Extender;

/**
 * Payment method model
 * @Extender\Mixin
 */
class Method extends \XLite\Model\Payment\Method
{
    /**
     * Get message why we can't switch payment method
     *
     * @return string
     */
    public function getNotSwitchableReason()
    {
        $message = parent::getNotSwitchableReason();
        $processor = $this->getProcessor();

        if (
            $processor
            && $this->getServiceName() === 'CloverPayments'
            && $this->getSetting('username')
            && $this->getSetting('password')
            && $this->getSetting('mode') !== \XLite\View\FormField\Select\TestLiveMode::TEST
            && !\XLite\Core\Config::getInstance()->Security->customer_security
        ) {
            $message = static::t(
                'Payments with this payment method are not allowed because HTTPS is not configured',
                [
                    'url' => \XLite\Core\Converter::buildURL('https_settings')
                ]
            );
        }

        return $message;
    }
}
