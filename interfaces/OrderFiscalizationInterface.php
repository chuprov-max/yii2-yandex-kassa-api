<?php

namespace mikefinch\YandexKassaAPI\interfaces;

use YandexCheckout\Model\Receipt;

interface OrderFiscalizationInterface
{
    /**
     * @return string
     */
    public function getUserEmail();
    
    /**
     * @return Receipt
     */
    public function getReceipt();
}
