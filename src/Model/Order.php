<?php

namespace Iidev\CloverPayments\Model;

use XCart\Extender\Mapping\Extender;
use XLite\Model\Payment\Transaction;
use Iidev\CloverPayments\Model\Payment\Processor\CloverPayments;
use Iidev\CloverPayments\Model\Payment\XpcTransactionData;
use XLite\Core\Database;

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

                    if (isset($result) && $transaction->getPaymentMethod()->getProcessor() instanceof CloverPayments) {
                        return static::t('Common CloverPayments error message');
                    }
                }
            }
        }

        return parent::getFailureReason();
    }

    public function isCloverPaymentsOrder()
    {
        $transaction = $this->getPaymentTransactions()->last();
        if ($transaction && $transaction->getPaymentMethod()->getProcessor() instanceof CloverPayments) {
            return true;
        }
        return false;
    }

    /**
     * Get payment card
     *
     * @return array
     */
    public function getCloverPaymentsCard()
    {
        $transaction = $this->getPaymentTransactions()->last();
        $transactionData = Database::getRepo(XpcTransactionData::class)->findOneBy([
            'transaction' => $transaction->getTransactionId()
        ]);

        if (!$transactionData)
            return [];

        return [
            'card_id' => $transactionData->getId(),
            'card_number' => $transactionData->getCardNumber(),
            'card_type' => $transactionData->getCardType(),
            'card_type_css' => strtolower($transactionData->getCardType()),
            'use_for_recharges' => $transactionData->getUseForRecharges(),
            'expire' => $transactionData->getCardExpire(),
        ];
    }
}
