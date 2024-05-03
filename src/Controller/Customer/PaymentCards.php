<?php

namespace Iidev\CloverPayments\Controller\Customer;

use \XLite\Core\Request;
use \XLite\Core\TopMessage;
use XLite\Core\Database;
use Qualiteam\SkinActXPaymentsConnector\Model\Payment\XpcTransactionData;
use Iidev\CloverPayments\Model\Payment\Processor\CloverPayments;
use Qualiteam\SkinActXPaymentsSubscriptions\Model\Subscription;

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

    /**
     * Check if it is Subscription card
     *
     * @return boolean
     */
    public function isSubscriptionCard($id)
    {
        $result = Database::getRepo(Subscription::class)->findOneBy([
            'card' => $id,
        ]);

        return $result ? true : false;
    }

    /**
     * Remove saved card
     *
     * @return void
     */
    protected function doActionRemove()
    {
        $cardId = Request::getInstance()->card_id;

        if ($cardId) {
            Database::getRepo(XpcTransactionData::class)->deleteById($cardId);

            TopMessage::addInfo('Saved card has been deleted');
        } else {
            TopMessage::addError('Failed to delete saved card');
        }

        $this->reloadPage();
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
            // temporary doCardSetupWithPromembership
            if (Request::getInstance()->membership_setup) {
                $status = $processor->doCardSetupWithPromembership($paymentMethod, $profile, $address);
            } else {
                $status = $processor->doCardSetup($paymentMethod, $profile, $address);
            }

            TopMessage::addInfo($status === 1 ? "Card added successfully." : "Please try another card.");
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
