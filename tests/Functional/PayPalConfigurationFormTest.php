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

use ApiTestCase\JsonApiTestCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Tests\Sylius\PayPalPlugin\Service\FakeMerchantOnboardingStatusApi;

final class PayPalConfigurationFormTest extends JsonApiTestCase
{
    private PaymentMethodInterface $paymentMethod;

    private AdminUserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/authorized_paypal_order.yaml',
        ]);
        $this->paymentMethod = $fixtures['paypal'];
        $this->admin = $fixtures['admin'];
        self::getContainer()->get('sylius_paypal.cache')->clear();
    }

    protected function tearDown(): void
    {
        FakeMerchantOnboardingStatusApi::$activeCapabilities = FakeMerchantOnboardingStatusApi::ACTIVE_CAPABILITIES;
        self::getContainer()->get('sylius_paypal.cache')->clear();

        parent::tearDown();
    }

    public function test_it_disables_the_toggle_of_a_payment_source_the_paypal_account_has_no_capability_for(): void
    {
        FakeMerchantOnboardingStatusApi::$activeCapabilities = ['GOOGLE_PAY'];

        $crawler = $this->editPage();

        self::assertNotNull($crawler->filter('[data-test-apple-pay-enabled]')->attr('disabled'));
        self::assertNull($crawler->filter('[data-test-google-pay-enabled]')->attr('disabled'));
        self::assertStringContainsString('Your PayPal account does not have this payment method active yet.', $crawler->text());
    }

    public function test_it_leaves_the_toggles_enabled_when_the_paypal_account_has_the_capabilities(): void
    {
        $crawler = $this->editPage();

        self::assertNull($crawler->filter('[data-test-apple-pay-enabled]')->attr('disabled'));
        self::assertNull($crawler->filter('[data-test-google-pay-enabled]')->attr('disabled'));
    }

    private function editPage(): Crawler
    {
        $this->client->loginUser($this->admin, 'admin');
        $crawler = $this->client->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId()));
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }
}
