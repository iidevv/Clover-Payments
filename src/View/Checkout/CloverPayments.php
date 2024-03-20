<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\View\Checkout;

use XLite\Core\Cache\ExecuteCachedTrait;
use Iidev\CloverPayments\Core\CloverPaymentsAPI;

/**
 * BlueSnap widget
 */
class CloverPayments extends \XLite\View\AView
{
    use ExecuteCachedTrait;

    /**
     * @return array
     */
    public function getJSFiles()
    {
        $list = parent::getJSFiles();
        $api  = $this->getAPI();

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
        $list   = parent::getCSSFiles();
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
    protected function getBlueSnapData()
    {
        return [
            'token' => $this->getToken(),
        ];
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
        return $this->isTestMode()
            ? 'https://sandbox.bluesnap.com/servlet/logo.htm?s=' . $this->getFraudSessionId()
            : 'https://www.bluesnap.com/servlet/logo.htm?s=' . $this->getFraudSessionId();
    }

    /**
     * @return string
     */
    protected function getIframeImageUrl()
    {
        return $this->isTestMode()
            ? 'https://sandbox.bluesnap.com/servlet/logo.gif?s=' . $this->getFraudSessionId()
            : 'https://www.bluesnap.com/servlet/logo.gif?s=' . $this->getFraudSessionId();
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
