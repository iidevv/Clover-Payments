<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\Model;

use Qualiteam\SkinActXPaymentsConnector\Model\Payment\XpcTransactionData;
use Qualiteam\SkinActXPaymentsConnector\Model\Repo\Payment\XpcTransactionData as XpcTransactionDataRepo;
use XLite\Core\CommonCell;
use XLite\Core\Database;
use XCart\Extender\Mapping\Extender;

/**
 * XPayments payment processor
 *
 * @Extender\Mixin
 */
class Profile extends \XLite\Model\Profile
{
    /**
     * Checks if this card belongs to the current profile
     *
     * @param integer $cardId Card id
     *
     * @return boolean
     */
    public function isCardIdValid($cardId)
    {
        // if (empty($this->default_card_id)) {
        //     return false;
        // }

        $cnd = new CommonCell();

        $class = XpcTransactionDataRepo::class;

        $cnd->{$class::SEARCH_RECHARGES_ONLY} = true;
        $cnd->{$class::SEARCH_PAYMENT_ACTIVE} = true;
        $cnd->{$class::SEARCH_CARD_ID} = $cardId;
        $cnd->{$class::SEARCH_PROFILE_ID} = $this->getProfileId();

        $valid = Database::getRepo(XpcTransactionData::class)
            ->search($cnd, true);

        return !empty($valid);
    }

    public function isMembershipMigrationProfile()
    {

        $isMembershipMigrationProfile = false;

        $login = $this->getLogin();

        /** @var \Iidev\CloverPayments\Model\MembershipMigrate $preProfile */
        $preProfile = Database::getRepo('Iidev\CloverPayments\Model\MembershipMigrate')->findOneBy([
            'login' => $login
        ]);

        if ($preProfile && $preProfile->getPaidMembershipId() === 9 && $preProfile->getStatus() === '') {
            $isMembershipMigrationProfile = true;
        }

        return $isMembershipMigrationProfile;
    }
    public function setMembershipMigrationProfileComplete()
    {
        if ($this->isMembershipMigrationProfile())
            return null;

        $login = $this->getLogin();

        /** @var \Iidev\CloverPayments\Model\MembershipMigrate $preProfile */
        $preProfile = Database::getRepo('Iidev\CloverPayments\Model\MembershipMigrate')->findOneBy([
            'login' => $login
        ]);

        $preProfile->setStatus("MIGRATION_COMPLETE");

        return true;
    }

    public function getMembershipMigrationProfileExpirationDate()
    {
        if ($this->isMembershipMigrationProfile())
            return null;

        $login = $this->getLogin();

        /** @var \Iidev\CloverPayments\Model\MembershipMigrate $preProfile */
        $preProfile = Database::getRepo('Iidev\CloverPayments\Model\MembershipMigrate')->findOneBy([
            'login' => $login
        ]);

        return $preProfile->getPaidMembershipExpire();
    }
}
