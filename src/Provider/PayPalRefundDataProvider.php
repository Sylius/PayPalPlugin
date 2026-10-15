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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\PayPalPlugin\Api\GenericApiInterface;
use Sylius\PayPalPlugin\Exception\PayPalWrongDataException;

final readonly class PayPalRefundDataProvider implements PayPalRefundDataProviderInterface
{
    public function __construct(
        private GenericApiInterface $genericApi,
        private PayPalPaymentMethodProviderInterface $payPalPaymentMethodProvider,
    ) {
    }

    public function provide(string $refundRefundUrl): array
    {
        $paymentMethod = $this->payPalPaymentMethodProvider->provide();

        $refundData = $this->genericApi->get($paymentMethod, $refundRefundUrl);

        /** @var array<array{rel?: string, href?: string}> $links */
        $links = is_array($refundData['links'] ?? null) ? $refundData['links'] : [];

        foreach ($links as $link) {
            if ('up' === ($link['rel'] ?? null) && isset($link['href'])) {
                return $this->genericApi->get($paymentMethod, (string) $link['href']);
            }
        }

        throw new PayPalWrongDataException();
    }
}
