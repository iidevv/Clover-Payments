<?php

namespace Iidev\CloverPayments\Core;

use XCart\Extender\Mapping\Extender;
use XCart\Messenger\Message\SendMail;
use Iidev\CloverPayments\Core\Mail\CloverPaymentsChargeback;

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

}
