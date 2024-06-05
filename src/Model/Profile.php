<?php

namespace Iidev\CloverPayments\Model;

use Doctrine\ORM\Mapping as ORM;
use Iidev\CloverPayments\Model\Payment\XpcTransactionData;
use Iidev\CloverPayments\Model\Repo\Payment\XpcTransactionData as XpcTransactionDataRepo;
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
     * Default card id 
     * 
     * @var integer
     *
     * @ORM\Column (type="integer", options={ "unsigned": true })
     */
    protected $default_card_id = 0;

    /**
     * Pending zero auth (card setup) reference
     *
     * @var string 
     *
     * @ORM\Column (type="string")
     */
    protected $pending_zero_auth = '';

    /**
     * Pending zero auth (card setup) txnId 
     *
     * @var string
     *
     * @ORM\Column (type="string")
     */
    protected $pending_zero_auth_txn_id = '';

    /**
     * Pending zero auth (card setup) status
     *
     * @var string
     *
     * @ORM\Column (type="string", options={ "fixed": true }, length=1)
     */
    protected $pending_zero_auth_status = '';

    /**
     * Pending zero auth (card setup) interface: cart or admin
     *
     * @var string
     *
     * @ORM\Column (type="string")
     */
    protected $pending_zero_auth_interface = '';

    /**
     * Get default card id 
     * 
     * @return integer
     */
    public function getDefaultCardId()
    {
        if (!$this->isCardIdValid($this->default_card_id)) {

            $cnd = new CommonCell();

            $class = XpcTransactionDataRepo::class;

            $cnd->{$class::SEARCH_RECHARGES_ONLY} = true;
            $cnd->{$class::SEARCH_PAYMENT_ACTIVE} = true;
            $cnd->{$class::SEARCH_PROFILE_ID} = $this->getProfileId();

            $cards = Database::getRepo(XpcTransactionData::class)
                ->search($cnd);

            if ($cards && $cards[0]) {
                $this->default_card_id = $cards[0]->getId();
            }

        }

        return $this->default_card_id;
    }

    /**
     * Get list of saved credit cards
     *
     * @return array
     */
    public function getSavedCards()
    {
        $result = [];

        if ($this->getLogin()) {

            $cnd = new CommonCell();

            $class = XpcTransactionDataRepo::class;

            $cnd->{$class::SEARCH_RECHARGES_ONLY} = true;
            $cnd->{$class::SEARCH_PAYMENT_ACTIVE} = true;
            $cnd->{$class::SEARCH_PROFILE_ID} = $this->getProfileId();

            $cards = Database::getRepo(XpcTransactionData::class)
                ->search($cnd);

            foreach ($cards as $card) {

                $res = array(
                    'card_id' => $card->getId(),
                    'invoice_id' => $card->getTransaction()->getOrder()->getOrderNumber(),
                    'order_id' => $card->getTransaction()->getOrder()->getOrderId(),
                    'profile_id' => $card->getTransaction()->getOrder()->getProfile()->getProfileId(),
                    'card_number' => $card->getCardNumber(),
                    'card_type' => $card->getCardType(),
                    'card_type_css' => strtolower($card->getCardType()),
                    'expire' => $card->getCardExpire(),
                    'transaction_id' => $card->getTransaction()->getTransactionId(),
                    'init_action' => $card->getTransaction()->getInitXpcAction(),
                );

                if ($card->getBillingAddress()) {
                    $res['address_id'] = $card->getBillingAddress()->getAddressId();
                }

                $res['is_default'] = ($this->getDefaultCardId() == $res['card_id']);

                $result[] = $res;
            }
        }

        return $result;
    }

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

    /**
     * Allow recharges for this card
     *
     * @param integer $cardId Card id
     *
     * @return boolean
     */
    public function allowRecharge($cardId)
    {
        return $this->setRecharge($cardId, 'Y');
    }

    /**
     * Deny recharges for this card
     *
     * @param integer $cardId Card id
     *
     * @return boolean
     */
    public function denyRecharge($cardId)
    {
        return $this->setRecharge($cardId, 'N');
    }

    /**
     * Set recharge 
     * 
     * @param integer $cardId   Card id
     * @param string  $recharge Recharge flag
     *  
     * @return boolean
     */
    protected function setRecharge($cardId, $recharge)
    {
        $class = XpcTransactionData::class;
        $xpcTransaction = Database::getRepo($class)->find(intval($cardId));

        $result = false;

        if (
            $xpcTransaction
            && $this->isCardIdValid($cardId)
        ) {
            $xpcTransaction->setUseForRecharges($recharge);
            Database::getEM()->flush();

            $result = true;
        }

        return $result;

    }


    /**
     * Set default_card_id
     *
     * @param integer $defaultCardId
     * @return Profile
     */
    public function setDefaultCardId($defaultCardId)
    {
        $this->default_card_id = $defaultCardId;
        return $this;
    }

}
