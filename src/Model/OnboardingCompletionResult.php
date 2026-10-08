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

namespace Sylius\PayPalPlugin\Model;

use Sylius\Component\Core\Model\PaymentMethodInterface;

final readonly class OnboardingCompletionResult
{
    public function __construct(
        private PaymentMethodInterface $paymentMethod,
        private OnboardingStatus $status,
        private bool $webhookUrlValid,
    ) {
    }

    public function getPaymentMethod(): PaymentMethodInterface
    {
        return $this->paymentMethod;
    }

    public function getStatus(): OnboardingStatus
    {
        return $this->status;
    }

    public function isWebhookUrlValid(): bool
    {
        return $this->webhookUrlValid;
    }
}
