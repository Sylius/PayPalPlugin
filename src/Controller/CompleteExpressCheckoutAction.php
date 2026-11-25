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

use Psr\Log\LoggerInterface;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\OrderCheckoutStates;
use Sylius\PayPalPlugin\Completer\ExpressCheckoutCompleterInterface;
use Sylius\PayPalPlugin\Dispatcher\ApprovedPayPalOrderPaymentRequestDispatcherInterface;
use Sylius\PayPalPlugin\Exception\PaymentAmountMismatchException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class CompleteExpressCheckoutAction
{
    public function __construct(
        private OrderProviderInterface $orderProvider,
        private OrderOwnershipVerifierInterface $orderOwnershipVerifier,
        private ExpressCheckoutCompleterInterface $expressCheckoutCompleter,
        private ApprovedPayPalOrderPaymentRequestDispatcherInterface $approvedPayPalOrderPaymentRequestDispatcher,
        private UrlProviderInterface $paymentRequestPayUrlProvider,
        private UrlGeneratorInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->getPayload();
        $payPalOrderId = $payload->getString('payPalOrderId');

        $order = $this->orderProvider->provideOrderById($payload->getInt('orderId'));
        $this->orderOwnershipVerifier->verify($order, $request);

        /** @var PaymentInterface|null $payment */
        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        if (null === $payment) {
            return $this->returnTo(OrderCheckoutStates::STATE_COMPLETED === $order->getCheckoutState()
                ? $this->router->generate('sylius_shop_order_thank_you', [], UrlGeneratorInterface::ABSOLUTE_URL)
                : $this->checkoutUrl());
        }

        if (PayPalPaymentDetails::fromPayment($payment)->payPalOrderId() !== $payPalOrderId) {
            return $this->returnTo($this->checkoutUrl(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->expressCheckoutCompleter->complete($payment);
        } catch (PaymentAmountMismatchException) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.order_total_changed');

            return $this->returnTo($this->checkoutUrl());
        }

        $request->getSession()->set('sylius_order_id', $order->getId());

        try {
            $paymentRequest = $this->approvedPayPalOrderPaymentRequestDispatcher->dispatch($payment);
        } catch (HandlerFailedException $exception) {
            $this->logger->error(
                sprintf('Completing the express checkout PayPal order "%s" failed: %s', $payPalOrderId, $exception->getMessage()),
                ['exception' => $exception],
            );

            return $this->returnTo($this->orderPayUrl($order));
        }

        return $this->returnTo($this->paymentRequestPayUrlProvider->getUrl($paymentRequest, UrlGeneratorInterface::ABSOLUTE_URL));
    }

    private function returnTo(string $url, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse(['return_url' => $url], $status);
    }

    private function checkoutUrl(): string
    {
        return $this->router->generate('sylius_shop_checkout_complete', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function orderPayUrl(OrderInterface $order): string
    {
        return $this->router->generate('sylius_shop_order_pay', ['tokenValue' => $order->getTokenValue()], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
