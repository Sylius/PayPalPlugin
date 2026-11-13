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

namespace Tests\Sylius\PayPalPlugin\Unit\Checker;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Sylius\Component\Payment\Repository\PaymentRequestRepositoryInterface;
use Sylius\PayPalPlugin\Checker\PreUpgradePayPalOrderChecker;

final class PreUpgradePayPalOrderCheckerTest extends TestCase
{
    /** @var PaymentRequestRepositoryInterface<PaymentRequestInterface>&MockObject */
    private PaymentRequestRepositoryInterface&MockObject $paymentRequestRepository;

    private PreUpgradePayPalOrderChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentRequestRepository = $this->createMock(PaymentRequestRepositoryInterface::class);
        $this->checker = new PreUpgradePayPalOrderChecker($this->paymentRequestRepository);
    }

    public function test_it_tells_a_processing_payment_awaits_a_paypal_order_no_payment_request_created(): void
    {
        $payment = $this->payment();
        $this->paymentRequestRepository
            ->method('findOneBy')
            ->with(['payment' => $payment, 'action' => [PaymentRequestInterface::ACTION_CAPTURE, PaymentRequestInterface::ACTION_AUTHORIZE]])
            ->willReturn(null)
        ;

        self::assertTrue($this->checker->isAwaitingPreUpgradePayPalOrder($payment));
    }

    public function test_it_tells_a_payment_whose_paypal_order_a_payment_request_created_does_not_await_one_from_before_the_upgrade(): void
    {
        $this->paymentRequestRepository->method('findOneBy')->willReturn($this->createStub(PaymentRequestInterface::class));

        self::assertFalse($this->checker->isAwaitingPreUpgradePayPalOrder($this->payment()));
    }

    public function test_it_tells_a_payment_no_longer_processing_does_not_await_a_paypal_order(): void
    {
        $this->paymentRequestRepository->expects(self::never())->method('findOneBy');

        self::assertFalse($this->checker->isAwaitingPreUpgradePayPalOrder($this->payment(PaymentInterface::STATE_COMPLETED)));
    }

    public function test_it_tells_a_payment_carrying_no_paypal_order_does_not_await_one(): void
    {
        $this->paymentRequestRepository->expects(self::never())->method('findOneBy');

        self::assertFalse($this->checker->isAwaitingPreUpgradePayPalOrder($this->payment(details: [])));
    }

    /** @param array<string, mixed> $details */
    private function payment(string $state = PaymentInterface::STATE_PROCESSING, array $details = ['paypal_order_id' => 'PAYPAL_ORDER_ID']): PaymentInterface
    {
        $payment = $this->createStub(PaymentInterface::class);
        $payment->method('getState')->willReturn($state);
        $payment->method('getDetails')->willReturn($details);

        return $payment;
    }
}
