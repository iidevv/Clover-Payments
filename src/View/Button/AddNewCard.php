<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace Iidev\CloverPayments\View\Button;

/**
 * Add new card button widget
 */
class AddNewCard extends \XLite\View\Button\APopupButton
{
    /*
     * Widget parameter
     */
    const PARAM_PROFILE_ID = 'profileId';
    const PARAM_WIDGET_TITLE = 'widgetTitle';
    const PARAM_AMOUNT = 'amount';

    /**
     * Get JS files
     *
     * @return array
     */
    public function getJSFiles()
    {
        $list = parent::getJSFiles();

        $list[] = 'modules/XPay/XPaymentsCloud/button/add_new_card.js';

        return $list;
    }

    /**
     * Define widget params
     *
     * @return void
     */
    protected function defineWidgetParams()
    {
        parent::defineWidgetParams();

        $this->widgetParams += array(
            self::PARAM_PROFILE_ID => new \XLite\Model\WidgetParam\TypeInt('Profile ID', 0),
            self::PARAM_WIDGET_TITLE => new \XLite\Model\WidgetParam\TypeString('Widget title', static::t('Card setup')),
            self::PARAM_AMOUNT => new \XLite\Model\WidgetParam\TypeInt('Card Setup Amount', 0),
        );
    }

    /**
     * Return URL parameters to use in AJAX popup
     *
     * @return array
     */
    protected function prepareURLParams()
    {
        return array(
            'target'       => 'payment_cards',
            'profile_id'   => $this->getParam(self::PARAM_PROFILE_ID),
            'widget'       => '\Iidev\CloverPayments\View\CardSetup',
            'widget_title' => $this->getParam(self::PARAM_WIDGET_TITLE),
            'amount'       => $this->getParam(self::PARAM_AMOUNT),
        );
    }

    /**
     * Return CSS classes
     *
     * @return string
     */
    protected function getClass()
    {
        return 'btn regular-button popup-button add-new-card';
    }
}
