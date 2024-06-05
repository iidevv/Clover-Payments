<?php

namespace Iidev\CloverPayments\Model\Payment;

use Doctrine\ORM\Mapping as ORM;
use XLite\Model\Address;

/**
 *
 * @ORM\Entity
 * @ORM\Table  (name="clover_payment_transaction_data")
 */
class XpcTransactionData extends \XLite\Model\AEntity
{
    /**
     * Allow card usage for recharges 
     */
    const RECHARGE_TRUE  = 'Y';
    const RECHARGE_FALSE = 'N';

    /**
     * Unique ID
     *
     * @var integer
     *
     * @ORM\Id
     * @ORM\GeneratedValue (strategy="AUTO")
     * @ORM\Column         (type="integer", options={ "unsigned": true })
     */
    protected $id;

    /**
     * Masked credit card number 
     *
     * @var string
     *
     * @ORM\Column (type="string")
     */
    protected $card_number = '';

    /**
     * Type of the credit card
     *
     * @var string
     *
     * @ORM\Column (type="string")
     */
    protected $card_type = '';

    /**
     * Credit card expiration date
     *
     * @var string
     *
     * @ORM\Column (type="string")
     */
    protected $card_expire = '';

    /**
     * Allow card usage for recharges
     *
     * @var string
     *
     * @ORM\Column (type="string", options={ "fixed": true }, length=1)
     */
    protected $use_for_recharges = self::RECHARGE_FALSE;

    /**
     * Billing address 
     *
     * @var Address
     *
     * @ORM\ManyToOne  (targetEntity="XLite\Model\Address")
     * @ORM\JoinColumn (name="address_id", referencedColumnName="address_id", onDelete="SET NULL")
     */
    protected $billingAddress;

    /**
     * One-to-one relation with payment transaction
     *
     * @var \XLite\Model\Payment\Transaction
     *
     * @ORM\OneToOne  (targetEntity="XLite\Model\Payment\Transaction", inversedBy="xpc_data")
     * @ORM\JoinColumn (name="transaction_id", referencedColumnName="transaction_id", onDelete="CASCADE")
     */
    protected $transaction;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set card_number
     *
     * @param string $cardNumber
     * @return XpcTransactionData
     */
    public function setCardNumber($cardNumber)
    {
        $this->card_number = $cardNumber;
        return $this;
    }

    /**
     * Get card_number
     *
     * @return string 
     */
    public function getCardNumber()
    {
        return $this->card_number;
    }

    /**
     * Set card_type
     *
     * @param string $cardType
     * @return XpcTransactionData
     */
    public function setCardType($cardType)
    {
        $this->card_type = $cardType;
        return $this;
    }

    /**
     * Get card_type
     *
     * @return string 
     */
    public function getCardType()
    {
        return $this->card_type;
    }

    /**
     * Set card_expire
     *
     * @param string $cardExpire
     * @return XpcTransactionData
     */
    public function setCardExpire($cardExpire)
    {
        $this->card_expire = $cardExpire;
        return $this;
    }

    /**
     * Get card_expire
     *
     * @return string 
     */
    public function getCardExpire()
    {
        return $this->card_expire;
    }

    /**
     * Set use_for_recharges
     *
     * @param string $useForRecharges
     * @return XpcTransactionData
     */
    public function setUseForRecharges($useForRecharges)
    {
        $this->use_for_recharges = $useForRecharges;
        return $this;
    }

    /**
     * Get use_for_recharges
     *
     * @return string 
     */
    public function getUseForRecharges()
    {
        return $this->use_for_recharges;
    }

    /**
     * Set billingAddress
     *
     * @param Address $billingAddress
     * @return XpcTransactionData
     */
    public function setBillingAddress(Address $billingAddress = null)
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    /**
     * Get billingAddress
     *
     * @return Address
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * Set transaction
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @return XpcTransactionData
     */
    public function setTransaction(\XLite\Model\Payment\Transaction $transaction = null)
    {
        $this->transaction = $transaction;
        return $this;
    }

    /**
     * Get transaction
     *
     * @return \XLite\Model\Payment\Transaction 
     */
    public function getTransaction()
    {
        return $this->transaction;
    }
}
