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
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalApiMocker;
use Tests\Sylius\PayPalPlugin\Behat\Page\Shop\PayWithPayPalPage;
use Webmozart\Assert\Assert;

final readonly class PayingWithPayPalContext implements Context
{
    private const THREE_D_SECURE_AUTHENTICATION_STATUS = 'paypal_three_d_secure_authentication_status';

    private const APPROVE_URL = 'paypal_approve_url';

    public function __construct(
        private SharedStorageInterface $sharedStorage,
        private PayWithPayPalPage $payWithPayPalPage,
        private KernelBrowser $client,
        private PayPalApiMocker $payPalApiMocker,
    ) {
    }

    #[Given('PayPal will approve the capture of my card payment')]
    public function payPalWillApproveTheCaptureOfMyCardPayment(): void
    {
        $this->sharedStorage->set(self::THREE_D_SECURE_AUTHENTICATION_STATUS, 'Y');
    }

    #[Given('PayPal will decline the 3D Secure challenge for my card payment')]
    public function payPalWillDeclineTheThreeDSecureChallengeForMyCardPayment(): void
    {
        $this->sharedStorage->set(self::THREE_D_SECURE_AUTHENTICATION_STATUS, 'N');
    }

    #[Given('PayPal will ask to retry the 3D Secure challenge for my card payment')]
    public function payPalWillAskToRetryTheThreeDSecureChallengeForMyCardPayment(): void
    {
        $this->sharedStorage->set(self::THREE_D_SECURE_AUTHENTICATION_STATUS, 'C');
    }

    #[When('I go to the PayPal payment page of my order')]
    public function iGoToThePayPalPaymentPageOfMyOrder(): void
    {
        $this->payWithPayPalPage->tryToOpen(['_locale' => 'en_US', 'tokenValue' => $this->order()->getTokenValue()]);
    }

    #[When('I start a card payment for my order')]
    public function iStartACardPaymentForMyOrder(): void
    {
        $this->client->request('GET', sprintf('/en_US/order/%s/pay', $this->order()->getTokenValue()));
        $payUrl = (string) $this->client->getResponse()->headers->get('Location');
        Assert::regex($payUrl, '#/payment-request/pay/[0-9a-f-]{36}$#', 'The order is not paid through a PayPal Payment Request.');

        $this->payPalApiMocker->mockCreateOrder();
        $this->client->request(
            'POST',
            sprintf('/en_US/paypal/payment-requests/%s/order', basename($payUrl)),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['paymentSource' => 'card'], \JSON_THROW_ON_ERROR),
        );

        Assert::same($this->client->getResponse()->getStatusCode(), 200, 'Could not start a card payment attempt for the order.');

        /** @var array{approve_url: string} $attempt */
        $attempt = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        $this->sharedStorage->set(self::APPROVE_URL, $attempt['approve_url']);
    }

    #[When('I complete the card payment')]
    public function iCompleteTheCardPayment(): void
    {
        $authenticationStatus = (string) $this->sharedStorage->get(self::THREE_D_SECURE_AUTHENTICATION_STATUS);

        $this->payPalApiMocker->mockCardOrderDetails(authenticationStatus: $authenticationStatus);
        if ('Y' === $authenticationStatus) {
            $this->payPalApiMocker->mockUpdateOrderAddress();
            $this->payPalApiMocker->mockCapture();
            $this->payPalApiMocker->mockOrderDetailsWithCapture(
                value: number_format($this->order()->getTotal() / 100, 2, '.', ''),
                currencyCode: (string) $this->order()->getCurrencyCode(),
            );
        }

        $this->client->followRedirects();
        $this->client->request('GET', (string) $this->sharedStorage->get(self::APPROVE_URL));
        $this->client->followRedirects(false);
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
        Assert::contains($this->currentPath(), '/order/thank-you', 'A completed card payment should send the buyer to the thank-you page.');
        Assert::notNull($this->lastPayment(PaymentInterface::STATE_COMPLETED), 'The card payment was not completed.');
    }

    #[Then('the card payment should be declined, leaving the order payable')]
    public function theCardPaymentShouldBeDeclined(): void
    {
        Assert::endsWith(
            $this->currentPath(),
            sprintf('/order/%s', $this->order()->getTokenValue()),
            'A declined (non-retryable) card payment should send the buyer to the order page.',
        );
        Assert::notNull($this->lastPayment(PaymentInterface::STATE_FAILED), 'The declined card payment was not failed.');
        Assert::notNull($this->lastPayment(PaymentInterface::STATE_NEW), 'The order is not payable again.');
    }

    #[Then('the card payment should require a retry, returning the buyer to the payment page')]
    public function theCardPaymentShouldRequireARetry(): void
    {
        Assert::contains($this->currentPath(), '/payment-request/pay/', 'A retryable card payment should send the buyer back to the PayPal payment page.');
        Assert::null($this->lastPayment(PaymentInterface::STATE_FAILED), 'A retryable card payment should not fail the payment.');
        Assert::notNull($this->lastPayment(PaymentInterface::STATE_NEW), 'The order is not payable again.');
    }

    private function order(): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->sharedStorage->get('order');

        return $order;
    }

    private function currentPath(): string
    {
        return $this->client->getRequest()->getPathInfo();
    }

    private function lastPayment(string $state): ?PaymentInterface
    {
        $entityManager = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();

        /** @var OrderInterface $order */
        $order = $entityManager->getRepository(OrderInterface::class)->findOneBy(['tokenValue' => $this->order()->getTokenValue()]);

        return $order->getLastPayment($state);
    }
}
