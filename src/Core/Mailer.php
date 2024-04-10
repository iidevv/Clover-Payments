<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\Core;

use XCart\Extender\Mapping\Extender;
use XCart\Messenger\Message\SendMail;
use Iidev\CloverPayments\Core\Mail\CloverPaymentsChargeback;
use Qualiteam\SkinActXPaymentsSubscriptions\Model\Subscription;

use XLite\InjectLoggerTrait;

/**
 * @Extender\Mixin
 */
abstract class Mailer extends \XLite\Core\Mailer
{
    use InjectLoggerTrait;
    /**
     * @param \XLite\Model\Order $order
     * @param string             $referenceNumber
     */
    public static function sendCloverPaymentsChargeback(\XLite\Model\Order $order, $referenceNumber)
    {
        static::getBus()->dispatch(new SendMail(CloverPaymentsChargeback::class, [$order, $referenceNumber]));
    }

    /**
     * Send subscription status details notification
     *
     * @param Subscription $subscription Subscription
     *
     * @return void
     */
    public static function sendSubscriptionStatus(Subscription $subscription)
    {

    }
}
