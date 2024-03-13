<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments;

abstract class Main extends \XLite\Module\AModule
{
    /**
     * @param string $serviceName
     *
     * @return array
     */
    public static function getMethodConfig($serviceName = 'CloverPayments')
    {
        /** @var \XLite\Model\Payment\Method $method */
        $method = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy([
            'service_name' => $serviceName,
        ]);

        $config = [];
        /** @var \XLite\Model\Payment\MethodSetting $setting */
        foreach ($method->getSettings() as $setting) {
            $config[$setting->getName()] = $setting->getValue();
        }

        return $config;
    }
}
