<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\Coinbase\CoinbaseCommercePayment;

/**
 * Main module
 */
abstract class Main extends \XLite\Module\AModule
{
    /**
     * Author name
     *
     * @return string
     */
    public static function getAuthorName()
    {
        return 'Coinbase';
    }

    /**
     * Module name
     *
     * @return string
     */
    public static function getModuleName()
    {
        return 'Coinbase Commerce Payment Gateway';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Coinbase Commerce  - Bitcoin/Bitcoin Cash/Litecoin/Etherium Payments';
    }

    /**
     * Get module major version
     *
     * @return string
     */
    public static function getMajorVersion()
    {
        return '5.3';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '5';
    }


    public static function getBuildVersion()
    {
        return '5';
    }

    /**
     * Get minor core version which is required for the module activation
     *
     * @return string
     */
    public static function getMinorRequiredCoreVersion()
    {
        return '3';
    }

    /**
     * Get module type
     *
     * @return integer
     */
    public static function getModuleType()
    {
        return static::MODULE_TYPE_PAYMENT;
    }

    /**
     * Include libraries
     */
    public static function includeLibrary()
    {
        require_once LC_DIR_MODULES . str_replace('/', LC_DS, 'Coinbase/CoinbaseCommercePayment/Logic/CoinbaseSDK/init.php');
        require_once LC_DIR_MODULES . str_replace('/', LC_DS, 'Coinbase/CoinbaseCommercePayment/Logic/CoinbaseSDK/const.php');
    }
}
