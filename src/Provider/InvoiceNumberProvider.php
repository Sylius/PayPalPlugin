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

use Sylius\Component\Core\Model\PaymentInterface;

final readonly class InvoiceNumberProvider implements InvoiceNumberProviderInterface
{
    public function __construct(
        private PaymentReferenceNumberProviderInterface $paymentReferenceNumberProvider,
    ) {
    }

    public function provide(PaymentInterface $payment, string $referenceId): string
    {
        return $this->paymentReferenceNumberProvider->provide($payment) . '-' . $referenceId;
    }
}
