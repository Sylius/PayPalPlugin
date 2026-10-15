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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\PayumBundle\Request\GetStatus;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Payum\Action\AuthorizeAction;

final class AuthorizeActionTest extends TestCase
{
    private AuthorizeAction $authorizeAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizeAction = new AuthorizeAction();
    }

    #[Test]
    public function it_implements_action_interface(): void
    {
        self::assertInstanceOf(ActionInterface::class, $this->authorizeAction);
    }

    #[Test]
    public function it_marks_payment_as_created(): void
    {
        $request = $this->createMock(Authorize::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getModel')->willReturn($payment);
        $payment->expects(self::once())->method('setDetails')->with(['status' => PayPalPaymentStatus::Created->value]);

        $this->authorizeAction->execute($request);
    }

    #[Test]
    public function it_throws_an_exception_if_request_type_is_invalid(): void
    {
        $request = $this->createMock(GetStatus::class);

        $this->expectException(RequestNotSupportedException::class);
        $this->authorizeAction->execute($request);
    }

    #[Test]
    public function it_supports_authorize_request_with_payment_as_first_model(): void
    {
        $request = $this->createMock(Authorize::class);
        $payment = $this->createMock(PaymentInterface::class);

        $request->method('getModel')->willReturn($payment);

        self::assertTrue($this->authorizeAction->supports($request));
    }

    #[Test]
    public function it_does_not_support_request_other_than_authorize(): void
    {
        $request = $this->createMock(Capture::class);

        self::assertFalse($this->authorizeAction->supports($request));
    }

    #[Test]
    public function it_does_not_support_request_with_first_model_other_than_payment(): void
    {
        $request = $this->createMock(Authorize::class);
        $request->method('getModel')->willReturn('badObject');

        self::assertFalse($this->authorizeAction->supports($request));
    }
}
