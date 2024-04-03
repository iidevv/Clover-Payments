<?php

namespace Iidev\CloverPayments\Model\Payment\Base;

use XCart\Extender\Mapping\Extender;
use XLite\InjectLoggerTrait;

/**
 * Payment processor (Override Xpayments)
 *
 * @Extender\Mixin
 */
abstract class Processor extends \Qualiteam\SkinActXPaymentsSubscriptions\Model\Payment\Base\Processor
{
    use InjectLoggerTrait;

    public function isApplicable(\XLite\Model\Order $order, \XLite\Model\Payment\Method $method)
    {
        $isProMembershipInCart = $order->isProMembershipInCart($order->getItems());
        $isClover = $method->getServiceName() === 'CloverPayments';

        return !$isProMembershipInCart || $isClover;
    }
}
