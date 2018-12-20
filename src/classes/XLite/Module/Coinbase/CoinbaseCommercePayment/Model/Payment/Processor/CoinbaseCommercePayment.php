<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\Coinbase\CoinbaseCommercePayment\Model\Payment\Processor;

/**
 * Payment processor for coinbase
 */
class CoinbaseCommercePayment extends \XLite\Model\Payment\Base\WebBased
{
    /**
     * Constructor
     */
    protected function __construct()
    {
        parent::__construct();

        \XLite\Module\Coinbase\CoinbaseCommercePayment\Main::includeLibrary();
    }

    /**
     * Process return
     *
     * @param \XLite\Model\Payment\Transaction $transaction Return-owner transaction
     *
     * @return void
     */
    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processReturn($transaction);

        if (\XLite\Core\Request::getInstance()->cancel) {
            $this->setDetail(
                'status',
                'Customer has canceled the checkout before completing their payment',
                'Status'
            );
            $this->transaction->setNote('Customer has canceled checkout before completing their payments');
            $this->transaction->setStatus($transaction::STATUS_CANCELED);

            \XLite\Core\Operator::redirect(
                $this->getCheckoutUrl()
            );
        } else {
            $this->transaction->order->processSucceed();

            \XLite\Core\Operator::redirect(
                $this->getInvoiceUrl($transaction)
            );
        }
    }

    private function getCheckoutUrl()
    {
        return \XLite::getInstance()->getShopURL(
            \XLite\Core\Converter::buildURL('checkout'),
            \XLite\Core\Config::getInstance()->Security->customer_security
        );
    }

    private function getInvoiceUrl($transaction)
    {
        return \XLite::getInstance()->getShopURL(
            \XLite\Core\Converter::buildURL('order', '', array('order_number' => $transaction->getOrder()->getOrderNumber())),
            \XLite\Core\Config::getInstance()->Security->customer_security
        );
    }

    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/Coinbase/CoinbaseCommercePayment/config.twig';
    }

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method)
          && $method->getSetting('app_key')
          && $method->getSetting('secret_key');
    }

    /**
     * Get payment method admin zone icon URL
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return string
     */
    public function getAdminIconURL(\XLite\Model\Payment\Method $method)
    {
        return true;
    }

    /**
     * Returns the list of settings available for this payment processor
     *
     * @return array
     */
    public function getAvailableSettings()
    {
        return array(
            'app_key',
            'secret_key',
        );
    }

    /**
     * Get redirect form URL
     *
     * @return string
     */
    protected function getFormURL()
    {
        $token = $this->generateRandomToken();
        $billingAddress = $this->getProfile()->getBillingAddress();
        $chargeData = array(
            'local_price' => array(
                'amount' => $this->transaction->getValue(),
                'currency' => $this->getCurrencyCode()
            ),
            'pricing_type' => 'fixed_price',
            'name' => sprintf(
                '%s: payment for %s transaction',
                \XLite\Core\Config::getInstance()->Company->company_name,
                $this->transaction->getPublicTxnId()
            ),
            'description' => mb_substr(implode($this->getOrderProducts(), ', '), 0, 200),
            'metadata' => [
                METADATA_SOURCE_PARAM => METADATA_SOURCE_VALUE,
                METADATA_INVOICEID_PARAM  => $this->transaction->getPublicTxnId(),
                METADATA_TOKEN_PARAM => $token,
                'email' => $this->getProfile()->getLogin(),
                'firstName' => $billingAddress ? $billingAddress->getFirstname() : '',
                'lastName' => $billingAddress ? $billingAddress->getLastname() : ''
            ],
            'redirect_url' => $this->getReturnURL(null, true),
            'cancel_url' => $this->getReturnURL(null, true, true),
        );

        $this->transaction->setDataCell(METADATA_TOKEN_PARAM, $token, METADATA_TOKEN_PARAM, 'C');
        \XLite\Core\Database::getEM()->flush();

        \CoinbaseCommerce\ApiClient::init($this->getSetting('app_key'));
        $chargeObj = \CoinbaseCommerce\Resources\Charge::create($chargeData);

        return $chargeObj->hosted_url;
    }

    private function generateRandomToken()
    {
        return bin2hex(random_bytes(4));
    }

    private function getOrderProducts()
    {
        $products = array();

        foreach ($this->transaction->getOrder()->getItems() as $item) {
            $products[] = $item->getName() . ' x ' . $item->getAmount();
        }

        return $products;
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */
    protected function getFormFields()
    {
        return array(
            'transactionID' => $this->transaction->getPublicTxnId(),
            'returnURL' => $this->getReturnURL('transactionID')
        );
    }

    /**
     * Get Webhook URL
     *
     * @return string
     */
    public function getWebhookURL()
    {
        return \XLite::getInstance()->getShopURL(
            \XLite\Core\Converter::buildURL('coinbase_ipn', null, array(), \XLite::getCustomerScript()),
            \XLite\Core\Config::getInstance()->Security->customer_security
        );
    }

    /**
     * Get form method
     *
     * @return string
     */
    protected function getFormMethod()
    {
        return static::FORM_METHOD_GET;
    }

    /**
     * Get currency code
     *
     * @return string
     */
    protected function getCurrencyCode()
    {
        return strtoupper($this->transaction->getCurrency()->getCode());
    }
}
