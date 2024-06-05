<?php

namespace Iidev\CloverPayments\Module\XC\ThemeTweaker\Core\Notifications\Data;

use XCart\Extender\Mapping\Extender;

/**
 * Order
 *
 * @Extender\Mixin
 * @Extender\Depend("XC\ThemeTweaker")
 */
class Order extends \XC\ThemeTweaker\Core\Notifications\Data\Order
{
    protected function getTemplateDirectories()
    {
        return array_merge(parent::getTemplateDirectories(), [
            'modules/Iidev/CloverPayments/chargeback',
        ]);
    }
}
