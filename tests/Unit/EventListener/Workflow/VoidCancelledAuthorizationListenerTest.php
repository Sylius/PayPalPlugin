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

namespace Tests\Sylius\PayPalPlugin\Unit\EventListener\Workflow;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\PayPalPlugin\Dispatcher\VoidAuthorizationPaymentRequestDispatcherInterface;
use Sylius\PayPalPlugin\EventListener\Workflow\VoidCancelledAuthorizationListener;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

final class VoidCancelledAuthorizationListenerTest extends TestCase
{
    private VoidAuthorizationPaymentRequestDispatcherInterface&MockObject $dispatcher;

    private LoggerInterface&MockObject $logger;

    private VoidCancelledAuthorizationListener $listener;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(VoidAuthorizationPaymentRequestDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new VoidCancelledAuthorizationListener($this->dispatcher, $this->logger);
    }

    public function test_it_voids_the_authorization_of_a_paypal_payment_cancelled_from_authorized(): void
    {
        $payment = $this->payment('sylius_paypal', ['authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'CREATED']);

        $this->dispatcher->expects(self::once())->method('dispatch')->with($payment);

        ($this->listener)($this->cancelledFrom($payment, 'authorized'));
    }

    public function test_it_ignores_a_payment_cancelled_from_another_state(): void
    {
        $this->dispatcher->expects(self::never())->method('dispatch');

        ($this->listener)($this->cancelledFrom($this->payment('sylius_paypal', ['authorization_id' => 'AUTHORIZATION_ID']), 'new'));
    }

    public function test_it_does_not_void_an_authorization_paypal_has_already_released(): void
    {
        $this->dispatcher->expects(self::never())->method('dispatch');

        $this->listener->void($this->payment('sylius_paypal', ['authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'VOIDED']));
        $this->listener->void($this->payment('sylius_paypal', ['authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'EXPIRED']));
        $this->listener->void($this->payment('sylius_paypal', ['authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'DENIED']));
    }

    public function test_it_does_not_void_a_payment_without_a_paypal_authorization(): void
    {
        $this->dispatcher->expects(self::never())->method('dispatch');

        $this->listener->void($this->payment('sylius_paypal', ['paypal_order_id' => 'PAYPAL_ORDER_ID']));
        $this->listener->void($this->payment('offline', ['authorization_id' => 'AUTHORIZATION_ID']));
    }

    public function test_it_logs_a_void_that_could_not_be_dispatched_instead_of_failing_the_cancellation(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(new \RuntimeException('PayPal is down'));

        $this->logger->expects(self::once())->method('error')->with(self::stringContains('PayPal is down'));

        $this->listener->void($this->payment('sylius_paypal', ['authorization_id' => 'AUTHORIZATION_ID', 'authorization_status' => 'CREATED']));
    }

    /** @param array<string, mixed> $details */
    private function payment(string $factoryName, array $details): Payment
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName($factoryName);
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);
        $payment = new Payment();
        $payment->setMethod($paymentMethod);
        $payment->setDetails($details);

        return $payment;
    }

    private function cancelledFrom(Payment $payment, string $state): CompletedEvent
    {
        return new CompletedEvent($payment, new Marking(['cancelled' => 1]), new Transition('cancel', $state, 'cancelled'));
    }
}
