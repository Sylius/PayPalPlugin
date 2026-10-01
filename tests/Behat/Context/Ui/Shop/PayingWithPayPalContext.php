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

namespace Tests\Sylius\PayPalPlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Tests\Sylius\PayPalPlugin\Behat\Page\Shop\PayWithPayPalPage;
use Tests\Sylius\PayPalPlugin\Service\DummyOrderDetailsApi;
use Tests\Sylius\PayPalPlugin\Service\VoidPayPalPaymentCompleteProcessor;
use Webmozart\Assert\Assert;

final readonly class PayingWithPayPalContext implements Context
{
    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private PayWithPayPalPage $payWithPayPalPage,
        private KernelBrowser $client,
        private DummyOrderDetailsApi $orderDetailsApi,
        private VoidPayPalPaymentCompleteProcessor $paymentCompleteProcessor,
    ) {
    }

    #[Given('PayPal will approve the capture of my card payment')]
    public function payPalWillApproveTheCaptureOfMyCardPayment(): void
    {
        $this->paymentCompleteProcessor->completeSuccessfullyNext();
    }

    #[Given('PayPal will decline the 3D Secure challenge for my card payment')]
    public function payPalWillDeclineTheThreeDSecureChallengeForMyCardPayment(): void
    {
        $this->configureThreeDSecureResult(authenticationStatus: 'N');
    }

    #[Given('PayPal will ask to retry the 3D Secure challenge for my card payment')]
    public function payPalWillAskToRetryTheThreeDSecureChallengeForMyCardPayment(): void
    {
        $this->configureThreeDSecureResult(authenticationStatus: 'C');
    }

    #[When('I go to the PayPal payment page of my order')]
    public function iGoToThePayPalPaymentPageOfMyOrder(): void
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');
        /** @var PaymentInterface $payment */
        $payment = $order->getLastPayment();

        $this->payWithPayPalPage->open([
            '_locale' => 'en_US',
            'orderToken' => $order->getTokenValue(),
            'paymentId' => $payment->getId(),
        ]);
    }

    #[When('I start a card payment for my order')]
    public function iStartACardPaymentForMyOrder(): void
    {
        // Keep one kernel/container (and so the same test-double instances, such as
        // $orderDetailsApi and $paymentCompleteProcessor above) alive for the rest of the
        // scenario. The client reboots the kernel before each request by default, which
        // would otherwise silently discard a Given step's configuration of a test double
        // before a later request gets to exercise it.
        $this->client->disableReboot();

        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        $this->client->request(
            'POST',
            sprintf('/en_US/create-pay-pal-order/%s', $order->getTokenValue()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['paymentSource' => 'card'], \JSON_THROW_ON_ERROR),
        );

        Assert::same(
            $this->client->getResponse()->getStatusCode(),
            200,
            'Could not start a card payment attempt for the order.',
        );
    }

    #[When('I complete the card payment')]
    public function iCompleteTheCardPayment(): void
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        $this->client->request(
            'POST',
            sprintf('/en_US/complete-pay-pal-order/%s', $order->getTokenValue()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}',
        );
    }

    #[Then('I should be able to pay with Trustly')]
    public function iShouldBeAbleToPayWithTrustly(): void
    {
        Assert::true(
            $this->payWithPayPalPage->hasTrustlyButton(),
            'The Trustly button is not rendered on the payment page.',
        );
    }

    #[Then('I should be able to pay with PayPal')]
    public function iShouldBeAbleToPayWithPayPal(): void
    {
        Assert::true(
            $this->payWithPayPalPage->hasPayPalButton(),
            'The PayPal wallet button is not rendered on the payment page.',
        );
    }

    #[Then('I should be able to pay by card')]
    public function iShouldBeAbleToPayByCard(): void
    {
        Assert::true(
            $this->payWithPayPalPage->hasCardFields(),
            'The card fields are not rendered on the payment page.',
        );
    }

    #[Then('the payment page should be a part of the shop')]
    public function thePaymentPageShouldBeAPartOfTheShop(): void
    {
        Assert::true(
            $this->payWithPayPalPage->isRenderedInTheShopLayout(),
            'The payment page does not extend the shop layout.',
        );
    }

    #[Then('the card payment should be completed')]
    public function theCardPaymentShouldBeCompleted(): void
    {
        $response = $this->completeOrderResponse();

        Assert::same($response['status'], PaymentInterface::STATE_COMPLETED);
        Assert::contains(
            $response['return_url'],
            '/order/thank-you',
            'A completed card payment should send the buyer to the thank-you page.',
        );
    }

    #[Then('the card payment should be declined, leaving the order payable')]
    public function theCardPaymentShouldBeDeclined(): void
    {
        $response = $this->completeOrderResponse();

        Assert::same($response['status'], PaymentInterface::STATE_CANCELLED);
        Assert::contains(
            $response['return_url'],
            '/order/',
            'A declined (non-retryable) card payment should send the buyer to the order page.',
        );
        Assert::notContains(
            $response['return_url'],
            '/pay-with-paypal/',
            'A declined (non-retryable) card payment should not send the buyer back to the payment page - that is the retryable case.',
        );
    }

    #[Then('the card payment should require a retry, returning the buyer to the payment page')]
    public function theCardPaymentShouldRequireARetry(): void
    {
        $response = $this->completeOrderResponse();

        Assert::same($response['status'], PaymentInterface::STATE_CANCELLED);
        Assert::contains(
            $response['return_url'],
            '/pay-with-paypal/',
            'A retryable card payment should send the buyer back to the PayPal payment page.',
        );
    }

    private function configureThreeDSecureResult(string $authenticationStatus): void
    {
        $this->orderDetailsApi->useResponse([
            'status' => 'COMPLETED',
            'payment_source' => [
                'card' => [
                    'authentication_result' => [
                        'three_d_secure' => [
                            'enrollment_status' => 'Y',
                            'authentication_status' => $authenticationStatus,
                        ],
                        'liability_shift' => 'NO',
                    ],
                ],
            ],
            'purchase_units' => [['payments' => ['captures' => [['id' => '123123']]]]],
        ]);
    }

    /** @return array<string, mixed> */
    private function completeOrderResponse(): array
    {
        $response = json_decode($this->client->getResponse()->getContent() ?: '{}', true, flags: \JSON_THROW_ON_ERROR);

        Assert::keyExists($response, 'status', 'The complete-order response did not carry a payment status.');
        Assert::keyExists($response, 'return_url', 'The complete-order response did not carry a return_url.');

        return $response;
    }
}
