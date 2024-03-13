<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\Core;

use XLite\Core\Translation;
use XLite\InjectLoggerTrait;

/**
 * http://developers.bluesnap.com/v2.0/docs
 * @todo: rewrite to http://developers.bluesnap.com/v2.1/docs
 */
class CloverPaymentsAPI
{
    use InjectLoggerTrait;

    public const CARD_TRANSACTION_AUTH_CAPTURE  = 'AuthCapture';
    public const CARD_TRANSACTION_AUTH_ONLY     = 'AuthOnly';
    public const CARD_TRANSACTION_CAPTURE       = 'Capture';
    public const CARD_TRANSACTION_AUTH_REVERSAL = 'AuthReversal';
    public const CARD_TRANSACTION_RETRIEVE      = 'Retrieve';

    public const BLUESNAP_SESSION_CELL_NAME = 'BlueSnap_Token';

    public const TOKEN_TTL = 3540; // 59 minutes

    /**
     * @var array
     */
    protected $config;

    /**
     * @param array $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * http://developers.bluesnap.com/v4.0/docs/hosted-payment-fields#section-2-add-the-bluesnap-javascript-file-to-your-checkout-form
     *
     * @return string
     */
    public function getJSURL()
    {
        //return $this->getCloverPaymentsDomainPath() . '/services/hosted-payment-fields/v2.0/bluesnap.hpf.min.js';
        return $this->getCloverPaymentsDomainPath() . '/sdk.js';
    }

    /**
     * http://developers.bluesnap.com/v4.0/docs/hosted-payment-fields#section-implementing-hosted-payment-fields-in-your-checkout-form
     *
     * @return string
     */
    private function getCloverPaymentsDomainPath()
    {
        return $this->config['mode'] === \XLite\View\FormField\Select\TestLiveMode::TEST
            ? 'https://checkout.sandbox.dev.clover.com'
            : 'https://checkout.clover.com/sdk.js';
    }

    // {{{ Token

    /**
     * @return string
     */
    public function getToken()
    {
        $session = \XLite\Core\Session::getInstance();
        $token   = $session->get(static::BLUESNAP_SESSION_CELL_NAME);

        if (!$token || !$this->isTokenValid($token)) {
            $tokenValue = $this->generateToken();
            if ($tokenValue) {
                $token = [LC_START_TIME + static::TOKEN_TTL, $this->generateFraudSessionId(), $tokenValue];
                $session->set(static::BLUESNAP_SESSION_CELL_NAME, $token);
            }
        }

        return $token ? array_pop($token) : null;
    }

    /**
     * @return string
     */
    public function getFraudSessionId()
    {
        $session = \XLite\Core\Session::getInstance();
        $token   = $session->get(static::BLUESNAP_SESSION_CELL_NAME);

        if (!$token || !$this->isTokenValid($token)) {
            $this->getToken();
            $token = $session->get(static::BLUESNAP_SESSION_CELL_NAME);
        }

        return $token[1];
    }

    /**
     * @param array|null $token
     *
     * @return bool
     */
    public function isTokenValid($token = null)
    {
        if ($token === null) {
            $session = \XLite\Core\Session::getInstance();
            $token   = $session->get(static::BLUESNAP_SESSION_CELL_NAME);
        }

        return $token && array_shift($token) > LC_START_TIME;
    }

    /**
     * @return string|null
     */
    public function generateToken()
    {
        $result = null;

        try {
            $response = $this->doRequest('POST', 'services/2/payment-fields-tokens');
            $location = $response->headers->Location;
            if ($location && preg_match('/([^\/]+)$/', $location, $matches)) {
                $result = $matches[1];
            }
        } catch (APIException $e) {
            return null;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function generateFraudSessionId()
    {
        return md5(LC_START_TIME . $this->config['username']);
    }

    public static function dropToken()
    {
        $session = \XLite\Core\Session::getInstance();
        unset($session->{static::BLUESNAP_SESSION_CELL_NAME});
    }

    // }}}

    // {{{ Card transaction

    /**
     * http://developers.bluesnap.com/v2.0/docs/auth-only
     *
     * @param array $data
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionAuthOnly(array $data)
    {
        if (!$this->isTokenValid()) {
            throw new APIException('Token is invalid');
        }

        $data['card-transaction-type'] = 'AUTH_ONLY';
        $data['recurring-transaction'] = 'ECOMMERCE';

        if (!empty($this->config['soft_descriptor'])) {
            $data['soft-descriptor'] = $this->config['soft_descriptor'];
        }

        $data['transaction-fraud-info']['fraud-session-id'] = $this->getFraudSessionId();

        $data['pf-token'] = $this->getToken();

        $body = CloverPaymentsXML::toXML(['card-transaction' => $data]);
        $result = $this->doRequest('POST', 'services/2/transactions', $body);

        return CloverPaymentsXML::stringToArray($result->body);
    }

    /**
     * http://developers.bluesnap.com/v2.0/docs/auth-capture
     *
     * @param array $data
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionAuthCapture(array $data)
    {
        if (!$this->isTokenValid()) {
            throw new APIException('Token is invalid');
        }

        $data['card-transaction-type'] = 'AUTH_CAPTURE';
        $data['recurring-transaction'] = 'ECOMMERCE';

        if (!empty($this->config['soft_descriptor'])) {
            $data['soft-descriptor'] = $this->config['soft_descriptor'];
        }

        $data['transaction-fraud-info']['fraud-session-id'] = $this->getFraudSessionId();

        $data['pf-token'] = $this->getToken();

        $body = CloverPaymentsXML::toXML(['card-transaction' => $data]);
        $result = $this->doRequest('POST', 'services/2/transactions', $body);

        return CloverPaymentsXML::stringToArray($result->body);
    }

    /**
     * http://developers.bluesnap.com/v2.0/docs/capture
     *
     * @param string $transactionId
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionCapture($transactionId)
    {
        $data = [
            'card-transaction' => [
                'card-transaction-type' => 'CAPTURE',
                'transaction-id'        => $transactionId,
            ],
        ];

        $result = $this->doRequest('PUT', 'services/2/transactions', CloverPaymentsXML::toXML($data));

        return CloverPaymentsXML::stringToArray($result->body);
    }

    /**
     * http://developers.bluesnap.com/v2.0/docs/auth-reversal
     *
     * @param string $transactionId
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionAuthReversal($transactionId)
    {
        $data = [
            'card-transaction' => [
                'card-transaction-type' => 'AUTH_REVERSAL',
                'transaction-id'        => $transactionId,
            ],
        ];

        $result = $this->doRequest('PUT', 'services/2/transactions', CloverPaymentsXML::toXML($data));

        return CloverPaymentsXML::stringToArray($result->body);
    }

    /**
     * http://developers.bluesnap.com/v2.0/docs/retrieve
     *
     * @param string $transactionId
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionRetrieve($transactionId)
    {
        $path   = sprintf('services/2/transactions/%s', $transactionId);
        $result = $this->doRequest('GET', $path);

        return CloverPaymentsXML::stringToArray($result->body);
    }

    // }}}

    // {{{ Refund

    /**
     * http://developers.bluesnap.com/v2.0/docs/refund
     *
     * @param string $transactionId
     * @param string $amount
     *
     * @return boolean
     * @throws APIException
     */
    public function refund($transactionId, $amount = null)
    {
        if (!is_null($amount)) {
            $path = sprintf('services/2/transactions/%s/refund?amount=%s', $transactionId, $amount);
        } else {
            $path = sprintf('services/2/transactions/%s/refund', $transactionId);
        }
        $result = $this->doRequest('PUT', $path);

        return (int) $result->code === 204;
    }

    // }}}

    /**
     * @param string $method
     * @param string $path
     * @param string $data
     *
     * @return \PEAR2\HTTP\Request\Response
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    protected function doRequest($method, $path, $data = '')
    {
        $url = $this->getCloverPaymentsDomainPath() . '/' . $path;

        $request = new \XLite\Core\HTTP\Request($url);

        $request->verb = $method;

        $auth = base64_encode(sprintf('%s:%s', $this->config['username'], $this->config['password']));
        $request->setHeader('Authorization', sprintf('Basic %s', $auth));
        $request->setHeader('Content-Type', 'application/xml');
        $request->setHeader('bluesnap-version', '2.0');

        $request->body = $data;

        $this->getLogger('XC-BlueSnap')->error(__FUNCTION__ . 'Request', [
            $method,
            $url,
            $request->headers,
            $request->body,
        ]);

        $response = $request->sendRequest();

        $this->getLogger('XC-BlueSnap')->error(__FUNCTION__ . 'Response', [
            $method,
            $url,
            $response ? $response->headers : 'empty',
            $response ? $response->body : 'empty',
            $request->getErrorMessage(),
        ]);

        if (!$response || !in_array((int) $response->code, [200, 201, 204], true)) {
            if (!$response || in_array((int) $response->code, [403, 500], true)) {
                throw new APIException(Translation::lbl('Unfortunately, an error occurred and your order could not be placed at this time. Please try again, or contact our support team.'));
            } elseif ($response->body) {
                $message = $this->getErrorMessages($response->body);

                if ($message) {
                    throw new APIException($message);
                }
            }

            throw new APIException($request->getErrorMessage(), $response->code);
        }

        return $response;
    }

    /**
     * @param string $xml
     *
     * @return array[]|string
     */
    protected function getErrorMessages($xml)
    {
        $result = [];

        try {
            $data = CloverPaymentsXML::stringToArray($xml);
            if ($data['message']) {
                $result = is_int(key($data['message'])) ? $data['message'] : [$data['message']];
            }
        } catch (APIException $e) {
            $result = $e->getMessage();
        }

        return $result;
    }
}
