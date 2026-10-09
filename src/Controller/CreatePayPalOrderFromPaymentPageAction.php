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

use Doctrine\Persistence\ObjectManager;
use GuzzleHttp\Exception\GuzzleException;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Provider\OrderProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalFundingSourcesConfigurationProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Resolver\CapturePaymentResolverInterface;
use Sylius\PayPalPlugin\Verifier\OrderOwnershipVerifierInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

final readonly class CreatePayPalOrderFromPaymentPageAction
{
    public function __construct(
        private StateMachineInterface $stateMachineFactory,
        private PaymentStateManagerInterface $paymentStateManager,
        private OrderProviderInterface $orderProvider,
        private CapturePaymentResolverInterface $capturePaymentResolver,
        private ?OrderProcessorInterface $orderPaymentProcessor = null,
        private ?ObjectManager $objectManager = null,
        private ?OrderOwnershipVerifierInterface $orderOwnershipVerifier = null,
        private ?PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider = null,
    ) {
        if (null === $this->orderPaymentProcessor) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of "%s" as the fifth argument is deprecated and will be prohibited in 3.0.',
                OrderProcessorInterface::class,
            );
        }
        if (null === $this->objectManager) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of "%s" as the sixth argument is deprecated and will be prohibited in 3.0.',
                ObjectManager::class,
            );
        }
        if (null === $this->orderOwnershipVerifier) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of "%s" to %s constructor is deprecated and will be required in 3.0.',
                OrderOwnershipVerifierInterface::class,
                self::class,
            );
        }
        if (null === $this->fundingSourcesConfigurationProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.2',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 3.0.',
                PayPalFundingSourcesConfigurationProviderInterface::class,
                self::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $id = $request->attributes->getInt('id');

        $order = $this->orderProvider->provideOrderById($id);
        if (null === $this->orderOwnershipVerifier) {
            throw new \RuntimeException(sprintf(
                'An instance of "%s" is required to verify order ownership.',
                OrderOwnershipVerifierInterface::class,
            ));
        }
        $this->orderOwnershipVerifier->verify($order, $request);

        $paymentSource = $this->resolvePaymentSource($request, $order);
        if (null === $paymentSource) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.payment_source_not_available');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->cancelLiveAttempt($order);

        $payment = $order->getLastPayment(PaymentInterface::STATE_CART);
        if (null === $payment) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $this->stateMachineFactory->apply($order, OrderCheckoutTransitions::GRAPH, OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT);

        $payment->setDetails(array_merge($payment->getDetails(), ['payment_source' => $paymentSource]));

        try {
            $this->capturePaymentResolver->resolve($payment);
        } catch (GuzzleException $exception) {
            /** @var FlashBagInterface $flashBag */
            $flashBag = $request->getSession()->getBag('flashes');
            $flashBag->add('error', 'sylius_paypal.something_went_wrong');

            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->paymentStateManager->create($payment);
        $this->paymentStateManager->process($payment);

        $payPalOrderId = $payment->getDetails()['paypal_order_id'];

        return new JsonResponse([
            'id' => $order->getId(),
            'orderId' => $payPalOrderId,
            'order_id' => $payPalOrderId, // BC with 2.1. Deprecated in 2.2; use "orderId" instead.
            'status' => $payment->getState(),
        ]);
    }

    private function cancelLiveAttempt(OrderInterface $order): void
    {
        if (null === $this->orderPaymentProcessor || null === $this->objectManager) {
            return;
        }

        $payment = $order->getLastPayment(PaymentInterface::STATE_PROCESSING);

        if (null === $payment || !$this->isPayPalPayment($payment)) {
            return;
        }

        $this->paymentStateManager->cancel($payment);
        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();
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

    private function resolvePaymentSource(Request $request, OrderInterface $order): ?string
    {
        $paymentSource = $request->query->get('paymentSource', PayPalPaymentSourceProviderInterface::PAYPAL);

        if (PayPalPaymentSourceProviderInterface::PAYPAL === $paymentSource) {
            return $paymentSource;
        }

        if (PayPalPaymentSourceProviderInterface::VENMO === $paymentSource && $this->isVenmoEnabled($order)) {
            return $paymentSource;
        }

        return null;
    }

    private function isVenmoEnabled(OrderInterface $order): bool
    {
        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();
        if (null === $channel || null === $this->fundingSourcesConfigurationProvider) {
            return false;
        }

        try {
            return $this->fundingSourcesConfigurationProvider->isVenmoEnabled($channel);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
