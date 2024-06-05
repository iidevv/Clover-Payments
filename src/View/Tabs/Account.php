<?php

namespace Iidev\CloverPayments\View\Tabs;

use XCart\Extender\Mapping\Extender;
use XPay\XPaymentsCloud\Main as XPaymentsHelper;

/**
 * X-Payments Saved Cards tab
 *
 * @Extender\Mixin
 */
abstract class Account extends \XLite\View\Tabs\Account implements \XLite\Base\IDecorator
{
    /**
     * Returns the list of targets where this widget is available
     *
     * @return array
     */
    public static function getAllowedTargets()
    {
        $list = parent::getAllowedTargets();
        $list[] = 'payment_cards';
        
        return $list;
    }

    /**
     * Define tabs
     *
     * @return array
     */
    protected function defineTabs()
    {
        $tabs = parent::defineTabs();

        if (
            $this->getProfile()
        ) {
            $tabs['payment_cards'] = array(
                'weight' => 1200,
                'title' => static::t('Saved cards'),
                'template' => 'modules/Iidev/CloverPayments/saved_cards/body.twig',
            );

        }

        return $tabs;
    }

    /**
     * Register files from common repository
     *
     * @return array
     */
    public function getCommonFiles()
    {
        $list = parent::getCommonFiles();

        if (static::isXpaymentsEnabled()) {
            $list['css'][] = 'modules/XPay/XPaymentsCloud/account/cc_type_sprites.css';
            $list['css'][] = 'modules/XPay/XPaymentsCloud/account/xpayments_cards.less';
        }

        return $list;
    }
}
