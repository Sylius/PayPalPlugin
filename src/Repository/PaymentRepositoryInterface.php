<?php

namespace Sylius\PayPalPlugin\Repository;

interface PaymentRepositoryInterface
{
    public function getByPayPalOrderId(string $paypalOrderId);
}
