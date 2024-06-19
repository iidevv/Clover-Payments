<?php

namespace Iidev\CloverPayments\Model\Payment\Processor;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use XLite\Core\Cache\ExecuteCachedTrait;
use XLite\Core\Database;
use XLite\Core\Request;
use XLite\Core\TopMessage;
use XLite\InjectLoggerTrait;
use XLite\Model\Payment\BackendTransaction;
use XLite\Model\Payment\Transaction;
use XLite\Model\Order;
use Iidev\CloverPayments\Core\APIException;
use Iidev\CloverPayments\Core\CloverPaymentsAPI;
use XLite\Model\Order\Status\Payment;

/**
 * CloverPayments processor
 *
 * Find the latest API document here:
 */
class CloverPayments extends \XLite\Model\Payment\Base\CreditCard
{
    use ExecuteCachedTrait;
    use InjectLoggerTrait;
    /**
     * Get allowed backend transactions
     *
     * @return string[] Status code
     */
    public function getAllowedTransactions()
    {
        return [
            BackendTransaction::TRAN_TYPE_CAPTURE,
            BackendTransaction::TRAN_TYPE_VOID,
            BackendTransaction::TRAN_TYPE_REFUND,
            BackendTransaction::TRAN_TYPE_REFUND_PART,
            BackendTransaction::TRAN_TYPE_REFUND_MULTI,
        ];
    }

    /**
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/Iidev/CloverPayments/config.twig';
    }

    /**
     * @return string
     */
    public function getConfigCallbackURL()
    {
        return \XLite::getInstance()->getShopURL(
            \XLite\Core\Converter::buildURL('callback', '', [], \XLite::getCustomerScript()),
            \XLite\Core\Config::getInstance()->Security->customer_security
        );
    }

    /**
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return string|boolean|null
     */
    public function getAdminIconURL(\XLite\Model\Payment\Method $method)
    {
        return true;
    }

    /**
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method)
            && $method->getSetting('mid')
            && $method->getSetting('username')
            && $method->getSetting('password')
            && $method->getSetting('soft_descriptor')
            && ($this->isTestMode($method) || \XLite\Core\Config::getInstance()->Security->customer_security);
    }

    /**
     * @return string|null
     */
    public function getInputTemplate()
    {
        return 'modules/Iidev/CloverPayments/checkout/checkout.twig';
    }

    /**
     * Get initial transaction type (used when customer places order)
     *
     * @param \XLite\Model\Payment\Method $method Payment method object OPTIONAL
     *
     * @return string
     */
    public function getInitialTransactionType($method = null)
    {
        return BackendTransaction::TRAN_TYPE_SALE;
    }

    /**
     * Do initial payment
     *
     * @return string Status code
     */
    protected function doInitialPayment()
    {
        $result = static::FAILED;

        try {
            $api = $this->getAPI();
            $data = $this->getInitialPaymentData();

            $response = $api->cardTransactionAuthCapture($data);

            if ($response['status'] === 'succeeded') {
                $result = static::COMPLETED;

                if ($this->getSetting('type') === self::OPERATION_SALE) {
                    $transaction = $this->transaction;
                    $backendTransaction = $transaction->createBackendTransaction(
                        BackendTransaction::TRAN_TYPE_SALE
                    );
                    $backendTransaction->setValue($transaction->getValue());
                    $backendTransaction->setStatus(BackendTransaction::STATUS_SUCCESS);
                }
            }
            $alignedData = $this->prepareDataToSave($response);
            $this->saveFilteredData($alignedData);

            if ($data['is-save-card'] && !$data['saved-card-select']) {
                $this->transaction->saveCard(
                    $alignedData['source_first6'],
                    $alignedData['source_last4'],
                    $alignedData['source_brand'],
                    $alignedData['source_exp_month'],
                    $alignedData['source_exp_year']
                );


                $this->transaction->getXpcData()->setBillingAddress($data['billing-address']);
                $this->transaction->getXpcData()->setUseForRecharges('Y');
            }

        } catch (APIException $e) {
            $this->transaction->setNote($e->getMessage());
            $this->transaction->setDataCell('status', $e->getMessage());

            $errors = [];

            foreach ($e->getCodes() as $k => $code) {
                $name = $e->getNames()[$k] ?? null;
                $description = $e->getMessages()[$k] ?? null;

                if ($processResult = $this->processErrorCode($code, $name, $description)) {
                    $errors[] = $processResult;
                }
            }

            if (!empty($errors)) {
                $this->transaction->setDataCell(
                    \Iidev\CloverPayments\Model\Payment\Transaction::CLOVERPAYMENTS_ERRORS_CELL,
                    json_encode(array_unique($errors)),
                    null,
                    \XLite\Model\Payment\TransactionData::ACCESS_CUSTOMER
                );
            }
        }

        return $result;
    }

    /**
     * Card Setup request
     *
     * @return string
     */
    public function doCardSetup($paymentMethod, \XLite\Model\Profile $profile, \XLite\Model\Address $address)
    {

        $setupStatus = 0;

        /** @var \XLite\Model\Currency $currency */
        $currency = Database::getRepo('XLite\Model\Currency')->findOneBy([
            'code' => 'USD'
        ]);

        $order = new Order();
        $order->setOrderNumber(Database::getRepo(Order::class)->findNextOrderNumber());
        $order->setProfile($profile);
        $order->setOrigProfile($profile);
        $order->setNotes('Card setup order.');
        $order->setCurrency($currency);
        $order->setTotal(1);
        $order->setPaymentStatus(Payment::STATUS_PAID);

        $order->create();

        $this->transaction = new Transaction();

        $this->transaction->setPaymentMethod($paymentMethod);
        $this->transaction->setCurrency($currency);
        $this->transaction->setValue(1);
        $this->transaction->setOrder($order);

        $transaction = $this->transaction;
        $backendTransaction = $transaction->createBackendTransaction(
            BackendTransaction::TRAN_TYPE_SALE
        );
        $backendTransaction->setValue($transaction->getValue());
        $backendTransaction->setStatus(BackendTransaction::STATUS_SUCCESS);

        try {
            $api = $this->getAPI();
            $data = $this->getInitialCardSetupData($profile, $address);

            $response = $api->cardTransactionAuthCapture($data);

            if ($response['status'] === 'succeeded') {
                $alignedData = $this->prepareDataToSave($response);
                $this->saveFilteredData($alignedData);

                $this->transaction->setPublicTxnId($alignedData['transaction-id']);

                $this->transaction->saveCard(
                    $alignedData['source_first6'],
                    $alignedData['source_last4'],
                    $alignedData['source_brand'],
                    $alignedData['source_exp_month'],
                    $alignedData['source_exp_year']
                );

                $transaction->getXpcData()->setBillingAddress($data['billing-address']);
                $transaction->getXpcData()->setUseForRecharges('Y');

                Database::getEM()->persist($transaction);
                Database::getEM()->flush();

                $maxRetries = 3;
                $retryCount = 0;
                $delay = 7000000;

                while ($retryCount < $maxRetries) {
                    usleep($delay);
                    $isRefunded = $api->refund($alignedData['transaction-id'], null);

                    if ($isRefunded) {
                        $order->setPaymentStatus(Payment::STATUS_REFUNDED);
                        $order->update();
                        $setupStatus = 1;
                        break;
                    }

                    $retryCount++;
                }

                if ($retryCount === $maxRetries) {
                    $order->setPaymentStatus(Payment::STATUS_AUTHORIZED);
                    $order->update();
                    $setupStatus = 0;
                }
            } else {
                $order->setPaymentStatus(Payment::STATUS_DECLINED);
                $order->update();

                $setupStatus = 0;
            }
        } catch (\Exception $e) {

            $setupStatus = 0;

            $this->getLogger('CloverPayments processCardSetup')->error(__FUNCTION__, [
                'request' => Request::getInstance()->getData(),
                'exceptionMessage' => $e->getMessage(),
            ]);
        }
        return $setupStatus;
    }

    protected function processErrorCode($code, $name = null, $description = null)
    {
        switch ($code) {
            case 'amount_too_large':
                return static::t("The transaction amount exceeds our limit. Please advise the customer to split the transaction into smaller amounts. If using a single-use card token, re-tokenize the card for any subsequent transaction.");
            case 'card_declined':
                switch ($name) {
                    case 'issuer_declined':
                        return $description;

                    default:
                        return static::t("The card was declined. Advise the customer to try a different card.");
                }
            case 'card_on_file_missing':
                return static::t("The customer does not have a stored payment method. Please ask them to provide card details for payment processing.");
            case 'charge_already_captured':
                return static::t("This charge has already been captured. No further action is required.");
            case 'charge_already_refunded':
                return static::t("This charge has already been refunded. No further action is required.");
            case 'email_invalid':
                return static::t("The provided email is invalid. Ask the customer to provide a valid email address.");
            case 'expired_card':
                return static::t("The card's expiration date has passed. Ask the customer to use a different card.");
            case 'incorrect_cvc':
                return static::t("The CVC number is incorrect. Ask the customer to enter the correct CVC.");
            case 'incorrect_number':
                return static::t("The card number is incorrect. Please have the customer enter a valid card number.");
            case 'invalid_card_type':
                return static::t("The card type is not recognized. Verify the card brand before proceeding.");
            case 'invalid_charge_amount':
                return static::t("The charge amount exceeds the allowed limit. Split the charge into smaller amounts if possible.");
            case 'invalid_request':
                return static::t("The card number provided is invalid. Ask the customer to re-enter their card details.");
            case 'invalid_tip_amount':
                return static::t("The tip amount is invalid. Ask the customer to enter a valid tip amount.");
            case 'invalid_tax_amount':
                return static::t("The tax amount is invalid. Verify the amount and try again.");
            case 'missing':
                return static::t("The token request failed. Allow the customer to retry the transaction.");
            case 'order_already_paid':
                return static::t("The order has already been paid. Inform the customer that no further action is needed.");
            case 'processing_error':
                return static::t("A processing error occurred. Ask the customer to try the request again.");
            case 'rate_limit':
                return static::t("We've hit our rate limit. Wait a moment before attempting another request.");
            case 'resource_missing':
                return static::t("The requested resource could not be found. Verify the request and try again.");
            case 'token_already_used':
                return static::t("The payment token has already been used. Ask the customer for new card details to generate a new token.");
        }

        return $description ?: null;
    }

    /**
     * @return string
     */
    protected function isSaveCard()
    {
        $request = Request::getInstance();

        return (bool) $request->save_card;
    }

    /**
     * @return string
     */
    protected function getSource()
    {
        $request = Request::getInstance();

        return $request->source;
    }

    /**
     * @return string
     */
    protected function getSavedCard()
    {
        $request = Request::getInstance();
        $card = $request->saved_card_select ? $request->saved_card_select : null;

        return $card;
    }

    /**
     * @return array
     */
    protected function getInitialPaymentData()
    {
        $currency = $this->transaction->getCurrency();
        $amount = $this->currencyFormat($this->transaction->getValue(), $currency);

        $profile = $this->transaction->getProfile();

        // set Shipping address same as billing if pro membership order only
        if (!$profile->getShippingAddress() && $profile->getBillingAddress()) {
            $profile->setShippingAddress($profile->getBillingAddress());
        }

        $billingAddress = $profile->getBillingAddress();
        $shippingAddress = $profile->getShippingAddress();

        $cardHolderInfo = $this->prepareAddress($billingAddress);
        $cardHolderInfo['email'] = $profile->getLogin();
        $cardHolderInfo['address'] = $cardHolderInfo['address1'];
        unset($cardHolderInfo['address1']);

        $shippingContactInfo = $this->prepareAddress($shippingAddress);

        $result = [
            'merchant-transaction-id' => $this->getTransactionId(),
            'source' => $this->getSource(),
            'saved-card-select' => $this->getSavedCard(),
            'is-save-card' => true,
            'amount' => $amount,
            'currency' => $currency->getCode(),
            'card-holder-info' => array_filter($cardHolderInfo),
            'billing-address' => $billingAddress,
            'transaction-fraud-info' => [
                'shipping-contact-info' => $shippingContactInfo,
                'shopper-ip-address' => Request::getInstance()->getClientIp(),
            ],
        ];

        return $result;
    }

    /**
     * @return array
     */
    protected function getInitialCardSetupData($profile, $address)
    {

        // set Shipping address same as billing if pro membership order only
        if (!$profile->getShippingAddress() && $address) {
            $profile->setShippingAddress($profile->getBillingAddress());
        }

        $billingAddress = $address;
        $shippingAddress = $profile->getShippingAddress();

        $cardHolderInfo = $this->prepareAddress($billingAddress);
        $cardHolderInfo['email'] = $profile->getLogin();
        $cardHolderInfo['address'] = $cardHolderInfo['address1'];
        unset($cardHolderInfo['address1']);

        $shippingContactInfo = $this->prepareAddress($shippingAddress);

        $result = [
            'merchant-transaction-id' => $this->getTransactionId(),
            'source' => $this->getSource(),
            'saved-card-select' => null,
            'is-save-card' => $this->isSaveCard(),
            'amount' => 1,
            'currency' => 'USD',
            'card-holder-info' => array_filter($cardHolderInfo),
            'billing-address' => $billingAddress,
            'transaction-fraud-info' => [
                'shipping-contact-info' => $shippingContactInfo,
                'shopper-ip-address' => Request::getInstance()->getClientIp(),
            ],
        ];

        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function prepareDataToSave(array $data)
    {
        $result = $this->alignArray($data);

        if (isset($result['id'])) {
            $result['transaction-id'] = $result['id'];
        }
        if (isset($result['source_id'])) {
            $result['card-token'] = $result['source_id'];
        }

        return $result;
    }

    /**
     * @param array  $data
     * @param string $prefix
     *
     * @return array
     */
    protected function alignArray(array $data, $prefix = '')
    {
        $result = [[]];
        $prefix = $prefix ? $prefix . '_' : '';
        foreach ($data as $name => $value) {
            $key = $prefix . $name;
            if (is_scalar($value)) {
                $result[0][$key] = $value;
            } elseif (is_array($value)) {
                $result[] = $this->alignArray($value, $key);
            }
        }

        return call_user_func_array('array_merge', $result);
    }

    /**
     * @param \XLite\Model\Address $address
     *
     * @return array
     */
    protected function prepareAddress(\XLite\Model\Address $address)
    {
        $countryCode = $address->getCountryCode();

        $street = $address->getStreet();
        if (strlen($street) > 100) {
            $address1 = substr($street, 0, 100);
            $address2 = substr($street, 99, 42);
        } else {
            $address1 = $street;
            $address2 = '';
        }

        $result = [
            'first-name' => $address->getFirstname(),
            'last-name' => $address->getLastname(),
            'country' => $address->getCountryCode(),
            'address1' => $address1,
            'city' => $address->getCity(),
            'zip' => $address->getZipcode(),
        ];

        if (in_array($countryCode, ['US', 'CA'], true) && $address->getState()) {
            $result['state'] = $address->getState()->getCode();
        }

        if ($address2) {
            $result['address2'] = $address2;
        }

        return $result;
    }

    /**
     * @param BackendTransaction $transaction Transaction
     *
     * @return boolean
     */
    protected function doCapture(BackendTransaction $transaction)
    {
        $result = false;

        try {
            Database::getEM()->transactional(function (EntityManager $em) use ($transaction, &$result) {
                $api = $this->getAPI();

                $paymentTransaction = $transaction->getPaymentTransaction();
                $em->lock($paymentTransaction, LockMode::PESSIMISTIC_WRITE);

                try {
                    $response = $api->cardTransactionCapture($paymentTransaction->getDetail('transaction-id'));

                    $processingInfo = $response['processing-info'];
                    if ($processingInfo['processing-status'] === 'SUCCESS') {
                        $transaction->setStatus(BackendTransaction::STATUS_SUCCESS);

                        $result = true;
                        TopMessage::getInstance()->addInfo('Payment has been captured successfully');
                    }
                } catch (APIException $e) {
                    TopMessage::getInstance()
                        ->addError('Transaction failure. CloverPayments response: ' . $e->getMessage() . ' Please contact CloverPayments support (ilya.i@skinact.com) for further assistance');
                }
            });
        } catch (\Exception $e) {
            $this->getLogger('CloverPayments')->error(__FUNCTION__, [
                'request' => Request::getInstance()->getData(),
                'exceptionMessage' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * @param BackendTransaction $transaction Transaction
     *
     * @return boolean
     */
    protected function doVoid(BackendTransaction $transaction)
    {
        $result = false;

        TopMessage::getInstance()
            ->addError('Transaction failure. Please contact CloverPayments support (ilya.i@skinact.com) for further assistance');

        return $result;
    }

    /**
     * Perform refund
     *
     * @param BackendTransaction $transaction Transaction
     *
     * @return bool
     */
    protected function performRefund(BackendTransaction $transaction)
    {
        $result = false;

        try {
            Database::getEM()->transactional(function (EntityManager $em) use ($transaction, &$result) {
                $api = $this->getAPI();

                $paymentTransaction = $transaction->getPaymentTransaction();
                $em->lock($paymentTransaction, LockMode::PESSIMISTIC_WRITE);

                try {
                    $response = $api->refund(
                        $paymentTransaction->getDetail('transaction-id'),
                        $transaction->getValue() < $paymentTransaction->getValue()
                        ? $transaction->getValue()
                        : null
                    );

                    if ($response) {
                        $transaction->setStatus(BackendTransaction::STATUS_SUCCESS);

                        $result = true;
                        TopMessage::getInstance()->addInfo('Payment has been refunded successfully');
                    }
                } catch (APIException $e) {
                    TopMessage::getInstance()
                        ->addError('Transaction failure. ' . $e->getMessage() . '. Please contact ilya.i@skinact.com for further assistance');
                }
            });
        } catch (\Exception $e) {
            $this->getLogger('CloverPayments')->error(__FUNCTION__, [
                'request' => Request::getInstance()->getData(),
                'exceptionMessage' => $e->getMessage(),
            ]);
        }

        if (!$result) {
            $transaction->setStatus(BackendTransaction::STATUS_FAILED);
        }

        return $result;
    }

    /**
     * @param BackendTransaction $transaction Transaction
     *
     * @return bool
     */
    protected function doRefund(BackendTransaction $transaction)
    {
        return $this->performRefund($transaction);
    }

    /**
     * @param BackendTransaction $transaction Transaction
     *
     * @return boolean
     */
    protected function doRefundPart(BackendTransaction $transaction)
    {
        return $this->performRefund($transaction);
    }

    /**
     * @param BackendTransaction $transaction Transaction
     *
     * @return boolean
     */
    protected function doRefundMulti(BackendTransaction $transaction)
    {
        return $this->performRefund($transaction);
    }

    /**
     *
     * @param Order $order Order which is recharged
     * @param Transaction $parentCardTransaction Transaction with saved card
     * @param float $amount Amount to recharge
     * @param boolean $sendCart Send or not cart in request OPTIONAL
     *
     * @return boolean
     */
    public function doRecharge(Order $order, Transaction $parentCardTransaction, $amount, $sendCart = true)
    {
        $this->getLogger('CloverPayments doRecharge')->error('getOrderId ' . $order->getOrderId());
        return false;
    }

    /**
     * Get callback request owner transaction or null
     *
     * @return Transaction|void
     */
    public function getCallbackOwnerTransaction()
    {
        $result = null;

        $request = \XLite\Core\Request::getInstance();
        if ($request->referenceNumber && $request->merchantTransactionId) {
            $this->getLogger('CloverPayments')->error(__FUNCTION__ . 'Callback', [
                'request' => \XLite\Core\Request::getInstance()->getData(),
            ]);

            try {
                $cardTransaction = \XLite\Core\Cache\ExecuteCached::executeCachedRuntime(
                    function () {
                        return $this->getAPI()->cardTransactionRetrieve(
                            \XLite\Core\Request::getInstance()->referenceNumber
                        );
                    },
                    'CloverPaymentsCardTransaction' . $request->referenceNumber
                );
                if ($cardTransaction['merchant-transaction-id'] === $request->merchantTransactionId) {
                    /** @var Transaction $result */
                    $result = Database::getRepo('XLite\Model\Payment\Transaction')->findOneBy(
                        ['public_id' => $request->merchantTransactionId]
                    );
                }
            } catch (APIException $e) {

            }
        }

        return $result;
    }

    /**
     * Check if we can process IPN right now or should receive it later
     *
     * @param \XLite\Model\Payment\Transaction $transaction Callback-owner transaction
     *
     * @return boolean
     */
    protected function canProcessIPN(\XLite\Model\Payment\Transaction $transaction)
    {
        return $transaction->getOrder()->getOrderNumber() && (
            $transaction->isEntityLockExpired(\XLite\Model\Payment\Transaction::LOCK_TYPE_IPN)
            || !$transaction->isEntityLocked(\XLite\Model\Payment\Transaction::LOCK_TYPE_IPN)
        );
    }

    /**
     * @param Transaction $transaction Callback-owner transaction
     *
     * @throws \XLite\Core\Exception\PaymentProcessing\CallbackNotReady
     */
    public function processCallback(Transaction $transaction)
    {
        $this->transaction = $transaction;

        if (!$this->canProcessIPN($transaction)) {
            throw new \XLite\Core\Exception\PaymentProcessing\CallbackNotReady();
        } else {
            $transaction->setEntityLock(\XLite\Model\Payment\Transaction::LOCK_TYPE_IPN);
        }

        parent::processCallback($transaction);

        $request = \XLite\Core\Request::getInstance();

        /** @var BackendTransaction $backendTransaction */
        $backendTransaction = null;
        $status = BackendTransaction::STATUS_SUCCESS;

        $transactionType = $request->transactionType;
        switch ($transactionType) {
            case 'REFUND':
            case 'CHARGEBACK':
                try {
                    Database::getEM()->transactional(function (EntityManager $em) use ($transaction, $transactionType) {
                        $isChargeback = $transactionType === 'CHARGEBACK';
                        $em->lock($transaction, LockMode::PESSIMISTIC_WRITE);

                        $em->refresh($transaction);
                        $em->refresh($transaction->getOrder());

                        $CloverPaymentsRefunds = [];
                        $cardTransaction = \XLite\Core\Cache\ExecuteCached::getRuntimeCache(
                            'CloverPaymentsCardTransaction' . \XLite\Core\Request::getInstance()->referenceNumber
                        );
                        /** @var array $refunds */
                        $refunds = $isChargeback
                            ? $cardTransaction['chargebacks']['chargeback']
                            : $cardTransaction['refunds']['refund'];
                        $refunds = key($refunds) === 0 ? $refunds : [$refunds];
                        foreach ($refunds as $refund) {
                            $CloverPaymentsRefunds[] = $refund['amount'];
                        }

                        $counts = array_count_values($this->getRefundedAmounts($transaction));
                        $unregisteredRefunds = array_values(
                            array_filter($CloverPaymentsRefunds, static function ($item) use (&$counts) {
                                return empty($counts[$item]) || !$counts[$item]--;
                            })
                        );

                        foreach ($unregisteredRefunds as $k => $refundAmount) {
                            /** @var BackendTransaction $tmpTransaction */
                            foreach ($transaction->getBackendTransactions() as $tmpTransaction) {
                                $tmpAmount = $this->currencyFormat(
                                    $tmpTransaction->getValue(),
                                    $transaction->getCurrency()
                                );
                                if (
                                    $refundAmount === $tmpAmount
                                    && $tmpTransaction->isRefund()
                                    && $tmpTransaction->getStatus() !== BackendTransaction::STATUS_SUCCESS
                                ) {
                                    $tmpTransaction->setStatus(BackendTransaction::STATUS_SUCCESS);
                                    unset($unregisteredRefunds[$k]);

                                    $tmpTransaction->registerTransactionInOrderHistory('callback, IPN');
                                }
                            }
                        }

                        $transactionAmount = $this->currencyFormat(
                            $transaction->getValue(),
                            $transaction->getCurrency()
                        );
                        foreach ($unregisteredRefunds as $refundAmount) {
                            $backendTransaction = $transaction->createBackendTransaction(
                                $refundAmount === $transactionAmount
                                ? BackendTransaction::TRAN_TYPE_REFUND
                                : BackendTransaction::TRAN_TYPE_REFUND_PART
                            );
                            $backendTransaction->setValue($refundAmount);
                            $backendTransaction->setStatus(BackendTransaction::STATUS_SUCCESS);

                            $backendTransaction->registerTransactionInOrderHistory('callback, IPN');
                        }

                        if (!empty($unregisteredRefunds) && $isChargeback) {
                            \XLite\Core\Mailer::sendCloverPaymentsChargeback(
                                $transaction->getOrder(),
                                \XLite\Core\Request::getInstance()->referenceNumber
                            );
                        }
                    });
                } catch (\Exception $e) {
                    $this->getLogger('CloverPayments')->error(__FUNCTION__, [
                        'request' => Request::getInstance()->getData(),
                        'exceptionMessage' => $e->getMessage(),
                    ]);
                }

                break;

            case 'CHARGE':
                $backendTransactions = $transaction->getBackendTransactions();
                /** @var BackendTransaction $tmpTransaction */
                foreach ($backendTransactions as $tmpTransaction) {
                    if (
                        $tmpTransaction->isCapture()
                        || $tmpTransaction->getType() === BackendTransaction::TRAN_TYPE_SALE
                    ) {
                        $backendTransaction = $tmpTransaction;
                        break;
                    }
                }

                break;
        }

        if ($backendTransaction) {
            $backendTransaction->registerTransactionInOrderHistory('callback, IPN');

            $this->setBackendTransactionStatus($backendTransaction, $status);
        }

        $transaction->unsetEntityLock(\XLite\Model\Payment\Transaction::LOCK_TYPE_IPN);
    }

    /**
     * @inheritdoc
     */
    public function processCallbackNotReady(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processCallbackNotReady($transaction);

        header('HTTP/1.1 409 Conflict', true, 409);
        header('Status: 409 Conflict');
        header('X-Robots-Tag: noindex, nofollow');
    }

    /**
     * @param Transaction $transaction
     *
     * @return float[]
     */
    protected function getRefundedAmounts(Transaction $transaction)
    {
        $result = [];

        /** @var BackendTransaction $tmpTransaction */
        foreach ($transaction->getBackendTransactions() as $tmpTransaction) {
            if (
                $tmpTransaction->isRefund()
                && $tmpTransaction->getStatus() === BackendTransaction::STATUS_SUCCESS
            ) {
                $result[] = $this->currencyFormat($tmpTransaction->getValue(), $transaction->getCurrency());
            }
        }

        return $result;
    }

    /**
     * @param BackendTransaction $transaction
     * @param string             $status
     */
    protected function setBackendTransactionStatus(BackendTransaction $transaction, $status)
    {
        try {
            Database::getEM()->transactional(static function (EntityManager $em) use ($transaction, $status) {
                $paymentTransaction = $transaction->getPaymentTransaction();

                $em->lock($paymentTransaction, LockMode::PESSIMISTIC_WRITE);
                $em->refresh($paymentTransaction->getOrder());

                $transaction->setStatus($status);
            });
        } catch (\Exception $e) {
            $this->getLogger('CloverPayments')->error(__FUNCTION__, [
                'request' => Request::getInstance()->getData(),
                'exceptionMessage' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Define saved into transaction data schema
     *
     * @return array
     */
    protected function defineSavedData()
    {
        $data = parent::defineSavedData();
        $data['transaction-id'] = 'CloverPayments identifier for the transaction';
        $data['card-token'] = 'Credit card token';

        return $data;
    }

    /**
     * @param float                 $value
     * @param \XLite\Model\Currency $currency
     *
     * @return string
     */
    protected function currencyFormat($value, $currency)
    {
        return number_format($currency->roundValue($value), 2, '.', '');
    }

    /**
     * @return CloverPaymentsAPI
     */
    protected function getAPI()
    {
        return $this->executeCachedRuntime(static function () {
            return new CloverPaymentsAPI(\Iidev\CloverPayments\Main::getMethodConfig());
        });
    }
}
