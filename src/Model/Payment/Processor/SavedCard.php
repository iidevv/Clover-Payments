<?php

namespace Iidev\CloverPayments\Model\Payment\Processor;

use Iidev\CloverPayments\Model\Payment\XpcTransactionData;
use XLite;
use XLite\Core\Database;
use XLite\Core\Request;
use XLite\Model\Order;
use XLite\Model\Payment\Method;

class SavedCard
{

    /**
     * Get input template
     *
     * @return string|void
     */
    public function getInputTemplate()
    {
        return 'modules/Iidev\CloverPayments/checkout/saved_cards.twig';
    }

    /**
     * Get input errors
     *
     * @param array $data Input data
     *
     * @return array
     */
    public function getInputErrors(array $data)
    {
        if (empty($data['saved_card_id'])) {
            $errors[] = 'Wrong credit card submitted';
        }

        return $errors;
    }

    /**
     * Check - payment processor is applicable for specified order or not
     *
     * @param Order          $order  Order
     * @param Method $method Payment method
     *
     * @return boolean
     */
    public function isApplicable(Order $order, Method $method)
    {
        $controller = XLite::getController();

        return $order->getProfile()
            && $order->getProfile()->getSavedCards()
            && method_exists($controller, 'isLogged')
            && $controller->isLogged();
    }

    /**
     * This is not Saved Card payment method
     *
     * @return boolean
     */
    protected function isSavedCardsPaymentMethod()
    {
        return true;
    }

    /**
     * Do initial payment
     *
     * @return string Status code
     */
    protected function doInitialPayment()
    {
        $class = XpcTransactionData::class;
        $cardId = Request::getInstance()->payment['saved_card_id'];
        $xpcTransactionData = Database::getRepo($class)
            ->find($cardId);

        $status = static::FAILED;

        if (
            $xpcTransactionData
            && $xpcTransactionData->getTransaction()
            && $xpcTransactionData->getTransaction()->getDataCell('xpc_txnid')
            && $xpcTransactionData->getTransaction()->getDataCell('xpc_txnid')->getValue()
        ) {

            $parentTransaction = $xpcTransactionData->getTransaction();

            foreach ($this->paymentSettingsToSave as $field) {

                $key = 'xpc_can_do_' . $field;
                if (
                    $parentTransaction->getXpcDataCell($key)
                    && $parentTransaction->getXpcDataCell($key)->getValue()
                ) {
                    $this->transaction->setXpcDataCell($key, $parentTransaction->getXpcDataCell($key)->getValue());
                }
            }

            $this->copyMaskedCard($parentTransaction, $this->transaction);

            $parentTxnId = $parentTransaction->getDataCell('xpc_txnid')->getValue();

            $recharge = $this->client->requestPaymentRecharge(
                $parentTxnId,
                $this->transaction,
                'Payment via saved card'
            );

            Database::getEM()->refresh($this->transaction);
            $this->transaction->update();
            Database::getEM()->flush();

            if ($recharge->isSuccess()) {

                // Update masked card data
                $this->copyMaskedCard($this->transaction, $parentTransaction);

                $response = $recharge->getResponse();

                if (isset($response['transaction_id'])) {
                    $this->transaction->setDataCell('xpc_txnid', $response['transaction_id'], 'Clover transaction id');
                    $this->processTransactionUpdate($this->transaction, $response, $response['transaction_id']);
                }

                if (isset($response['status'])) {

                    if (
                        static::STATUS_AUTH == $response['status']
                        || static::STATUS_CHARGED == $response['status']
                    ) {
                        $this->setTransactionTypeByStatus($this->transaction, $response['status']);
                        $status = static::COMPLETED;
                    }

                }
            }       

            $this->transaction->setXpcDataCell('xpc_deny_callbacks', '0');

        }

        return $status;
    }

    /**
     * Get redirect form URL
     *
     * @return string
     */
    protected function getFormURL()
    {
        return '';
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */
    protected function getFormFields()
    {
        return [];
    }
}
