<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

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
