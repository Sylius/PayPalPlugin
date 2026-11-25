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

namespace Tests\Sylius\PayPalPlugin\Service;

use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\WebhookSignatureVerifierInterface;
use Symfony\Component\HttpFoundation\Request;

final class DummyWebhookSignatureVerifier implements WebhookSignatureVerifierInterface
{
    public function verify(Request $request, string $webhookId, PaymentMethodInterface $paymentMethod): bool
    {
        return true;
    }
}
