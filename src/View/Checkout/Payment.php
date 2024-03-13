<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\View\Checkout;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Payment extends \XLite\View\Checkout\Payment
{
    /**
     * TODO: Remove when BUG-5544 will be resolved
     */
    public function getCSSFiles()
    {
        return array_merge(parent::getCSSFiles(), [
            [
                'file'  => 'checkout/css/credit_card.less',
                'media' => 'screen',
                'merge' => 'bootstrap/css/bootstrap.less',
            ],
            'modules/Iidev/CloverPayments/checkout/style.less',
        ]);
    }
}
