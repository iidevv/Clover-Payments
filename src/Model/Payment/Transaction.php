<?php

namespace Iidev\CloverPayments\Model\Payment;

use XCart\Extender\Mapping\Extender;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Iidev\CloverPayments\Model\Payment\Processor\SavedCard;
use XLite\Model\Payment\TransactionData;

/**
 * @Extender\Mixin
 */
class Transaction extends \XLite\Model\Payment\Transaction
{
    public const CLOVERPAYMENTS_ERRORS_CELL = 'CloverPaymentsErrors';
    /**
     * @return array|null
     */
    public function getCloverPaymentsFailureReasons()
    {
        $cell = $this->getDataCell(static::CLOVERPAYMENTS_ERRORS_CELL);

        if ($cell && $cell->getValue()) {
            return (array) @json_decode($cell->getValue());
        }

        return null;
    }

    /**
     * Fields for transaction note is limited by 255 characters in database
     */
    const NOTE_LIMIT = 255;

    /**
     * X-Payments custom data cells
     *
     * @var XpcDataCell
     *
     * @ORM\OneToMany (targetEntity="\Iidev\CloverPayments\Model\Payment\XpcDataCell", mappedBy="transaction", cascade={"all"})
     */
    protected $xpc_data_cells;

    /**
     * One-to-one relation with X-Payments transaction details
     *
     * @var XpcTransactionData
     *
     * @ORM\OneToOne  (targetEntity="\Iidev\CloverPayments\Model\Payment\XpcTransactionData", mappedBy="transaction", cascade={"all"})
     */
    protected $xpc_data;

    /**
     * Constructor
     *
     * @param array $data Entity properties OPTIONAL
     *
     * @return void
     */
    public function __construct(array $data = [])
    {
        $this->xpc_data_cells = new ArrayCollection();

        parent::__construct($data);
    }

    /**
     * @return boolean
     */
    public function isXpc($includeSavedCardsMethod = false)
    {
        if ($includeSavedCardsMethod) {
            $xpClass[] = SavedCard::class;
        } 

        return $this->getPaymentMethod()
            && in_array($this->getPaymentMethod()->getClass(), $xpClass);
    }


    /**
     * Check - transaction is for Save card in profile ("Zero auth") feature
     * 
     * @return boolean
     */
    public function isPendingZeroAuth()
    {
        return (
            $this->getProfile()
            && $this->getProfile()->getPendingZeroAuth() == $this->getPublicId()
        );
    }

    /**
     * Save card in details
     *
     * @return void
     */
    public function saveCard($first6, $last4, $type, $expireMonth = '', $expireYear = '')
    {
        if (!$this->getXpcData()) {
            
            $xpcData = new XpcTransactionData();
            $xpcData->setTransaction($this);

            $this->xpc_data = $xpcData;
        }

        $xpcData = $this->getXpcData();

        $xpcData->setCardNumber($first6 . '******' . $last4);
        $xpcData->setCardType($type);
        
        if ($expireMonth && $expireYear) {

            // If this is changed, correct Model\Payment\Processor\AXPayments::copyMaskedCard() as well
            $xpcData->setCardExpire($expireMonth . '/' . $expireYear);
        }
    }

    /**
     * Get saved credit card 
     *
     * @return array
     */
    public function getCard($forRechargesOnly = false)
    {
        $result = false;

        if (
            $this->getXpcData()
            && (
                !$forRechargesOnly 
                || 'Y' == $this->getXpcData()->getUseForRecharges()
            )
        ) {

            $result = array(
                'card_id'           => $this->getXpcData()->getId(),
                'card_number'       => $this->getXpcData()->getCardNumber(),
                'card_type'         => $this->getXpcData()->getCardType(),
                'card_type_css'     => strtolower($this->getXpcData()->getCardType()),
                'use_for_recharges' => $this->getXpcData()->getUseForRecharges(),
                'expire'            => $this->getXpcData()->getCardExpire(),
            );
        }

        return $result;
    }

    /**
     * Get initial action of a transaction. Was it first authorized or charged.
     *
     * @return string
     */
    public function getInitXpcAction()
    {
        if (
            $this->getXpcDataCell('xpc_authorized')
            && $this->getXpcDataCell('xpc_authorized')->getValue() > \XLite\Model\Order::ORDER_ZERO
        ) {
            $action = 'authorize';
        } else {
            $action = 'charge';
        }

        return $action;
    }

    /**
     * Get transaction xpc_ values. What was actually authorized, paid, voided, and refunded.
     *
     * @return array 
     */
    public function getXpcValues()
    {

        $fields = array(
            'authorized' => 0,
            'captured' => 0,
            'charged' => 0,
            'paid' => 0,
            'voided' => 0,
            'refunded' => 0,
        );

        foreach ($fields as $key => $unused) {
            $fields[$key] = $this->getXpcDataCell('xpc_' . $key)
                ? floatval($this->getXpcDataCell('xpc_' . $key)->getValue())
                : 0;

        }

        if ($this->getXpcDataCell('xpc_paid')) {
            // The 'paid' value is used for backwards compatiblity only.

            if ($fields['paid'] < $fields['refunded']) {

                // WA fix for the information returned from X-Payments.
                // The captured and charged values are calculated in a different way,
                // refunded amount is substracted from charged but not from captured.
                // See XPay_Model_Payment::getInfo().

                $fields['paid'] += $fields['refunded'];
            }

            $fields['captured'] = $fields['paid'];
        }

        unset($fields['paid']);

        return $fields;
    }

    /**
     * Get charge value modifier
     *
     * @return float
     */
    public function getChargeValueModifier()
    {
        if ($this->isXpc(true)) {

            $sums = $this->getXpcValues();

            $authorized = max($sums['authorized'] - $sums['voided'] - $sums['captured'], 0);
            $captured = max($sums['captured'] - $sums['refunded'], 0);
            $charged = max($sums['charged'] - $sums['refunded'] - $captured, 0);

            $value = $authorized + $captured + $charged;

        } else {

            $value = parent::getChargeValueModifier();

        }

        return $value;
    }

    /**
     * Set note
     *
     * @param string $note
     *
     * @return Transaction
     */
    public function setNote($note)
    {
        if (strlen($note) > self::NOTE_LIMIT) {

            // truncate note for STRICT_TRANS_TABLES
            $note = function_exists('mb_substr')
                ? mb_substr($note, 0, self::NOTE_LIMIT - 3)
                : substr($note, 0, self::NOTE_LIMIT - 3);

            $note .= '...';
        }

        return parent::setNote($note);
    }

    /**
     * Set XPC data cell
     *
     * @param string $name  Data cell name
     * @param string $value Value
     *
     * @return void
     */
    public function setXpcDataCell($name, $value)
    {
        $data = null;

        if (!isset($value)) {
            $value = '';
        }

        foreach ($this->getXpcDataCells() as $cell) {
            if ($cell->getName() == $name) {
                $data = $cell;
                break;
            }
        }

        if (!$data) {
            $data = new XpcDataCell();
            $data->setName($name);
            $this->addXpcDataCell($data);
            $data->setTransaction($this);
        }

        $data->setValue($value);
    }

    /**
     * Get XPC data cell object by name
     *
     * @param string $name Name of data cell
     *
     * @return XpcDataCell
     */
    public function getXpcDataCell($name)
    {
        $value = null;

        foreach ($this->getXpcDataCells() as $cell) {
            if ($cell->getName() == $name) {
                $value = $cell;
                break;
            }
        }

        if (is_null($value)) {
            // Backward compatiblity
            $value = $this->getDataCell($name);
        }

        return $value;
    }

    /**
     * Add data
     *
     * @param TransactionData $data
     * @return Transaction
     */
    public function addXpcDataCell(XpcDataCell $data)
    {
        $this->xpc_data_cells[] = $data;
        return $this;
    }

    /**
     * Get data
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getXpcDataCells()
    {
        return $this->xpc_data_cells;
    }

    /**
     * Set xpc_data
     *
     * @param XpcTransactionData $xpcData
     * @return Transaction
     */
    public function setXpcData(XpcTransactionData $xpcData = null)
    {
        $this->xpc_data = $xpcData;
        return $this;
    }

    /**
     * Get xpc_data
     *
     * @return XpcTransactionData
     */
    public function getXpcData()
    {
        return $this->xpc_data;
    }

}
