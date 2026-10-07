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
use Payum\Core\Request\Authorize;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Creator\PayPalOrderCreatorInterface;
use Sylius\PayPalPlugin\Payum\Action\CaptureAction;

final class CaptureActionTest extends TestCase
{
    private PayPalOrderCreatorInterface&MockObject $payPalOrderCreator;

    private CaptureAction $captureAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payPalOrderCreator = $this->createMock(PayPalOrderCreatorInterface::class);

        $this->captureAction = new CaptureAction($this->payPalOrderCreator);
    }

    public function test_it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->captureAction);
    }

    public function test_it_creates_the_order_with_the_payment_source_recorded_on_the_payment(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn(['status' => 'CREATED', 'payment_source' => 'google_pay']);

        $this->payPalOrderCreator->expects(self::once())->method('create')->with($payment, 'google_pay');

        $this->captureAction->execute($this->captureOf($payment));
    }

    public function test_it_creates_a_paypal_order_when_the_payment_records_no_payment_source(): void
    {
        $payment = $this->createMock(PaymentInterface::class);
        $payment->method('getDetails')->willReturn([]);

        $this->payPalOrderCreator->expects(self::once())->method('create')->with($payment, 'paypal');

        $this->captureAction->execute($this->captureOf($payment));
    }

    public function test_it_throws_an_exception_if_request_type_is_invalid(): void
    {
        $this->expectException(RequestNotSupportedException::class);

        $this->captureAction->execute($this->createMock(Authorize::class));
    }

    public function test_it_supports_capture_request_with_payment_as_first_model(): void
    {
        self::assertTrue($this->captureAction->supports($this->captureOf($this->createMock(PaymentInterface::class))));
    }

    public function test_it_does_not_support_request_other_than_capture(): void
    {
        self::assertFalse($this->captureAction->supports($this->createMock(GetStatus::class)));
    }

    public function test_it_does_not_support_request_with_first_model_other_than_payment(): void
    {
        $request = $this->createMock(Capture::class);
        $request->method('getModel')->willReturn('badObject');

        self::assertFalse($this->captureAction->supports($request));
    }

    private function captureOf(PaymentInterface $payment): Capture&MockObject
    {
        $request = $this->createMock(Capture::class);
        $request->method('getModel')->willReturn($payment);

        return $request;
    }
}
