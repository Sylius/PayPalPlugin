<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Onboarding\Processor;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\HttpFoundation\Request;

interface OnboardingProcessorInterface
{
    public function process(PaymentMethodInterface $paymentMethod, Request $request): PaymentMethodInterface;

    public function supports(PaymentMethodInterface $paymentMethod, Request $request): bool;
}
