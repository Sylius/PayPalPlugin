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
use Psr\Log\LoggerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Checker\PayerActionChecker;
use Sylius\PayPalPlugin\Checker\PayerActionCheckerInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalPaymentOnErrorAction
{
    private PayerActionCheckerInterface $payerActionChecker;

    public function __construct(
        private RequestStack $flashBagOrRequestStack,
        private LoggerInterface $logger,
        private ?PaypalPaymentQueryInterface $paypalPaymentQuery = null,
        private ?StateMachineInterface $stateMachine = null,
        private ?OrderProcessorInterface $orderPaymentProcessor = null,
        private ?ObjectManager $objectManager = null,
        ?PayerActionCheckerInterface $payerActionChecker = null,
    ) {
        $this->payerActionChecker = $payerActionChecker ?? new PayerActionChecker();

        if (!$this->canCancelPayments()) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing instances of "%s", "%s", "%s" and "%s" as the third to sixth arguments is deprecated and will be prohibited in 3.0.',
                PaypalPaymentQueryInterface::class,
                StateMachineInterface::class,
                OrderProcessorInterface::class,
                ObjectManager::class,
            );
        }
    }

    public function __invoke(Request $request): Response
    {
        $content = $request->getContent();
        $payload = $this->decodePayload($content);

        $error = $payload['error'] ?? null;
        $this->logger->error(is_string($error) ? $error : $content);

        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)
            ->add('error', 'sylius_paypal.something_went_wrong')
        ;

        $payPalOrderId = $payload['payPalOrderId'] ?? null;
        if (is_string($payPalOrderId) && '' !== $payPalOrderId) {
            $this->cancelPayment($payPalOrderId);
        }

        return new Response();
    }

    /** @return array<string, mixed> */
    private function decodePayload(string $content): array
    {
        if ('' === $content) {
            return [];
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function cancelPayment(string $payPalOrderId): void
    {
        if (!$this->canCancelPayments()) {
            return;
        }

        try {
            $payment = $this->paypalPaymentQuery->getForCancellationByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return;
        }

        if (
            null === $payment ||
            $this->payerActionChecker->isAwaitingPayerAction($payment) ||
            !$this->stateMachine->can($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL)
        ) {
            return;
        }

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_CANCEL);

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $this->orderPaymentProcessor->process($order);
        $this->objectManager->flush();
    }

    private function canCancelPayments(): bool
    {
        return
            null !== $this->paypalPaymentQuery &&
            null !== $this->stateMachine &&
            null !== $this->orderPaymentProcessor &&
            null !== $this->objectManager
        ;
    }
}
