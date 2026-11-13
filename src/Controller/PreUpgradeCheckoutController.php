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

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Payment\Model\PaymentInterface as BasePaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Checker\PreUpgradePayPalOrderCheckerInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Exception\PayPalApiException;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Processor\PaymentSettlementProcessorInterface;
use Sylius\PayPalPlugin\Processor\PreUpgradePayPalOrderSettlementProcessor;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PreUpgradeCheckoutController
{
    private const BUYER_CANCELLATION_ERROR_CODE = 'payment_error';

    /** @param OrderRepositoryInterface<OrderInterface> $orderRepository */
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private PaypalPaymentQueryInterface $paypalPaymentQuery,
        private PreUpgradePayPalOrderCheckerInterface $preUpgradePayPalOrderChecker,
        private PayerActionCheckerInterface $payerActionChecker,
        private PaymentSettlementProcessorInterface $paymentSettlementProcessor,
        private StateMachineInterface $stateMachine,
        private EntityManagerInterface $paymentManager,
        private UrlGeneratorInterface $router,
        private RequestStack $requestStack,
    ) {
    }

    public function payWithPayPalFormAction(string $orderToken): Response
    {
        return new RedirectResponse($this->router->generate('sylius_shop_order_pay', ['tokenValue' => $orderToken]));
    }

    public function completePayPalOrderAction(Request $request, string $token): Response
    {
        $order = $this->order($token);
        $payment = $this->paymentAwaitingPreUpgradePayPalOrder($order);
        if (null === $payment) {
            return new JsonResponse([], Response::HTTP_CONFLICT);
        }

        $payPalOrderId = (string) PayPalPaymentDetails::fromPayment($payment)->payPalOrderId();
        $requestedPayPalOrderId = $request->getPayload()->getString('payPalOrderId');
        if ('' !== $requestedPayPalOrderId && $requestedPayPalOrderId !== $payPalOrderId) {
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->settle($payment);

        return new JsonResponse([
            'orderId' => $payPalOrderId,
            'orderID' => $payPalOrderId,
            'status' => $payment->getState(),
            'return_url' => $this->destination($order, $payment),
        ]);
    }

    public function cancelCheckoutPaymentAction(Request $request): Response
    {
        try {
            $payment = $this->paypalPaymentQuery->getForCancellationByOrderId($request->getPayload()->getString('payPalOrderId'));
        } catch (PaymentNotFoundException) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        if (
            null !== $payment &&
            $this->preUpgradePayPalOrderChecker->isAwaitingPreUpgradePayPalOrder($payment) &&
            !$this->payerActionChecker->isAwaitingPayerAction($payment)
        ) {
            $this->end($payment, PaymentTransitions::TRANSITION_CANCEL);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    public function payerActionReturnAction(string $token, string $nonce): Response
    {
        $order = $this->order($token);
        $payment = $this->paymentAwaitingPreUpgradePayPalOrder($order);
        if (null !== $payment && PayPalPaymentDetails::fromPayment($payment)->matchesLegacyPayerActionReturnNonce($nonce)) {
            $this->settle($payment);
        }

        return new RedirectResponse($this->destination($order, $payment ?? $order->getLastPayment()));
    }

    public function payerActionCancelAction(Request $request, string $token, string $nonce): Response
    {
        $order = $this->order($token);
        $payment = $this->paymentAwaitingPreUpgradePayPalOrder($order);
        if (null !== $payment && PayPalPaymentDetails::fromPayment($payment)->matchesLegacyPayerActionCancelNonce($nonce)) {
            $this->settle($payment);
            $this->end($payment, $this->isRefusal($request->query->getString('errorcode'))
                ? PaymentTransitions::TRANSITION_FAIL
                : PaymentTransitions::TRANSITION_CANCEL);
        }

        return new RedirectResponse($this->destination($order, $payment ?? $order->getLastPayment()));
    }

    private function order(string $token): OrderInterface
    {
        $order = $this->orderRepository->findOneByTokenValue($token);
        if (!$order instanceof OrderInterface) {
            throw new NotFoundHttpException(sprintf('Order with token "%s" does not exist.', $token));
        }

        return $order;
    }

    private function paymentAwaitingPreUpgradePayPalOrder(OrderInterface $order): ?PaymentInterface
    {
        $payment = $order->getLastPayment(BasePaymentInterface::STATE_PROCESSING);
        if (null === $payment || !$this->preUpgradePayPalOrderChecker->isAwaitingPreUpgradePayPalOrder($payment)) {
            return null;
        }

        return $payment;
    }

    private function settle(PaymentInterface $payment): void
    {
        try {
            $this->paymentSettlementProcessor->settle($payment);
        } catch (PayPalApiException) {
        }
    }

    private function isRefusal(string $errorCode): bool
    {
        return '' !== $errorCode && self::BUYER_CANCELLATION_ERROR_CODE !== $errorCode;
    }

    private function end(PaymentInterface $payment, string $transition): void
    {
        $connection = $this->paymentManager->getConnection();
        $connection->beginTransaction();

        try {
            $this->paymentManager->refresh($payment, LockMode::PESSIMISTIC_WRITE);
            $ended =
                BasePaymentInterface::STATE_PROCESSING === $payment->getState() &&
                $this->stateMachine->can($payment, PaymentTransitions::GRAPH, $transition)
            ;
            if ($ended) {
                $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, $transition);
                $this->paymentManager->flush();
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        if ($ended && PaymentTransitions::TRANSITION_CANCEL === $transition) {
            $this->addFlash('info', 'sylius_paypal.payment_cancelled');
        }
    }

    private function destination(OrderInterface $order, ?PaymentInterface $payment): string
    {
        $tokenValue = (string) $order->getTokenValue();

        $threeDSecureFailure = null === $payment ? null : PayPalPaymentDetails::fromPayment($payment)->threeDSecureFailure();
        if (
            null !== $threeDSecureFailure &&
            in_array($payment?->getState(), [BasePaymentInterface::STATE_CANCELLED, BasePaymentInterface::STATE_FAILED], true)
        ) {
            if (PreUpgradePayPalOrderSettlementProcessor::THREE_D_SECURE_RETRY === $threeDSecureFailure) {
                $this->addFlash('error', 'sylius_paypal.three_d_secure_retry');

                return $this->router->generate('sylius_shop_order_pay', ['tokenValue' => $tokenValue]);
            }

            $this->addFlash('error', 'sylius_paypal.three_d_secure_declined');

            return $this->router->generate('sylius_shop_order_show', ['tokenValue' => $tokenValue]);
        }

        return match ($payment?->getState()) {
            BasePaymentInterface::STATE_COMPLETED, BasePaymentInterface::STATE_AUTHORIZED => $this->router->generate('sylius_shop_order_show', ['tokenValue' => $tokenValue]),
            BasePaymentInterface::STATE_PROCESSING => $this->pendingDestination($tokenValue),
            BasePaymentInterface::STATE_CANCELLED => $this->router->generate('sylius_shop_order_pay', ['tokenValue' => $tokenValue]),
            default => $this->failedDestination($tokenValue),
        };
    }

    private function pendingDestination(string $tokenValue): string
    {
        $this->addFlash('info', 'sylius_paypal.payment_pending');

        return $this->router->generate('sylius_shop_order_show', ['tokenValue' => $tokenValue]);
    }

    private function failedDestination(string $tokenValue): string
    {
        $this->addFlash('error', 'sylius_paypal.something_went_wrong');

        return $this->router->generate('sylius_shop_order_pay', ['tokenValue' => $tokenValue]);
    }

    private function addFlash(string $type, string $message): void
    {
        FlashBagProvider::getFlashBag($this->requestStack)->add($type, $message);
    }
}
