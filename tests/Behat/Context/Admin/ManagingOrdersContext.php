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
use Doctrine\Persistence\ObjectManager;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Behat\Page\Admin\Order\ShowPageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class ManagingOrdersContext implements Context
{
    private ?int $refundAmount = null;

    public function __construct(
        private readonly StateMachineInterface $stateMachine,
        private readonly ObjectManager $objectManager,
        private readonly KernelBrowser $client,
        private readonly ShowPageInterface $showPage,
    ) {
    }

    /**
     * @Given /^(this order) is already paid as "([^"]+)" PayPal order$/
     * @Given /^(this order) is already paid as "([^"]+)" PayPal order with "([^"]+)" PayPal payment$/
     */
    public function thisOrderIsAlreadyPaidAsPayPalOrder(
        OrderInterface $order,
        string $payPalOrderId,
        ?string $payPalPaymentId = null,
    ): void {
        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        $details = ['paypal_order_id' => $payPalOrderId];
        if ($payPalPaymentId !== null) {
            $details['paypal_payment_id'] = $payPalPaymentId;
        }
        $payment->setDetails($details);

        $this->stateMachine->apply($payment, PaymentTransitions::GRAPH, PaymentTransitions::TRANSITION_COMPLETE);

        $this->refundAmount = $order->getTotal();

        $this->objectManager->flush();
    }

    /**
     * @When request from PayPal about :payPalOrderId order refund has been received
     */
    public function requestFromPayPalAboutOrderRefundHasBeenReceived(string $payPalOrderId): void
    {
        $data = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource_type' => 'refund',
            'resource' => [
                'id' => $payPalOrderId,
                'amount' => ['currency_code' => 'USD', 'amount' => (string) ($this->refundAmount / 100)],
                'status' => 'COMPLETED',
                'links' => [
                    ['rel' => 'up', 'href' => $payPalOrderId],
                ],
            ],
        ]);

        $this->client->request('POST', '/paypal-webhook/api/', [], [], ['Content-Type' => 'application/json'], $data);

        Assert::same($this->client->getResponse()->getStatusCode(), Response::HTTP_NO_CONTENT);
    }

    /**
     * @When I view the summary of the refunded order :order
     */
    public function iSeeTheRefundedOrder(OrderInterface $order): void
    {
        // During calling `open` method it's verified and FOR SOME REASON it constantly tries to compare it
        // with the previously visited URL "/paypal-webhook/api/"
        // This override is temporary until we find out what is the problem
        $this->showPage->tryToOpen(['id' => $order->getId()]);
    }
}
