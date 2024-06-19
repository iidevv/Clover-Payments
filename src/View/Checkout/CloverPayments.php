<?php

namespace Iidev\CloverPayments\View\Checkout;

use XLite\Core\Cache\ExecuteCachedTrait;
use Iidev\CloverPayments\Core\CloverPaymentsAPI;
use XLite\Model\Cart;
use XLite\InjectLoggerTrait;

/**
 * CloverPayments widget
 */
class CloverPayments extends \XLite\View\AView
{
    use InjectLoggerTrait;
    use ExecuteCachedTrait;
    public const PARAM_MODE = 'mode';
    protected $mode;

    protected function defineWidgetParams()
    {
        parent::defineWidgetParams();
        $this->widgetParams[static::PARAM_MODE] = new \XLite\Model\WidgetParam\TypeString('Mode of operation', 'checkout', true);
    }

    protected function initMode()
    {
        $this->mode = $this->getParam(static::PARAM_MODE);
    }

    /**
     * @return array
     */
    public function getJSFiles()
    {
        $list = parent::getJSFiles();
        $api = $this->getAPI();

        $list[] = 'https://cdn.polyfill.io/v3/polyfill.min.js';

        $list[] = [
            'url' => $api->getJSURL(),
        ];

        $list[] = 'modules/Iidev/CloverPayments/checkout/payment.js';

        return $list;
    }

    /**
     * @return array
     */
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();
        $list[] = [
            'file' => 'checkout/css/credit_card.less',
            'media' => 'screen',
            'merge' => 'bootstrap/css/bootstrap.less',
        ];
        $list[] = 'modules/Iidev/CloverPayments/checkout/style.less';

        return $list;
    }

    /**
     * @return string
     */
    protected function getDefaultTemplate()
    {
        return 'modules/Iidev/CloverPayments/checkout/cc_input.twig';
    }

    /**
     * @return string
     */
    protected function getPublicKey()
    {
        return \Iidev\CloverPayments\Main::getMethodConfig()['username'];
    }

    /**
     * @return CloverPayments
     */
    protected function getAPI()
    {
        return $this->executeCachedRuntime(static function () {
            return new CloverPaymentsAPI(\Iidev\CloverPayments\Main::getMethodConfig());
        });
    }

    protected function getToken()
    {
        return $this->getAPI()->getToken();
    }

    /**
     * @return array
     */
    protected function CloverPaymentsData()
    {
        return [
            'token' => $this->getToken(),
        ];
    }

    protected function getCurrentCartIfAvailable(): ?Cart
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        return method_exists(\XLite::getController(), 'getCart')
            ? \XLite::getController()->getCart(false)
            : null;
    }

    protected function isAddCardMode()
    {
        $this->initMode();

        return $this->mode === 'add_card';
    }

    protected function getSavedCards()
    {
        return \XLite\Core\Auth::getInstance()->getProfile() ? \XLite\Core\Auth::getInstance()->getProfile()->getSavedCards() : null;
    }

    /**
     * @return bool
     */
    protected function isTestMode()
    {
        return \Iidev\CloverPayments\Main::getMethodConfig()['mode'] === \XLite\View\FormField\Select\TestLiveMode::TEST;
    }

    /**
     * @return string
     */
    protected function getIframeUrl()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function getIframeImageUrl()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function getFraudSessionId()
    {
        return $this->getAPI()->getFraudSessionId();
    }

    protected function getUnavailableTokenFirstMessage()
    {
        return static::t('The selected payment method is currently unavailable.');
    }

    protected function getUnavailableTokenSecondMessage()
    {
        return static::t('If the problem persists, please, contact us.', [
            'link' => $this->getUnavailableTokenContactLink()
        ]);
    }

    protected function getUnavailableTokenContactLink()
    {
        return 'mailto:' . \XLite\Core\Mailer::getSupportDepartmentMail(false);
    }
}
