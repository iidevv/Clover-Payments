<?php

namespace Iidev\CloverPayments\Controller\Customer;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class CheckoutFailed extends \XLite\Controller\Customer\CheckoutFailed
{
    protected function getFailureReason()
    {
        $cart = $this->getFailedCart();

        return $cart && $cart->getCloverPaymentsFailureReasons()
            ? $cart->getCloverPaymentsFailureReasons()
            : parent::getFailureReason();
    }
}
