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

namespace Tests\Sylius\PayPalPlugin\Functional;

use Sylius\PayPalPlugin\Client\PayPalClientInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalApiMocker;
use Tests\Sylius\PayPalPlugin\Behat\Mocker\PayPalHttpClientWithExpectations;

final class PayPalHttpClientWithExpectationsTest extends KernelTestCase
{
    private PayPalHttpClientWithExpectations $httpClient;

    private PayPalApiMocker $mocker;

    private PayPalClientInterface $payPalClient;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->httpClient = self::getContainer()->get(PayPalHttpClientWithExpectations::class);
        $this->mocker = self::getContainer()->get(PayPalApiMocker::class);
        $this->payPalClient = self::getContainer()->get('sylius_paypal.client.paypal');

        $this->httpClient->resetExpectations();
    }

    protected function tearDown(): void
    {
        $this->httpClient->resetExpectations();

        parent::tearDown();
    }

    public function test_it_answers_the_paypal_client_with_the_declared_responses_in_order(): void
    {
        $this->mocker->mockAccessToken('ACCESS_TOKEN');
        $this->mocker->mockOrderDetails('PAYPAL_ORDER_ID', ['status' => 'COMPLETED']);

        self::assertSame('ACCESS_TOKEN', $this->payPalClient->authorize('CLIENT_ID', 'CLIENT_SECRET')['access_token']);
        self::assertSame(
            ['id' => 'PAYPAL_ORDER_ID', 'status' => 'COMPLETED'],
            $this->payPalClient->get('v2/checkout/orders/PAYPAL_ORDER_ID', 'ACCESS_TOKEN'),
        );
        self::assertFalse($this->httpClient->hasExpectations());
    }

    public function test_it_returns_the_declared_error_response(): void
    {
        $this->httpClient->addExpectation('GET', 'v2/checkout/orders/MISSING', ['name' => 'RESOURCE_NOT_FOUND', 'debug_id' => 'DEBUG_ID'], 404);

        self::assertSame(
            ['name' => 'RESOURCE_NOT_FOUND', 'debug_id' => 'DEBUG_ID'],
            $this->payPalClient->get('v2/checkout/orders/MISSING', 'ACCESS_TOKEN'),
        );
    }

    public function test_it_rejects_a_request_nobody_declared(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No expectations found for the PayPal test request "GET /v2/checkout/orders/PAYPAL_ORDER_ID".');

        $this->payPalClient->get('v2/checkout/orders/PAYPAL_ORDER_ID', 'ACCESS_TOKEN');
    }

    public function test_it_rejects_a_request_other_than_the_declared_one(): void
    {
        $this->mocker->mockOrderDetails('PAYPAL_ORDER_ID');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected the PayPal request "GET /v2/checkout/orders/PAYPAL_ORDER_ID" but got "GET /v2/checkout/orders/OTHER_ORDER_ID".');

        $this->payPalClient->get('v2/checkout/orders/OTHER_ORDER_ID', 'ACCESS_TOKEN');
    }
}
