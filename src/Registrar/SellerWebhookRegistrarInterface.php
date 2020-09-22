<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Registrar;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Exception\PayPalWebhookUrlNotValidException;

interface SellerWebhookRegistrarInterface
{
    /** @throws PayPalWebhookUrlNotValidException */
    public function register(PaymentMethodInterface $paymentMethod): void;
}
