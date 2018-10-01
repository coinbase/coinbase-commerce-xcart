<?php
namespace XLite\Module\Coinbase\CoinbaseCommercePayment\Controller\Customer;

class CoinbaseIpn extends \XLite\Controller\Customer\ACustomer
{
    const EVENT_FAILED = 'charge:failed';
    const EVENT_DELAYED = 'charge:delayed';
    const EVENT_CONFIRMED = 'charge:confirmed';

    public function __construct($params = array())
    {
        parent::__construct($params);
        \XLite\Module\Coinbase\CoinbaseCommercePayment\Main::includeLibrary();
    }

    protected function doNoAction()
    {
        $body = file_get_contents('php://input');
        $bodyArray = \json_decode($body, true);

        if (!isset($bodyArray['event'])) {
            $this->markCallbackRequestAsInvalid('Invalid payload provided. No event key.');

            return false;
        }

        $event = new \CoinbaseSDK\Resources\Event($bodyArray['event']);
        $charge = $event->data;

        $publicTxnId = $charge->getMetadataParam(METADATA_INVOICEID_PARAM);

        $transaction = $this->loadTransaction($publicTxnId);

        $secretKey = $transaction->getPaymentMethod()->getSetting('secret_key');
        $headers = array_change_key_case(getallheaders());
        $signatureHeader = isset($headers[SIGNATURE_HEADER]) ? $headers[SIGNATURE_HEADER] : null;

        try {
            \CoinbaseSDK\Webhook::verifySignature($body, $signatureHeader, $secretKey);
        } catch (\Exception $exception) {
            $this->markCallbackRequestAsInvalid($exception->getMessage());
        }

        if ($transaction->getDataCell(METADATA_TOKEN_PARAM) != $charge->getMetadataParam(METADATA_TOKEN_PARAM)) {
            $this->markCallbackRequestAsInvalid('Invalid token.');
        }

        switch ($event->type) {
            case self::EVENT_FAILED:
            case self::EVENT_DELAYED:
                $transaction->setStatus($transaction::STATUS_FAILED);
                \XLite\Core\Database::getEM()->flush();

                break;
            case self::EVENT_CONFIRMED:
                $transactionId = '';
                $total = '';
                $currency = '';

                foreach ($charge->payments as $payment) {
                    if (strtolower($payment['status']) === 'confirmed') {
                        $transactionId = $payment['transaction_id'];
                        $total = isset($payment['value']['local']['amount']) ? $payment['value']['local']['amount'] : $total;
                        $currency = isset($payment['value']['local']['currency']) ? $payment['value']['local']['currency'] : $currency;
                    }
                }
                $transaction->setDataCell('remote_txn', $transactionId, 'Founded coinbase commerce transaction');
                $transaction->setStatus($transaction::STATUS_SUCCESS);
                \XLite\Core\Database::getEM()->flush();

                $cart = $transaction->getOrder();

                if ($cart instanceof \XLite\Model\Cart) {
                    $cart->tryClose();
                }

                $transaction->getOrder()->setPaymentStatusByTransaction($transaction);
                $transaction->getOrder()->update();
                break;
            default:
                $transaction->setStatus($transaction::STATUS_PENDING);
                \XLite\Core\Database::getEM()->flush();
        }

    }

    private function loadTransaction($publicTxnId)
    {
        $transaction = \Xlite\Core\Database::getRepo('XLite\Model\Payment\Transaction')->findOneBy(
            ['public_id' => $publicTxnId]
        );

        if (!$transaction) {
            $this->markCallbackRequestAsInvalid('Transaction was not found. Transaction Id:' . $publicTxnId);
        }

        return $transaction;
    }

    /**
     * Mark callback request as invalid
     *
     * @param string $message Message
     */
    public function markCallbackRequestAsInvalid($message)
    {
        \XLite\Logger::getInstance()->log(
            'Callback request is invalid: ' . $message . PHP_EOL
        );

        exit(0);
    }
}
