<?php

namespace Iidev\CloverPayments\Controller\Customer;

class PromembershipRenew extends \XLite\Controller\Customer\ACustomer
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
}