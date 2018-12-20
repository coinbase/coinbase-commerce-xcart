<?php
namespace XLite\Module\Coinbase\CoinbaseCommercePayment\Controller\Customer;

use XLite\Module\CDev\Paypal\Model\Payment\Transaction;

class CoinbaseIpn extends \XLite\Controller\Customer\ACustomer
{
    private $transaction;

    public function __construct($params = array())
    {
        parent::__construct($params);
        \XLite\Module\Coinbase\CoinbaseCommercePayment\Main::includeLibrary();
    }

    protected function doNoAction()
    {
        $body = file_get_contents('php://input');
        $bodyArray = \json_decode($body, true);

        if (!isset($bodyArray['event']['data'])) {
            $this->markCallbackRequestAsInvalid('Invalid payload provided. Charge not found.');
            return false;
        }

        $charge = new \CoinbaseCommerce\Resources\Charge($bodyArray['event']['data']);

        $txnId = $charge->metadata[METADATA_INVOICEID_PARAM];
        $txtToken = $charge->metadata[METADATA_TOKEN_PARAM];

        $this->loadTransaction($txnId, $txtToken);
        $this->validateBody($body);
        $lastTimeLine = end($charge->timeline);

        switch ($lastTimeLine['status']) {
            case 'RESOLVED':
            case 'COMPLETED':
                $this->handlePaid($charge);
                break;
            case 'PENDING':
                $status = Transaction::STATUS_SUCCESS;
                break;
            case 'NEW':
                $status = Transaction::STATUS_INPROGRESS;
                break;
            case 'UNRESOLVED':
                // mark order as paid on overpaid
                if ($lastTimeLine['context'] === 'OVERPAID') {
                    $status = Transaction::STATUS_SUCCESS;
                } else {
                    $status = Transaction::STATUS_FAILED;
                }
                break;
            case 'CANCELED':
                $status = Transaction::STATUS_CANCELED;
                break;
            case 'EXPIRED':
                $status = Transaction::STATUS_FAILED;
                break;
        }

        foreach ($charge->payments as $payment) {
            if (strtolower($payment['status']) === 'confirmed') {
                $transactionId = $payment['transaction_id'];
                $total = isset($payment['value']['local']['amount']) ? $payment['value']['local']['amount'] : '';
                $currency = isset($payment['value']['local']['currency']) ? $payment['value']['local']['currency'] : '';
                $cryptototal = isset($payment['value']['crypto']['amount']) ? $payment['value']['crypto']['amount'] : '';
                $cryptoCurrency = isset($payment['value']['crypto']['currency']) ? $payment['value']['crypto']['currency'] : '';

                $this->transaction->setDataCell('remote_txn', $transactionId, 'Remote transaction ID');
                $this->transaction->setDataCell('total', $total, 'Total');
                $this->transaction->setDataCell('currency', $currency, 'Currency');
                $this->transaction->setDataCell('crypto_total', $cryptototal, 'Crypto total');
                $this->transaction->setDataCell('crypto_currency', $cryptoCurrency, 'Crypto currency');
                break;
            }
        }

        $this->setTransactionStatus($status);
    }

    private function validateBody($body)
    {
        $secretKey = $this->transaction->getPaymentMethod()->getSetting('secret_key');
        $headers = array_change_key_case(getallheaders());
        $signatureHeader = isset($headers[SIGNATURE_HEADER]) ? $headers[SIGNATURE_HEADER] : null;

        try {
            \CoinbaseCommerce\Webhook::verifySignature($body, $signatureHeader, $secretKey);
        } catch (\Exception $exception) {
            $this->markCallbackRequestAsInvalid($exception->getMessage());
            return false;
        }

        return true;
    }

    private function setTransactionStatus($status, $transDataCells)
    {
        if (!$this->transaction) {
            return;
        }

        $this->transaction->setStatus($status);
        $this->transaction->registerTransactionInOrderHistory();

        \XLite\Core\Database::getEM()->flush();

        $order = $this->transaction->getOrder();

        if ($order instanceof \XLite\Model\Cart) {
            $order->tryClose();
        }

        $order->setPaymentStatusByTransaction($this->transaction);
        $order->update();
    }

    private function loadTransaction($publicTxnId, $token)
    {
        $this->transaction = \Xlite\Core\Database::getRepo('XLite\Model\Payment\Transaction')->findOneBy(
            ['public_id' => $publicTxnId]
        );

        if (!$this->transaction) {
            $this->markCallbackRequestAsInvalid('Transaction was not found. Transaction Id:' . $publicTxnId);
            return false;
        }

        if ($this->transaction->getDataCell(METADATA_TOKEN_PARAM)->getValue() != $token) {
           $this->markCallbackRequestAsInvalid('Invalid token.');
            return false;
        }

        return $this->transaction;
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
