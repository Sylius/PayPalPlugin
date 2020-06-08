<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;

interface OnboardingProcessorInterface
{
    public function process(PaymentMethodInterface $paymentMethod, Request $request): PaymentMethodInterface;

    public function supports(PaymentMethodInterface $paymentMethod, Request $request): bool;
}
