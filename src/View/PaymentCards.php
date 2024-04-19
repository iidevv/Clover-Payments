<?php

namespace Iidev\CloverPayments\View;

use XCart\Extender\Mapping\ListChild;

/**
 * @ListChild (list="center")
 */
class PaymentCards extends \XLite\View\AView
{
    /**
     * @return array
     */
    public static function getAllowedTargets()
    {
        return array_merge(parent::getAllowedTargets(), ['payment_cards']);
    }

    /**
     * @return string
     */
    protected function getDefaultTemplate()
    {
        return '';
    }
}