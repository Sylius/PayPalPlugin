<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

interface OnboardingProcessorInterface
{
    public function process(PaymentMethodInterface $paymentMethod, Request $request, HttpClientInterface $httpClient, string $url): PaymentMethodInterface;

    public function supports(PaymentMethodInterface $paymentMethod, Request $request): bool;
}
