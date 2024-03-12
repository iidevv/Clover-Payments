<?php

namespace Iidev\CloverPayments\Model\Payment\Processor;

class CloverIFrame extends \XLite\Model\Payment\Base\Iframe
{
    protected function getIframeData()
    {
        // $token = $this->doCreateSecureToken();
        $token = 123;

        $result = $token ? $this->getPostURL($this->iframeURL, $this->getIframeParams($token)) : null;

        return $result;
    }
}
