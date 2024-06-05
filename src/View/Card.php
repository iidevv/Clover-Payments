<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace Iidev\CloverPayments\View;

use \XPay\XPaymentsCloud\View\FormField\Select\CardNumberDisplayFormat;

/**
 * Card block 
 */
class Card extends \XLite\View\AView
{
    /**
     * Widget parameter names
     */
    const PARAM_CARD                        = 'card';
    const PARAM_COMPACT                     = 'compact';
    const PARAM_PLAIN_TEXT                  = 'plainText';
    const PARAM_CARD_NUMBER_DISPLAY_FORMAT  = 'cardNumberDisplayFormat';

    /**
     * Asterisk placeholder for card number
     */
    const PLACEHOLDER = '&#8226;';

    /**
     * Return widget default template
     *
     * @return string
     */
    protected function getDefaultTemplate()
    {
        return 'modules/Iidev/CloverPayments/card.twig';
    }

    /**
     * Display card as plain-text or not
     *
     * @return bool
     */
    public function isPlainText()
    {
        return (bool)$this->getParam(self::PARAM_PLAIN_TEXT);
    }

    /**
     * Get CSS class
     *
     * @return string
     */
    public function getCardBlockClass()
    {
        $class = 'xpayments-card';

        if ($this->getParam(self::PARAM_COMPACT)) {
            $class .= ' compact';

            $card = $this->getParam(self::PARAM_CARD);
            if (isset($card['isActive']) && false === $card['isActive']) {
                $class .= ' disabled';
            }
        }

        return $class;
    }

    /**
     * Get card
     *
     * @return array
     */
    public function getCard()
    {
        $card = $this->getParam(self::PARAM_CARD);

        if (!empty($card['cardType'])) {
            $card['cssType'] = strtolower($card['cardType']);
        } elseif (!empty($card['type'])) {
            $card['cssType'] = strtolower($card['type']);
            $card['cardType'] = $card['type'];
        } else {
            $card['cssType'] = '';
        }

        $placeholderLength = ('amex' === $card['cssType']) ? 5 : 6;

        if (!empty($card['cardNumber'])) {
            $card['first6'] = substr($card['cardNumber'], 0, 6);
            $card['last4'] = substr($card['cardNumber'], -4);
        }

        if (CardNumberDisplayFormat::FORMAT_UNMASKED == $this->getParam(self::PARAM_CARD_NUMBER_DISPLAY_FORMAT)) {
            if (!empty($card['expireMonth']) && !empty($card['expireYear'])) {
                $card['expire'] = sprintf('%s/%s', $card['expireMonth'], $card['expireYear']);
            } elseif (empty($card['expire'])) {
                $card['expire'] = '';
            }
        } else {
            $card['expire'] = $card['first6'] = '';
        }

        if (empty($card['first6']) || !is_numeric($card['first6'])) {
            $card['first6'] = '';
            $placeholderLength += 6;
        }

        if (empty($card['last4']) || !is_numeric($card['last4'])) {
            $card['last4'] = '';
            $placeholderLength += 4;
        }

        if (!empty($card['expire']) && $this->getParam(self::PARAM_COMPACT)) {
            $card['expire'] = sprintf('(%s)', $card['expire']);
        }

        $card['placeholder'] = str_repeat(self::PLACEHOLDER, $placeholderLength);

        return $card;
    }

    /**
     * Define widget parameters
     *
     * @return void
     */
    protected function defineWidgetParams()
    {
        parent::defineWidgetParams();

        $this->widgetParams += array(
            self::PARAM_CARD => new \XLite\Model\WidgetParam\TypeCollection(
                'Saved Card',
                array(),
                false
            ),
            self::PARAM_COMPACT => new \XLite\Model\WidgetParam\TypeBool(
                'Compact represenation of card',
                false,
                false
            ),
            self::PARAM_PLAIN_TEXT => new \XLite\Model\WidgetParam\TypeBool(
                'Plain-text represenation of card',
                false,
                false
            ),
            self::PARAM_CARD_NUMBER_DISPLAY_FORMAT => new \XLite\Model\WidgetParam\TypeString(
                'Card number display format',
                \XLite\Core\Config::getInstance()->XPay->XPaymentsCloud->card_number_display_format,
                false
            ),
        );
    }
}
