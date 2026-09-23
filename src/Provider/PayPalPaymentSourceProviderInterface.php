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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

interface PayPalPaymentSourceProviderInterface
{
    public const PAYPAL = 'paypal';

    public const GOOGLE_PAY = 'google_pay';

    public const TRUSTLY = RedirectPaymentSource::Trustly->value;

    /** @var list<string> */
    public const BASE_EXPERIENCE_CONTEXT_KEYS = [
        'brand_name',
        'locale',
        'shipping_preference',
        'return_url',
        'cancel_url',
    ];

    public const CARD = 'card';

    public const VENMO = 'venmo';

    /**
     * @param array<string, mixed> $experienceContext
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedPayPalPaymentSourceException
     * @throws InvalidPayerDataException
     */
    public function provide(OrderInterface $order, string $paymentSource, array $experienceContext): array;

    public function supports(string $paymentSource): bool;
}
