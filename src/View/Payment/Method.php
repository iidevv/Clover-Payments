<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

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
