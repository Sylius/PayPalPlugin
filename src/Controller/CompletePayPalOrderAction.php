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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\OrderDetailsApiInterface;
use Sylius\PayPalPlugin\Exception\ThreeDSecureAuthenticationFailedException;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\ThreeDSecureVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CompletePayPalOrderAction
{
    public function __construct(
        private PaymentStateManagerInterface $paymentStateManager,
        private UrlGeneratorInterface $router,
        private OrderProviderInterface $orderProvider,
        private ?CacheAuthorizeClientApiInterface $authorizeClientApi = null,
        private ?OrderDetailsApiInterface $orderDetailsApi = null,
        private ?ThreeDSecureVerifierInterface $threeDSecureVerifier = null,
    ) {
        if (
            null === $this->authorizeClientApi ||
            null === $this->orderDetailsApi ||
            null === $this->threeDSecureVerifier
        ) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $authorizeClientApi, $orderDetailsApi and $threeDSecureVerifier to "%s" constructor' .
                ' is deprecated and will be prohibited in 3.0. Without them the 3D Secure authentication result' .
                ' is not verified before the payment is captured.',
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $token = (string) $request->attributes->get('token');
        $order = $this->orderProvider->provideOrderByToken($token);

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);
        if (null === $payment) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $payPalOrderId = (string) ($payment->getDetails()['paypal_order_id'] ?? '');
        $requestedPayPalOrderId = $request->getPayload()->getString('payPalOrderId');

        if ('' !== $requestedPayPalOrderId && $requestedPayPalOrderId !== $payPalOrderId) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->verifyAuthenticationResult($payment, $payPalOrderId);
        } catch (ThreeDSecureAuthenticationFailedException $exception) {
            $this->paymentStateManager->cancel($payment);
            $this->addErrorFlash(
                $request,
                $exception->isRetryable() ? 'sylius_paypal.three_d_secure_retry' : 'sylius_paypal.three_d_secure_declined',
            );

            return $this->response(
                $payment,
                $payPalOrderId,
                $exception->isRetryable() ? $this->payPalPageUrl($order) : $this->orderUrl($order),
            );
        }

        $this->paymentStateManager->complete($payment);

        if (PaymentInterface::STATE_COMPLETED !== $payment->getState()) {
            $this->paymentStateManager->cancel($payment);
            $this->addErrorFlash($request, 'sylius_paypal.something_went_wrong');

            return $this->response($payment, $payPalOrderId, $this->payPalPageUrl($order));
        }

        return $this->response(
            $payment,
            $payPalOrderId,
            $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    private function verifyAuthenticationResult(PaymentInterface $payment, string $payPalOrderId): void
    {
        if (
            null === $this->authorizeClientApi ||
            null === $this->orderDetailsApi ||
            null === $this->threeDSecureVerifier
        ) {
            return;
        }

        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $this->threeDSecureVerifier->verify(
            $this->orderDetailsApi->get($this->authorizeClientApi->authorize($paymentMethod), $payPalOrderId),
        );
    }

    private function addErrorFlash(Request $request, string $message): void
    {
        /** @var FlashBagInterface $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');

        $flashBag->add('error', $message);
    }

    private function payPalPageUrl(OrderInterface $order): string
    {
        $payment = $order->getLastPayment(PaymentInterface::STATE_NEW);
        if (null === $payment) {
            return $this->orderUrl($order);
        }

        return $this->router->generate(
            'sylius_paypal_shop_pay_with_paypal_form',
            ['orderToken' => $order->getTokenValue(), 'paymentId' => $payment->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function orderUrl(OrderInterface $order): string
    {
        return $this->router->generate(
            'sylius_shop_order_show',
            ['tokenValue' => $order->getTokenValue()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    private function response(PaymentInterface $payment, string $payPalOrderId, string $returnUrl): JsonResponse
    {
        return new JsonResponse([
            'orderId' => $payPalOrderId,
            'orderID' => $payPalOrderId, // BC with 2.0. Deprecated in 2.1; use "orderId" instead.
            'status' => $payment->getState(),
            'return_url' => $returnUrl,
        ]);
    }
}
