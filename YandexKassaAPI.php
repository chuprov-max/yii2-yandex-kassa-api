<?php

namespace mikefinch\YandexKassaAPI;

use YandexCheckout\Client;
use mikefinch\YandexKassaAPI\interfaces\OrderInterface;
use mikefinch\YandexKassaAPI\interfaces\OrderFiscalizationInterface;
use yii\base\Component;

class YandexKassaAPI extends Component
{
    public $shopId;
    public $key;
    private $client;
    public $returnUrl;
    public $currency = "RUB";

    /**
     * @var boolean Use fiscalization (54-fz) or not (https://kassa.yandex.ru/docs/guides/#oplata-po-54-fz)
     */
    public $fiscalization = false;
    
    public function init()
    {
        parent::init();
        $this->client = new Client();
        $this->client->setAuth($this->shopId, $this->key);
    }

    public function createPayment(OrderInterface $order)
    {
        $paymentArray = [
            'amount' => [
                'value' => $order->getPaymentAmount(),
                'currency' => $this->currency
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->returnUrl,
            ],
            'capture' => true,
        ];
        if ($this->fiscalization && (($receipt = $this->buildReceipt($order)) !== null)) {
            $paymentArray['receipt'] = $receipt;
        }
        
        if ($order->getDescription()) {
            $paymentArray['description'] = $order->getDescription();
        }

        \Yii::warning('[Yandex Kassa fiscalization $paymentArray]:' . print_r($paymentArray, true), 'payment'); // remove after debug

        $payment = $this->client->createPayment(
            $paymentArray, uniqid('', true)
        );

        $order->setInvoiceId($payment->getId());
        $order->save();

        return $payment;
    }

    public function getPayment($invoiceId)
    {
        return $this->client->getPaymentInfo($invoiceId);
    }

    /**
     * @param $invoiceId
     * @param $order OrderInterface
     * @return bool
     */
    public function confirmPayment($invoiceId, OrderInterface $order)
    {
        $payment = $this->getPayment($invoiceId);

        if ($payment->getPaid()) {
            $data = [
                'amount' => [
                    'value' => $order->getPaymentAmount(),
                    'currency' => 'RUB',
                ],
            ];

            $confirm = $this->client->capturePayment($data, $order->getInvoiceId(), $this->generateIdempotent());
            return $confirm;
        }

        return false;
    }

    /**
     * Build `receipt` array element
     * 
     * @param OrderFiscalizationInterface $order
     * @return []
     */
    protected function buildReceipt(OrderFiscalizationInterface $order)
    {
        $receipt = $order->getReceipt();

        $receiptItems = [];
        foreach ($receipt->getItems() as $item) {
            $amount = $item->getPrice();
            $receiptItems[] = [
                'description' => $item->getDescription(),
                'quantity' => $item->getQuantity(),
                'amount' => [
                    'value' => $amount->getValue(),
                    'currency' => $amount->getCurrency()
                ],
                'vat_code' => $item->getVatCode(),
                'payment_subject' => $item->getPaymentSubject(),
                'payment_mode' => $item->getPaymentMode(),
            ];
        }

        return [
            'email' => $receipt->getEmail(),
            "items" => $receiptItems
        ];
    }

    private function generateIdempotent()
    {
        return uniqid('', true);
    }
}
