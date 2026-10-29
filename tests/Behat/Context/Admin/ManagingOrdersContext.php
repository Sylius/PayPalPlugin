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

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Admin;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Page\Admin\Order\ShowPageInterface;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalApiMocker;
use Tests\Sylius\PayPalPlugin\Service\DummyRefundPaymentApi;
use Webmozart\Assert\Assert;

final class ManagingOrdersContext implements Context
{
    private ?PaymentInterface $paidPayment = null;

    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly ObjectManager $objectManager,
        private readonly KernelBrowser $client,
        private readonly ShowPageInterface $showPage,
        private readonly PayPalApiMocker $payPalApiMocker,
        private readonly NotificationCheckerInterface $notificationChecker,
    ) {
    }

    #[Given('/^(this order) is already paid as "([^"]+)" PayPal order whose refund PayPal rejects$/')]
    public function thisOrderIsAlreadyPaidAsPayPalOrderWhoseRefundPayPalRejects(OrderInterface $order, string $payPalOrderId): void
    {
        $this->payOrderWithPayPal($order, $payPalOrderId);

        $this->payPalApiMocker->mockOrderDetailsWithCapture($payPalOrderId, captureId: DummyRefundPaymentApi::REJECTED_CAPTURE_ID);
    }

    #[Given('/^(this order) is already paid as "([^"]+)" PayPal order$/')]
    public function thisOrderIsAlreadyPaidAsPayPalOrder(OrderInterface $order, string $payPalOrderId): void
    {
        $this->payOrderWithPayPal($order, $payPalOrderId);
    }

    #[Given('/^(this order) is already paid as "([^"]+)" PayPal order with "([^"]+)" PayPal payment$/')]
    public function thisOrderIsAlreadyPaidAsPayPalOrderWithPayPalPayment(
        OrderInterface $order,
        string $payPalOrderId,
        string $payPalPaymentId,
    ): void {
        $this->payOrderWithPayPal($order, $payPalOrderId, $payPalPaymentId);

        $this->payPalApiMocker->mockOrderDetailsWithCapture($payPalOrderId);
    }

    #[When('request from PayPal about :payPalOrderId order refund has been received')]
    public function requestFromPayPalAboutOrderRefundHasBeenReceived(string $payPalOrderId): void
    {
        Assert::notNull($this->paidPayment);
        $refundId = sprintf('REFUND-%s', $payPalOrderId);
        $this->payPalApiMocker->mockRefundDetails($refundId);

        $data = json_encode([
            'id' => sprintf('WH-%s', $refundId),
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource_type' => 'refund',
            'resource' => [
                'id' => $refundId,
                'status' => 'COMPLETED',
                'supplementary_data' => ['related_ids' => ['order_id' => $payPalOrderId]],
            ],
        ]);

        $this->client->request(
            'POST',
            sprintf('/payment-methods/%s', (string) $this->paidPayment->getMethod()?->getCode()),
            [],
            [],
            ['Content-Type' => 'application/json'],
            (string) $data,
        );

        Assert::same($this->client->getResponse()->getStatusCode(), Response::HTTP_NO_CONTENT);
    }

    #[Then('I should be notified that PayPal rejected the refund')]
    public function iShouldBeNotifiedThatPayPalRejectedTheRefund(): void
    {
        $this->notificationChecker->checkNotification(
            'REFUND_AMOUNT_EXCEEDED',
            NotificationType::failure(),
        );
    }

    #[When('I view the summary of the refunded order :order')]
    public function iSeeTheRefundedOrder(OrderInterface $order): void
    {
        // During calling `open` method it's verified and FOR SOME REASON it constantly tries to compare it
        // with the previously visited webhook URL
        // This override is temporary until we find out what is the problem
        $this->showPage->tryToOpen(['id' => $order->getId()]);
    }

    private function payOrderWithPayPal(OrderInterface $order, string $payPalOrderId, ?string $payPalPaymentId = null): void
    {
        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        $details = PayPalPaymentDetails::create()->withPayPalOrderId($payPalOrderId)->toArray();
        if ($payPalPaymentId !== null) {
            $details['paypal_payment_id'] = $payPalPaymentId;
        }
        $payment->setDetails($details);

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $this->paidPayment = $payment;

        $this->objectManager->flush();
    }
}
