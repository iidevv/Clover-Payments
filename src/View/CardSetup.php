<?php
// vim: set ts=4 sw=4 sts=4 et:

namespace Iidev\CloverPayments\View;

/**
 * Add New Card widget
 */
class CardSetup extends \XLite\View\AView
{

    /**
     *
     * @return array
     */
    public function getJSFiles()
    {
        $list = parent::getJSFiles();

        return $list;
    }

    /**
     *
     * @return array
     */
    protected function getCommonFiles()
    {
        $list = parent::getCommonFiles();

        return $list;
    }

    /**
     * Get CSS files
     *
     * @return array
     */
    public function getCSSFiles()
    {
        $list = parent::getCSSFiles();

        $list[] = 'modules/Iidev/CloverPayments/account/card_setup.css';


        return $list;
    }

    /**
     * Return widget default template
     *
     * @return string
     */
    protected function getDefaultTemplate()
    {
        return 'modules/Iidev/CloverPayments/account/card_setup.twig';
    }

    /**
     * Get list of addresses
     *
     * @return array
     */
    public function getAddressList()
    {
        static $list = null;

        if (is_null($list)) {
            $list = [];
            $addresses = $this->getCustomerProfile()->getAddresses()->toArray();

            /** @var \XLite\Model\Address $address */
            foreach ($addresses as $address) {
                $list[$address->getAddressId()] = $this->getAddressAsString($address);
            }
        }

        return $list;
    }

    /**
     * Get address ID
     *
     * @return int
     */
    public function getAddressId()
    {
        $profile = $this->getCustomerProfile();

        if ($profile->getBillingAddress()) {
            $addressId = $profile->getBillingAddress()->getAddressId();
        } else {
            $list = $this->getAddressList();
            $addressId = key($list);
        }

        return $addressId;
    }

    /**
     * Get whole address as string
     *
     * @param \XLite\Model\Address $address Address
     *
     * @return string
     */
    public function getAddressAsString(\XLite\Model\Address $address)
    {
        $addressFields = $address->getAvailableAddressFields();

        $hasStates = $address->hasStates();

        $result = '';

        foreach ($addressFields as $field) {

            if ('country_code' === $field) {
                $field = 'country';
            }

            if ($hasStates) {
                if ('state_id' === $field) {
                    $field = 'state';
                } elseif ('custom_state' === $field) {
                    continue;
                }
            } else {
                if ('state_id' === $field) {
                    continue;
                }
            }

            $method = 'get' . ucfirst($field);

            $item = $address->$method();

            if (is_callable(array($item, $method))) {
                $item = $item->$method();
            }

            $result = $result . ' ' . $item;
        }

        return trim($result);
    }

    /**
     * Get default currency
     *
     * @return \XLite\Model\Currency
     */
    public function getCurrency()
    {
        return \XLite::getInstance()->getCurrency();
    }

    /**
     * Get Card Setup amount
     *
     * @return float
     */
    public function getAmount()
    {
        return floatval(\XLite\Core\Request::getInstance()->amount);
    }

    /**
     * Get customer profile
     *
     * @return \XLite\Model\Profile
     */
    protected function getCustomerProfile()
    {
        if (\XLite::isAdminZone()) {
            $profileId = \XLite\Core\Request::getInstance()->profile_id;
        }
        if (empty($profileId)) {
            $profileId = \XLite\Core\Auth::getInstance()->getProfile()->getProfileId();
        }

        /** @var \XLite\Model\Profile $result */
        $result = \XLite\Core\Database::getRepo('XLite\Model\Profile')
            ->find(intval($profileId));
        return $result;
    }

}

