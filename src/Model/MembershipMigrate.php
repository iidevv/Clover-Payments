<?php

namespace Iidev\CloverPayments\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Membership
 *
 * @ORM\Entity
 * @ORM\Table(name="xcart_customers")
 */
class MembershipMigrate extends \XLite\Model\AEntity
{
    /**
     * Unique ID
     *
     * @var integer
     *
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $id;

    /**
     * login
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    protected $login;

    /**
     * Paid membership ID
     *
     * @var integer
     *
     * @ORM\Column(type="integer")
     */
    private $paid_membershipid;

    /**
     * Paid membership expiry
     *
     * @var integer
     *
     * @ORM\Column(type="integer")
     */
    private $paid_membership_expire;

    /**
     * status
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    protected $status;


    // Getters
    public function getId()
    {
        return $this->id;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function getPaidMembershipId()
    {
        return $this->paid_membershipid;
    }

    public function getPaidMembershipExpire()
    {
        return $this->paid_membership_expire;
    }

    public function getStatus()
    {
        return $this->status;
    }
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }
}