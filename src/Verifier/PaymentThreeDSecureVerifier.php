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

namespace Sylius\PayPalPlugin\Verifier;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;

final readonly class PaymentThreeDSecureVerifier implements PaymentThreeDSecureVerifierInterface
{
    private const THREE_D_SECURE_PAYMENT_SOURCES = [
        PayPalPaymentSourceProviderInterface::CARD,
        PayPalPaymentSourceProviderInterface::GOOGLE_PAY,
    ];

    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private OrderDetailsApiInterface $orderDetailsApi,
        private ThreeDSecureVerifierInterface $threeDSecureVerifier,
    ) {
    }

    public function verify(PaymentInterface $payment): void
    {
        $details = PayPalPaymentDetails::fromPayment($payment);
        if (!in_array($details->paymentSource(), self::THREE_D_SECURE_PAYMENT_SOURCES, true)) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $this->threeDSecureVerifier->verify($this->orderDetailsApi->get(
            $this->authorizeClientApi->authorize($paymentMethod),
            (string) $details->payPalOrderId(),
        ));
    }
}
