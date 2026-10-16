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

namespace Sylius\PayPalPlugin\Dispatcher;

use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Factory\PaymentRequestFactoryInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\PaymentRequestTransitions;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Command\CapturePayPalOrderPaymentRequest;
use Sylius\PayPalPlugin\CommandHandler\CreatePayPalOrderPaymentRequestHandler;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CapturePayPalOrderPaymentRequestDispatcher implements CapturePayPalOrderPaymentRequestDispatcherInterface
{
    /**
     * @param PaymentRequestFactoryInterface<PaymentRequestInterface> $paymentRequestFactory
     * @param PaymentRequestRepositoryInterface<PaymentRequestInterface> $paymentRequestRepository
     */
    public function __construct(
        private PaymentRequestFactoryInterface $paymentRequestFactory,
        private PaymentRequestRepositoryInterface $paymentRequestRepository,
        private StateMachineInterface $stateMachine,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(PaymentInterface $payment): PaymentRequestInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $paymentRequest = $this->paymentRequestFactory->create($payment, $paymentMethod);
        $paymentRequest->setAction(PaymentRequestInterface::ACTION_CAPTURE);
        $paymentRequest->setResponseData([
            CreatePayPalOrderPaymentRequestHandler::RESPONSE_PAYPAL_ORDER_ID => PayPalPaymentDetails::fromPayment($payment)->payPalOrderId(),
        ]);
        $this->stateMachine->apply($paymentRequest, PaymentRequestTransitions::GRAPH, PaymentRequestTransitions::TRANSITION_PROCESS);

        $this->paymentRequestRepository->add($paymentRequest);

        $this->commandBus->dispatch(new CapturePayPalOrderPaymentRequest($paymentRequest->getId()));

        return $paymentRequest;
    }
}
