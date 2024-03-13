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
class Checkout extends \XLite\Controller\Customer\Checkout
{
    protected function doActionResetBluesnapToken()
    {
        \Iidev\CloverPayments\Core\CloverPaymentsAPI::dropToken();
    }

    /**
     * Define the actions with no secure token
     *
     * @return array
     */
    public static function defineFreeFormIdActions()
    {
        return array_merge(
            parent::defineFreeFormIdActions(),
            [
                'reset_bluesnap_token',
            ]
        );
    }
}
