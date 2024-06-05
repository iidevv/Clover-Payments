<?php

namespace Iidev\CloverPayments\Core;

use Exception;

class APIException extends \Exception
{
    /**
     * @var array
     */
    protected $messages = [];

    /**
     * @var array
     */
    protected $codes = [];

    /**
     * @var array
     */
    protected $names = [];

    /**
     * APIException constructor.
     *
     * @param string|array   $message
     * @param int            $code
     * @param Exception|null $previous
     */
    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        $messages = [];
        $codes = [];
        $names = [];

        if (is_string($message)) {
            $message = json_decode($message, true);
        }

        if (is_array($message)) {
            if (isset ($message['message'], $message['code'], $message['error-name'])) {
                $messages[] = $message['message'];
                $codes[] = $message['code'];
                $names[] = $message['error-name'];
            } else {
                foreach ($message as $m) {
                    if (is_array($m) && isset ($m['message'], $m['code'], $m['error-name'])) {
                        $messages[] = $m['message'];
                        $codes[] = $m['code'];
                        $names[] = $m['error-name'];
                    }
                }
            }
        } else {
            $this->messages[] = (string) $message;
        }

        $this->messages = $messages;
        $this->codes = $codes;
        $this->names = $names;

        parent::__construct(trim(implode('; ', $messages)), $code, $previous);
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * @param array $messages
     */
    public function setMessages($messages)
    {
        $this->messages = $messages;
    }

    /**
     * Return Codes
     *
     * @return array
     */
    public function getCodes()
    {
        return $this->codes;
    }

    /**
     * Set Codes
     *
     * @param array $codes
     *
     * @return $this
     */
    public function setCodes($codes)
    {
        $this->codes = $codes;
        return $this;
    }

    /**
     * Return Names
     *
     * @return array
     */
    public function getNames()
    {
        return $this->names;
    }

    /**
     * Set Names
     *
     * @param array $names
     *
     * @return $this
     */
    public function setNames($names)
    {
        $this->names = $names;
        return $this;
    }
}
