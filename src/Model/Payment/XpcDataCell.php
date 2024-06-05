<?php

namespace Iidev\CloverPayments\Model\Payment;

use Doctrine\ORM\Mapping as ORM;

/**
 * XPC custom transaction data storage
 *
 * @ORM\Entity
 * @ORM\Table (name="clover_payment_data_cells",
 *      indexes={
 *          @ORM\Index (name="tn", columns={"transaction_id","name"})
 *      }
 * )
 */
class XpcDataCell extends \XLite\Model\AEntity
{

    /**
     * Primary key
     *
     * @var integer
     *
     * @ORM\Id
     * @ORM\GeneratedValue (strategy="AUTO")
     * @ORM\Column         (type="integer")
     */
    protected $data_id;

    /**
     * Record name
     *
     * @var string
     *
     * @ORM\Column (type="string", length=128)
     */
    protected $name;

    /**
     * Value
     *
     * @var string
     *
     * @ORM\Column (type="text")
     */
    protected $value;

    /**
     * Transaction
     *
     * @var \XLite\Model\Payment\Transaction
     *
     * @ORM\ManyToOne  (targetEntity="XLite\Model\Payment\Transaction", inversedBy="data")
     * @ORM\JoinColumn (name="transaction_id", referencedColumnName="transaction_id", onDelete="CASCADE")
     */
    protected $transaction;

    /**
     * Get data_id
     *
     * @return integer 
     */
    public function getDataId()
    {
        return $this->data_id;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set value
     *
     * @param string $value
     * @return self
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set transaction
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @return self
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
