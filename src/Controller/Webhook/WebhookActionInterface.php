<?php

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Symfony\Component\HttpFoundation\Request;

interface WebhookActionInterface
{
    public function supports(Request $request): bool;
    public function getPayPalPaymentUrl(Request $request): string;
}
