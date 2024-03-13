<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

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
