<?php

namespace Iidev\CloverPayments\Core\Mail;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Registry extends \XLite\Core\Mail\Registry
{
    protected static function getNotificationsList()
    {
        return array_merge_recursive(parent::getNotificationsList(), [
            \XLite::ZONE_ADMIN => [
                'modules/Iidev/CloverPayments/chargeback' => CloverPaymentsChargeback::class,
            ],
        ]);
    }
}
