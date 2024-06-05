<?php

namespace Iidev\CloverPayments\Module\XC\ThemeTweaker\Core\Notifications;

use XCart\Extender\Mapping\Extender;

/**
 * StaticProvider
 *
 * @Deporator\Depend("XC\ThemeTweaker")
 * @Extender\Mixin
 */
class StaticProvider extends \XC\ThemeTweaker\Core\Notifications\StaticProvider
{
    protected static function getNotificationsStaticData()
    {
        return parent::getNotificationsStaticData() + [
                'modules/Iidev/CloverPayments/chargeback' => [
                    'referenceNumber' => 'reference_number_placeholder',
                ],
            ];
    }
}
