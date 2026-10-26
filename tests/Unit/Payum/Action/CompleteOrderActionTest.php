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

namespace Tests\Sylius\PayPalPlugin\Unit\Payum\Action;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Payum\Core\Action\ActionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Event\PayPalPaymentTokenCreated;
use Sylius\PayPalPlugin\Payum\Action\CompleteOrderAction;
use Sylius\PayPalPlugin\Payum\Request\CompleteOrder;
use Sylius\PayPalPlugin\Processor\PaymentCaptureProcessorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CompleteOrderActionTest extends TestCase
{
    private PaymentCaptureProcessorInterface&MockObject $paymentCaptureProcessor;

    private EntityManagerInterface&MockObject $paymentManager;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&MockObject $logger;

    /** @var list<string> */
    private array $calls = [];

    private CompleteOrderAction $completeOrderAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentCaptureProcessor = $this->createMock(PaymentCaptureProcessorInterface::class);
        $this->paymentManager = $this->createMock(EntityManagerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paymentManager->method('wrapInTransaction')->willReturnCallback(function (callable $func): mixed {
            $this->calls[] = 'begin';
            $result = $func();
            $this->calls[] = 'commit';

            return $result;
        });

        $this->completeOrderAction = new CompleteOrderAction(
            $this->paymentCaptureProcessor,
            $this->paymentManager,
            $this->eventDispatcher,
            $this->logger,
        );
    }

    public function test_it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->completeOrderAction);
    }

    public function test_it_captures_the_paypal_order_of_the_payment(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'card']);

        $this->paymentCaptureProcessor->expects(self::once())->method('capture')->with($payment);

        $this->completeOrderAction->execute($this->completeOrderOf($payment));
    }

    public function test_it_captures_the_payment_locked_and_read_again_inside_a_transaction(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'card']);
        $this->paymentManager
            ->expects(self::once())
            ->method('refresh')
            ->with($payment, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(function (): void {
                $this->calls[] = 'lock';
            })
        ;
        $this->paymentCaptureProcessor->method('capture')->willReturnCallback(function (): array {
            $this->calls[] = 'capture';

            return [];
        });

        $this->completeOrderAction->execute($this->completeOrderOf($payment));

        self::assertSame(['begin', 'lock', 'capture', 'commit'], $this->calls);
    }

    public function test_it_announces_the_vault_token_the_capture_revealed_after_the_transaction_commits(): void
    {
        $details = ['paypal_order_id' => '123123', 'payment_source' => 'paypal'];
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturnCallback(static function () use (&$details): array {
            return $details;
        });
        $this->paymentCaptureProcessor->method('capture')->willReturnCallback(static function () use (&$details): array {
            $details['vault_id'] = 'VAULT_ID';

            return [];
        });

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function (PayPalPaymentTokenCreated $event) use ($payment): bool {
                return ['begin', 'commit'] === $this->calls && $payment === $event->payment && 'VAULT_ID' === $event->vaultId;
            }))
            ->willReturnArgument(0)
        ;

        $this->completeOrderAction->execute($this->completeOrderOf($payment));
    }

    public function test_it_does_not_announce_a_vault_token_the_payment_already_carried(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'paypal', 'vault_id' => 'VAULT_ID']);

        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->completeOrderAction->execute($this->completeOrderOf($payment));
    }

    public function test_it_never_captures_an_order_paypal_completes_on_payment_approval(): void
    {
        $payment = $this->paymentWith(['paypal_order_id' => '123123', 'payment_source' => 'trustly']);

        $this->paymentCaptureProcessor->expects(self::never())->method('capture');
        $this->paymentManager->expects(self::never())->method('wrapInTransaction');
        $this->logger->expects(self::once())->method('warning');

        $this->completeOrderAction->execute($this->completeOrderOf($payment));
    }

    public function test_it_supports_complete_order_request_with_payment_as_model(): void
    {
        self::assertTrue($this->completeOrderAction->supports($this->completeOrderOf($this->createMock(PaymentInterface::class))));
    }

    /** @param array<string, mixed> $details */
    private function paymentWith(array $details): PaymentInterface&MockObject
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }

    private function completeOrderOf(PaymentInterface $payment): CompleteOrder&MockObject
    {
        $request = $this->createMock(CompleteOrder::class);
        $request->method('getModel')->willReturn($payment);

        return $request;
    }
}
