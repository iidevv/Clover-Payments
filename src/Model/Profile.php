<?php

namespace Iidev\CloverPayments\Model;

use Qualiteam\SkinActXPaymentsConnector\Core\ZeroAuth;
use Qualiteam\SkinActXPaymentsConnector\Model\Payment\XpcTransactionData;
use Qualiteam\SkinActXPaymentsConnector\Model\Repo\Payment\XpcTransactionData as XpcTransactionDataRepo;
use XLite\Core\CommonCell;
use XLite\Core\Database;
use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Profile extends \XLite\Model\Profile
{
     /**
     * Get list of saved credit cards
     *
     * @return array
     */
    public function getAllSavedCards()
    {
        $result = [];

        if ($this->getLogin()) {

            $cnd = new CommonCell();

            $class = XpcTransactionDataRepo::class;

            $cnd->{$class::SEARCH_RECHARGES_ONLY} = true;
            $cnd->{$class::SEARCH_PAYMENT_ACTIVE} = true;
            $cnd->{$class::SEARCH_PROFILE_ID} = $this->getProfileId();

            $cards = Database::getRepo(XpcTransactionData::class)
            ->findAll();

            foreach ($cards as $card) {

                $res = array(
                    'card_id'        => $card->getId(),
                    'card_number'    => $card->getCardNumber(),
                    'card_type'      => $card->getCardType(),
                    'card_type_css'  => strtolower($card->getCardType()),
                    'expire'         => $card->getCardExpire(),
                    'transaction_id' => $card->getTransaction()->getTransactionId(),
                    'init_action'    => $card->getTransaction()->getInitXpcAction(),
                );

                if ($card->getBillingAddress()) {
                    $res['address'] = ZeroAuth::getInstance()->getAddressItem($card->getBillingAddress());
                    $res['address_id'] = $card->getBillingAddress()->getAddressId();
                }

                $res['is_default'] = ($this->getDefaultCardId() == $res['card_id']);

                $result[] = $res;
            }
        }

        return $result;
    }
}