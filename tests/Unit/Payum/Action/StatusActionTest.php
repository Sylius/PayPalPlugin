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

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Payum\Action\StatusAction;

final class StatusActionTest extends TestCase
{
    private StatusAction $statusAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statusAction = new StatusAction();
    }

    #[Test]
    public function it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->statusAction);
    }

    #[Test]
    public function it_marks_request_as_new(): void
    {
        $request = $this->createMock(GetStatus::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getFirstModel')->willReturn($payment);
        $request->method('getModel')->willReturn(['status' => PayPalPaymentStatus::Created->value]);
        $request->expects(self::once())->method('markNew');

        $this->statusAction->execute($request);
    }

    #[Test]
    public function it_marks_request_as_pending(): void
    {
        $request = $this->createMock(GetStatus::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getFirstModel')->willReturn($payment);
        $request->method('getModel')->willReturn(['status' => PayPalPaymentStatus::Captured->value]);
        $request->expects(self::once())->method('markPending');

        $this->statusAction->execute($request);
    }

    #[Test]
    public function it_marks_request_as_captured(): void
    {
        $request = $this->createMock(GetStatus::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getFirstModel')->willReturn($payment);
        $request->method('getModel')->willReturn(['status' => 'COMPLETED']);
        $request->expects(self::once())->method('markCaptured');

        $this->statusAction->execute($request);
    }

    #[Test]
    public function it_throws_an_exception_if_request_is_not_supported(): void
    {
        $request = $this->createMock(Capture::class);

        $this->expectException(RequestNotSupportedException::class);
        $this->statusAction->execute($request);
    }

    #[Test]
    public function it_supports_get_status_request_with_payment_as_first_model(): void
    {
        $request = $this->createMock(GetStatus::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getFirstModel')->willReturn($payment);

        self::assertTrue($this->statusAction->supports($request));
    }

    #[Test]
    public function it_does_not_support_request_other_than_get_status(): void
    {
        $request = $this->createMock(Capture::class);

        self::assertFalse($this->statusAction->supports($request));
    }

    #[Test]
    public function it_does_not_support_request_with_first_model_other_than_payment(): void
    {
        $request = $this->createMock(GetStatus::class);
        $request->method('getFirstModel')->willReturn('badObject');

        self::assertFalse($this->statusAction->supports($request));
    }
}
