<?php

namespace Iidev\CloverPayments\Module\CDev\ContactUs\View\Checkout;

use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 * @Extender\Depend("CDev\ContactUs")
 */
class CloverPayments extends \Iidev\CloverPayments\View\Checkout\CloverPayments
{
    protected function getUnavailableTokenContactLink()
    {
        return $this->buildURL('contact_us');
    }
}
