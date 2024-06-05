<?php

namespace Iidev\CloverPayments\Model;

use XCart\Extender\Mapping\Extender;
use XLite\Model\Payment\Transaction;
use Iidev\CloverPayments\Model\Payment\Processor\CloverPayments;

/**
 * @Extender\Mixin
 */
class Order extends \XLite\Model\Order
{
    /**
     * Get failure reason
     *
     * @return string
     */
    public function getFailureReason()
    {
        if (!\XLite::isAdminZone()) {
            $transactions = $this->getPaymentTransactions()->getValues();
            /** @var \XLite\Model\Payment\Transaction $transaction */
            foreach (array_reverse($transactions) as $transaction) {
                if ($transaction->isFailed()) {
                    if (
                        ($cloverPaymentsFailureReasons = $transaction->getCloverPaymentsFailureReasons())
                        && in_array(static::t('CloverPayments Error #14016'), $cloverPaymentsFailureReasons)
                    ) {
                        return static::t('CloverPayments Error #14016');
                    }

                    if ($transaction->getNote() && $transaction->getNote() !== Transaction::getDefaultFailedReason()) {
                        $result = $transaction->getNote();
                    } else {
                        $reason = $transaction->getDataCell('status');

                        if ($reason && $reason->getValue()) {
                            $result = $reason->getValue();
                        }
                    }

                    if (isset ($result) && $transaction->getPaymentMethod()->getProcessor() instanceof CloverPayments) {
                        return static::t('Common CloverPayments error message');
                    }
                }
            }
        }

        return parent::getFailureReason();
    }
    /**
     * Check if the order contains a pro membership item.
     *
     * @return bool
     */
    public static function isProMembershipInCart($items)
    {
        foreach ($items as $item) {
            if ($item->getSku() === "PAIDMEMBERSHIP") {
                return true;
            }
        }

        return false;
    }
}
