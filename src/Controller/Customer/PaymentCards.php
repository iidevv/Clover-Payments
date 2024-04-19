<?php

namespace Iidev\CloverPayments\Controller\Customer;

use \XLite\Core\Request;
use \XLite\Core\TopMessage;
use XPay\XPaymentsCloud\Main as XPaymentsHelper;

class PaymentCards extends \XLite\Controller\Customer\ACustomer
{
    /**
     * Returns array with customer cards
     *
     * @return array
     */
    public function getCards()
    {
        return $this->getProfile()->getSavedCards();
    }
    public function isSaveCardsAllowed()
    {
        $savedCardsCount = count($this->getProfile()->getSavedCards());
        if ($savedCardsCount >= 5) {
            return false;
        }
        return true;
    }

    protected function doActionCardSetup()
    {
        \XLite\Core\Session::getInstance()->xpaymentsCardSetupData = null;

        $processor = null;

        /** @var \XLite\Model\Address $address */
        $address = \XLite\Core\Database::getRepo('XLite\Model\Address')->find(Request::getInstance()->addressId);

        if (
            $address
            && $address->getProfile()->getProfileId() === $this->getCustomerProfile()->getProfileId()
        ) {
            $response = '';
        } else {
            TopMessage::addError('Invalid profile address!');
        }

        $this->reloadPage(null);
    }

    /**
     * Sets hard redirect to reload the page
     *
     * @param string $url
     */
    protected function reloadPage($url = null)
    {
        if (is_null($url)) {
            $url = $this->buildURL('payment_cards');
        }

        $this->setHardRedirect();
        $this->setReturnURL($url);
        $this->doRedirect();
    }
}
