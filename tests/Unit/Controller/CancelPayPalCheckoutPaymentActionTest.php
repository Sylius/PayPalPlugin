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

namespace Tests\Sylius\PayPalPlugin\Unit\Controller;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Controller\CancelPayPalCheckoutPaymentAction;
use Sylius\PayPalPlugin\Manager\PaymentStateManagerInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CancelPayPalCheckoutPaymentActionTest extends TestCase
{
    private PaymentStateManagerInterface&MockObject $paymentStateManager;

    private PaypalPaymentQueryInterface&MockObject $paypalPaymentQuery;

    private CancelPayPalCheckoutPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentStateManager = $this->createMock(PaymentStateManagerInterface::class);
        $this->paypalPaymentQuery = $this->createMock(PaypalPaymentQueryInterface::class);

        $this->action = new CancelPayPalCheckoutPaymentAction(
            paymentProvider: null,
            paymentStateManager: $this->paymentStateManager,
            paypalPaymentQuery: $this->paypalPaymentQuery,
        );
    }

    public function test_it_flashes_that_the_payment_was_cancelled_and_cancels_it(): void
    {
        $payment = $this->createStub(PaymentInterface::class);
        $this->paypalPaymentQuery->method('getForCancellationByOrderId')->with('PAYPAL_ORDER_ID')->willReturn($payment);

        $this->paymentStateManager->expects(self::once())->method('cancel')->with($payment);

        $request = $this->request();
        $response = ($this->action)($request);

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        self::assertSame(['sylius_paypal.payment_cancelled'], $request->getSession()->getBag('flashes')->get('success'));
        self::assertSame([], $request->getSession()->getBag('flashes')->get('error'));
    }

    private function request(): Request
    {
        $request = new Request([], [], [], [], [], [], (string) json_encode(['payPalOrderId' => 'PAYPAL_ORDER_ID']));
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }
}
