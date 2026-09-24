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

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProvider;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class CreatePayPalOrderAction
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private OrderProviderInterface $orderProvider,
        private CapturePaymentResolverInterface $capturePaymentResolver,
        private ?PayPalPaymentSourceProviderInterface $paymentSourceProvider = null,
    ) {
        if (null === $this->paymentSourceProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                PayPalPaymentSourceProviderInterface::class,
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $paymentSource = $this->resolvePaymentSource($request);
        if (null === $paymentSource) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $token = (string) $request->attributes->get('token');
        $order = $this->orderProvider->provideOrderByToken($token);

        $this->cancelLiveAttempt($order);

        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        if (null === $payment) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $payment->setDetails(array_merge($payment->getDetails(), ['payment_source' => $paymentSource]));

        $this->capturePaymentResolver->resolve($payment);

        $this->paymentStateManager->process($payment);

        $details = $payment->getDetails();
        $payPalOrderId = $details['paypal_order_id'];

        return new JsonResponse(array_filter([
            'orderId' => $payPalOrderId,
            'orderID' => $payPalOrderId, // BC with 2.0. Deprecated in 2.1; use "orderId" instead.
            'status' => $payment->getState(),
            'payerActionUrl' => $this->payerActionUrl($details),
        ], static fn (mixed $value): bool => null !== $value));
    }

    /** @param array<string, mixed> $details */
    private function payerActionUrl(array $details): ?string
    {
        $payerActionUrl = $details['payer_action_url'] ?? null;
        if (!is_string($payerActionUrl)) {
            return null;
        }

        $url = parse_url($payerActionUrl);
        $host = strtolower((string) ($url['host'] ?? ''));

        if ('https' !== ($url['scheme'] ?? null) || ('paypal.com' !== $host && !str_ends_with($host, '.paypal.com'))) {
            return null;
        }

        return $payerActionUrl;
    }

    private function resolvePaymentSource(Request $request): ?string
    {
        $payload = json_decode($request->getContent(), true);
        $paymentSource = is_array($payload) ? ($payload['paymentSource'] ?? null) : null;

        if (null === $paymentSource) {
            return PayPalPaymentSourceProviderInterface::PAYPAL;
        }

        if (!is_string($paymentSource) || !$this->supportsPaymentSource($paymentSource)) {
            return null;
        }

        return $paymentSource;
    }

    private function supportsPaymentSource(string $paymentSource): bool
    {
        return ($this->paymentSourceProvider ?? new PayPalPaymentSourceProvider())->supports($paymentSource);
    }

    private function cancelLiveAttempt(OrderInterface $order): void
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        if (null !== $payment && $this->isPayPalPayment($payment)) {
            $this->paymentStateManager->cancel($payment);
        }
    }

    private function isPayPalPayment(PaymentInterface $payment): bool
    {
        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return false;
        }

        $gatewayConfig = $paymentMethod->getGatewayConfig();

        return
            $gatewayConfig instanceof GatewayConfigInterface &&
            $gatewayConfig->getFactoryName() === SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ;
    }
}
