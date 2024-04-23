<?php

namespace Iidev\CloverPayments\Controller\Customer;

use \XLite\Core\Request;
use \XLite\Core\TopMessage;
use XLite\Core\Database;
use Iidev\CloverPayments\Model\Payment\Processor\CloverPayments;

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

    /**
     * Get payment method
     *
     * @return \XLite\Model\Payment\Method
     */
    protected function getPaymentMethod()
    {
        $class = CloverPayments::class;

        return Database::getRepo(\XLite\Model\Payment\Method::class)->findOneBy(
            [
                'class' => $class,
            ]
        );
    }

    protected function doActionCardSetup()
    {
        if (!Request::getInstance()->source)
            return;

        $paymentMethod = $this->getPaymentMethod();
        $processor = $this->getPaymentMethod()->getProcessor();

        $profile = $this->getProfile();


        /** @var \XLite\Model\Address $address */
        $address = Database::getRepo('XLite\Model\Address')->find(Request::getInstance()->addressId);

        if (
            $address
            && $address->getProfile()->getProfileId() === $this->getProfile()->getProfileId()
        ) {
            $processor->doCardSetup($paymentMethod, $profile, $address);
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
