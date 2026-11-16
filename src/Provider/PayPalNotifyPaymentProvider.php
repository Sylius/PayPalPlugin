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

use Sylius\Bundle\PaymentBundle\Provider\NotifyPaymentProviderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface as CorePaymentMethodInterface;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\PayPalWebhookEventIgnoredException;
use Sylius\PayPalPlugin\Exception\PayPalWebhookSignatureNotVerifiedException;
use Sylius\PayPalPlugin\Model\PayPalWebhookEventNotification;
use Sylius\PayPalPlugin\Resolver\WebhookEventPaymentResolverInterface;
use Sylius\PayPalPlugin\Verifier\WebhookRequestVerifierInterface;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final readonly class PayPalNotifyPaymentProvider implements NotifyPaymentProviderInterface
{
    public function __construct(
        private WebhookRequestVerifierInterface $webhookRequestVerifier,
        private WebhookEventPaymentResolverInterface $webhookEventPaymentResolver,
    ) {
    }

    public function supports(Request $request, PaymentMethodInterface $paymentMethod): bool
    {
        return SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $paymentMethod->getGatewayConfig()?->getFactoryName();
    }

    public function getPayment(Request $request, PaymentMethodInterface $paymentMethod): PaymentInterface
    {
        Assert::isInstanceOf($paymentMethod, CorePaymentMethodInterface::class);

        if (!$this->webhookRequestVerifier->verify($request, $paymentMethod)) {
            throw new PayPalWebhookSignatureNotVerifiedException((string) $paymentMethod->getCode());
        }

        $notification = PayPalWebhookEventNotification::fromJson($request->getContent());
        if (null === $notification) {
            throw new PayPalWebhookEventIgnoredException('A PayPal webhook carried no readable event.');
        }

        $payment = $this->webhookEventPaymentResolver->resolve($notification);
        if (null === $payment) {
            throw new PayPalWebhookEventIgnoredException(sprintf(
                'PayPal webhook event "%s" (%s) matches no payment.',
                $notification->id(),
                $notification->eventType(),
            ));
        }

        if ($paymentMethod->getCode() !== $payment->getMethod()?->getCode()) {
            throw new PayPalWebhookEventIgnoredException(sprintf(
                'PayPal webhook event "%s" (%s) was sent to payment method "%s" for a payment of another method.',
                $notification->id(),
                $notification->eventType(),
                (string) $paymentMethod->getCode(),
            ));
        }

        return $payment;
    }
}
