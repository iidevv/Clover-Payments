<?php

namespace Iidev\CloverPayments\View\Order\Details\Admin;
use XCart\Extender\Mapping\Extender;

/**
 * @Extender\Mixin
 */
class Info extends \XLite\View\Order\Details\Admin\Info
{
     /**
     * Register CSS files
     *
     * @return array
     */
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();

        $list[] = 'modules/Iidev/CloverPayments/account/style.css';

        return $list;
    }
}
