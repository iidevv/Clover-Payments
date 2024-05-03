<?php

namespace Iidev\CloverPayments\Controller\Customer;

use XCart\Extender\Mapping\Extender;
use XLite\Core\Database;

/**
 * Extends customer base controller to show a message on all customer pages
 * @Extender\Mixin
 */
class MembershipUpdateReminder extends \XLite\Controller\Customer\ACustomer
{
    private function isRenewPage()
    {
        return strpos($this->getURL(), "promembership_renew") ? true : false;
    }

    public function handleRequest()
    {

        parent::handleRequest();

        if ($this->getProfile() && $this->getProfile()->isMembershipMigrationProfile() && !$this->isAJAX() && !$this->isRenewPage()) {
            $this->showMessageForPromembers();
        }

    }

    public function showMessageForPromembers()
    {
        $message = '<b>Action required:</b> please update your membership.<br>';
        $description = 'Welcome to our new platform! To continue enjoying all the features of Pro Membership, please update your payment details. <a href="/?target=promembership_renew">Renew Your Pro Membership</a>.';
        \XLite\Core\TopMessage::getInstance()->addInfo($message . ' ' . $description);
    }

}