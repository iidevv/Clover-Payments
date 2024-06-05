<?php

namespace Iidev\CloverPayments\View\Payment;

use XCart\Extender\Mapping\Extender;

/**
 * Payment method
 * @Extender\Mixin
 */
abstract class Method extends \XLite\View\Payment\Method
{
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();
        if ($this->getPaymentMethod()->getServiceName() === 'CloverPayments') {
            $list[] = 'modules/Iidev/CloverPayments/config.css';
        }

        return $list;
    }
}
