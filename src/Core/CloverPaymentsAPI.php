<?php

namespace Iidev\CloverPayments\Core;

use XLite\Core\Translation;
use XLite\InjectLoggerTrait;

/**
 * CloverPaymentsAPI
 */
class CloverPaymentsAPI
{
    use InjectLoggerTrait;

    public const CARD_TRANSACTION_AUTH_CAPTURE = 'AuthCapture';
    public const CARD_TRANSACTION_AUTH_ONLY = 'AuthOnly';
    public const CARD_TRANSACTION_CAPTURE = 'Capture';
    public const CARD_TRANSACTION_RETRIEVE = 'Retrieve';
    public const CLOVERPAYMENTS_SESSION_CELL_NAME = 'CloverPayments_Token';
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
     * @return string
     */
    public function getJSURL()
    {
        return $this->config['mode'] === \XLite\View\FormField\Select\TestLiveMode::TEST
            ? 'https://checkout.sandbox.dev.clover.com/sdk.js'
            : 'https://checkout.clover.com/sdk.js';
    }

    /**
     * @return string
     */
    private function getCloverPaymentsDomainPath()
    {
        return $this->config['mode'] === \XLite\View\FormField\Select\TestLiveMode::TEST
            ? 'https://scl-sandbox.dev.clover.com'
            : 'https://scl.clover.com';
    }

    // {{{ Token

    /**
     * @return string
     */
    public function getToken()
    {
        $session = \XLite\Core\Session::getInstance();
        $token = $session->get(static::CLOVERPAYMENTS_SESSION_CELL_NAME);

        if (!$token || !$this->isTokenValid($token)) {
            $tokenValue = $this->generateToken();
            if ($tokenValue) {
                $token = [LC_START_TIME + static::TOKEN_TTL, $this->generateFraudSessionId(), $tokenValue];
                $session->set(static::CLOVERPAYMENTS_SESSION_CELL_NAME, $token);
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
        $token = $session->get(static::CLOVERPAYMENTS_SESSION_CELL_NAME);

        if (!$token || !$this->isTokenValid($token)) {
            $this->getToken();
            $token = $session->get(static::CLOVERPAYMENTS_SESSION_CELL_NAME);
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
            $token = $session->get(static::CLOVERPAYMENTS_SESSION_CELL_NAME);
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

    // }}}

    // {{{ Card transaction

    protected function getAlignedAmount($amount)
    {
        $amount = (string) ($amount * 100);
        return $amount;
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function saveCardData(array $data)
    {

        // if (!empty($this->config['soft_descriptor'])) {
        //     $data['soft-descriptor'] = $this->config['soft_descriptor'];
        // }

        $headers = [
            'x-forwarded-for' => $data['transaction-fraud-info']['shopper-ip-address'],
        ];


        $body = [
            'firstName' => $data['card-holder-info']['first-name'],
            'lastName' => $data['card-holder-info']['last-name'],
            'email' => $data['card-holder-info']['email'],
            'source' => $data['source'],
        ];

        $result = $this->doRequest('POST', 'v1/customers', json_encode($body), $headers);

        return json_decode($result->body, true);
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionAuthCapture(array $data)
    {

        $description = null;

        if (!empty($this->config['soft_descriptor'])) {
            $description = $this->config['soft_descriptor'];
        }

        $customerId = null;

        if ($data['saved-card-select']) {

            $customer = \XLite\Core\Database::getRepo(\XLite\Model\Payment\TransactionData::class)
                ->findOneBy(['transaction' => $data['saved-card-select'], 'name' => 'card-token']);

            if ($customer) {
                $customerId = $customer->getValue();
            }
        }

        if ($data['is-save-card'] && !$customerId) {
            $result = $this->saveCardData($data);
            $customerId = $result['id'];
        }

        $headers = [
            'x-forwarded-for' => $data['transaction-fraud-info']['shopper-ip-address'],
        ];


        $body = [
            'amount' => $this->getAlignedAmount($data['amount']),
            'source' => $customerId ? $customerId : $data['source'],
            'description' => $description ? $description : "",
            'currency' => $data['currency']
        ];

        $result = $this->doRequest('POST', 'v1/charges', json_encode($body), $headers);
        $resultData = json_decode($result->body, true);

        if ($customerId) {
            $resultData['customer_id'] = $customerId;
        }

        return $resultData;
    }

    /**
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionCapture($transactionId)
    {
        return [];
    }

    /**
     * @param string $transactionId
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public function cardTransactionRetrieve($transactionId)
    {
        return [];
    }

    // }}}

    // {{{ Refund

    /**
     *
     * @param string $transactionId
     * @param string $amount
     *
     * @return boolean
     * @throws APIException
     */
    public function refund($transactionId, $amount = null)
    {
        $body = [
            'charge' => $transactionId
        ];

        if (!is_null($amount)) {
            $body['amount'] = $this->getAlignedAmount($amount);
        }

        $result = $this->doRequest('POST', 'v1/refunds', json_encode($body));

        return (int) $result->code === 200;
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
    protected function doRequest($method, $path, $data = '', $headers = [])
    {
        $this->getLogger('CloverPayments')->debug(__FUNCTION__ . 'Request', [
            $method,
            $path,
            $data
        ]);

        $url = $this->getCloverPaymentsDomainPath() . '/' . $path;

        $request = new \XLite\Core\HTTP\Request($url);

        $request->verb = $method;

        $request->setHeader('authorization', sprintf('Bearer %s', $this->config['password']));
        $request->setHeader('Content-Type', 'application/json');

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                $request->setHeader($key, $value);
            }
        }

        $request->body = $data;

        $this->getLogger('CloverPayments')->debug(__FUNCTION__ . 'Request', [
            $method,
            $url,
            $request->headers,
            $request->body,
        ]);

        $response = $request->sendRequest();

        $this->getLogger('CloverPayments')->debug(__FUNCTION__ . 'Response', [
            $method,
            $url,
            $response ? $response->headers : 'empty',
            $response ? $response->body : 'empty',
            $request->getErrorMessage(),
        ]);

        if (!$response || !in_array((int) $response->code, [200, 201, 204], true)) {

            $this->getLogger('CloverPayments')->error(__FUNCTION__ . 'Response', [
                $method,
                $url,
                $request->headers,
                $request->body,
                $response ? $response->headers : 'empty',
                $response ? $response->body : 'empty',
                $request->getErrorMessage(),
            ]);

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
     * @param string $json
     *
     * @return array[]|string
     */
    protected function getErrorMessages($json)
    {
        $result = [];
        try {
            $data = json_decode($json, true);
            if (isset($data['failure_reason'])) {
                $result = [
                    'code' => $data['failure_reason'] ?? '',
                    'message' => $data['description'] ?? '',
                    'error-name' => $data['failure_reason'] ?? '',
                ];
            } else if (isset($data['message'])) {
                $result = $data['message'];
            }
        } catch (APIException $e) {
            $result = $e->getMessage();
        }
        return $result;
    }
}
