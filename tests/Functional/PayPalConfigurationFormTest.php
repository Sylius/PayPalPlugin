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
use Tests\Sylius\PayPalPlugin\Service\FakeMerchantIntegrationStatusProvider;

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
        FakeMerchantIntegrationStatusProvider::$activeCapabilities = FakeMerchantIntegrationStatusProvider::ACTIVE_CAPABILITIES;
        self::getContainer()->get('sylius_paypal.cache')->clear();

        parent::tearDown();
    }

    public function test_it_disables_the_toggle_of_a_payment_source_the_paypal_account_has_no_capability_for(): void
    {
        FakeMerchantIntegrationStatusProvider::$activeCapabilities = ['GOOGLE_PAY'];

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

    public function test_it_turns_off_on_save_a_toggle_of_a_payment_source_the_paypal_account_has_no_capability_for(): void
    {
        FakeMerchantIntegrationStatusProvider::$activeCapabilities = ['GOOGLE_PAY'];
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $gatewayConfig = $entityManager->find($this->paymentMethod::class, $this->paymentMethod->getId())->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['apple_pay_enabled' => true, 'google_pay_enabled' => true]));
        $entityManager->flush();

        $crawler = $this->editPage();
        self::assertStringContainsString('It will be turned off when you save.', $crawler->text());

        $this->client->submit($crawler->filter('form[name="sylius_admin_payment_method"]')->form());
        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());

        $entityManager->clear();
        $config = $entityManager->find($this->paymentMethod::class, $this->paymentMethod->getId())->getGatewayConfig()->getConfig();
        self::assertFalse($config['apple_pay_enabled']);
        self::assertTrue($config['google_pay_enabled']);
    }

    private function editPage(): Crawler
    {
        $this->client->loginUser($this->admin, 'admin');
        $crawler = $this->client->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId()));
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }
}
