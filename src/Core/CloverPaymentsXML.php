<?php

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace Iidev\CloverPayments\Core;

/**
 * BlueSnap XML helper
 */
class CloverPaymentsXML
{
    /**
     * @param array $data
     *
     * @return mixed
     */
    public static function toXML($data)
    {
        [$root, $data] = static::getRoot($data);

        $result = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" ?>' . $root);
        static::addData($data, $result);

        return $result->asXML();
    }

    /**
     * @param \SimpleXMLElement $xml
     *
     * @return array
     */
    public static function toArray(\SimpleXMLElement $xml)
    {
        $result = [];

        /** @var \SimpleXMLElement $element */
        foreach ($xml as $name => $element) {
            ($node = &$result[$name])
            && (!is_int(key($node)) ? $node = [$node] : 1)
            && $node = &$node[];

            $node = $element->count() ? static::toArray($element) : trim($element);
        }

        return $result;
    }

    /**
     * @param string $xml
     *
     * @return array
     * @throws \Iidev\CloverPayments\Core\APIException
     */
    public static function stringToArray($xml)
    {
        try {
            libxml_use_internal_errors(true);
            return static::toArray(new \SimpleXMLElement($xml));
        } catch (\Exception $e) {
            throw new APIException($e->getMessage());
        }
    }

    /**
     * @param $data
     *
     * @return array
     */
    protected static function getRoot($data)
    {
        $content = reset($data);
        $tag     = key($data);

        return [sprintf('<%s xmlns="http://ws.plimus.com"></%s>', $tag, $tag), $content];
    }

    /**
     * @param array             $data
     * @param \SimpleXMLElement $xml
     */
    protected static function addData(array $data, \SimpleXMLElement $xml)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                static::addData($value, $child);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }
}
