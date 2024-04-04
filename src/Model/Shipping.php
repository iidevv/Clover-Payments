<?php


namespace Iidev\CloverPayments\Model;

use XCart\Extender\Mapping\Extender;
use XLite\Core\Auth;
use XLite\InjectLoggerTrait;


/**
 * @Extender\Mixin
 */
class Shipping extends \XLite\Model\Shipping
{
    use InjectLoggerTrait;
    protected function getProcessorRates($processor, $modifier)
    {
        $rates = parent::getProcessorRates($processor, $modifier);

        // $this->getLogger('CloverPayments rates')->error(print_r($rates, true));

        $membership = Auth::getInstance()->getMembership();

        $modified = false;

        $order = $modifier->getOrder();

        if ($membership) {
            [$rates, $modified] = $this->filterRatesByMembership($membership, $order, $rates);
        }

        // check if cart contains non free shipping items for pro members
        if (!$modified) {
            $isCartContainNonFreeShippingItems  = false;
            foreach ($order->getItems() as $item) {
                if ($item->getProduct()->getFreeShippingForMemberships()->count() == 0) {
                    $isCartContainNonFreeShippingItems = true;
                    break;
                }
            }

            if ($isCartContainNonFreeShippingItems) {
                $modified = true;
            }
        }

        if (!$modified) {

            // lets see if order has paid membership product
            $isProMembershipInCart = $order->isProMembershipInCart($order->getItems());

            if ($isProMembershipInCart) {

                $proShippingMethodId = (int) \XLite\Core\Config::getInstance()->Qualiteam->SkinActProMembership->pro_shipping_method;

                foreach ($rates as $ind => $rate) {
                    if ($rate->getMethod()->getMethodId() !== $proShippingMethodId) {
                        unset($rates[$ind]);
                    }
                }
                $modified = true;
            }
        }

        // if (!$modified) {
        //     // unset special free shipping method
        //     $proShippingMethodId = (int) \XLite\Core\Config::getInstance()->Qualiteam->SkinActProMembership->pro_shipping_method;

        //     foreach ($rates as $ind => $rate) {
        //         if ($rate->getMethod()->getMethodId() == $proShippingMethodId) {
        //             unset($rates[$ind]);
        //         }
        //     }
        // }

        return $rates;
    }

}