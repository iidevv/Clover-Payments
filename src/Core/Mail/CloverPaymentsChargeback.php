<?php

namespace Iidev\CloverPayments\Core\Mail;

use XLite\Core\Mailer;
use XLite\Model\Order;

class CloverPaymentsChargeback extends \XLite\Core\Mail\Order\AAdmin
{
    public static function getDir()
    {
        return 'modules/Iidev/CloverPayments/chargeback';
    }

    protected static function defineVariables()
    {
        return parent::defineVariables() + [
                'referenceNumber' => '',
            ];
    }

    /**
     * CloverPaymentsChargeback constructor.
     *
     * @param Order  $order
     * @param string $referenceNumber
     */
    public function __construct(Order $order, $referenceNumber)
    {
        parent::__construct($order);

        $this->setTo(Mailer::getSiteAdministratorMails());
        $this->populateVariables(['referenceNumber' => $referenceNumber]);

        $this->appendData([
            'fromName'             => 'CloverPayments',
            'orderNumber'          => $order->getOrderNumber(),
            'referenceNumber'      => $referenceNumber,
            'hideCompanyInSubject' => true,
        ]);
    }
}
