<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\Model\Payment;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Transaction extends \XLite\Model\Payment\Transaction
{
    public const BLUESNAP_ERRORS_CELL = 'bluesnapErrors';
    /**
     * @return array|null
     */
    public function getBluesnapFailureReasons()
    {
        $cell = $this->getDataCell(static::BLUESNAP_ERRORS_CELL);

        if ($cell && $cell->getValue()) {
            return (array)@json_decode($cell->getValue());
        }

        return null;
    }
}
