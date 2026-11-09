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

final class PaymentMethodModeTest extends JsonApiTestCase
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
    }

    public function test_it_flags_a_paypal_payment_method_without_a_mode(): void
    {
        $editPage = $this->page(sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId()));
        self::assertSame('unknown', $editPage->filter('#paypal-mode')->attr('data-test-paypal-mode'));
        self::assertCount(1, $editPage->filter('[data-test-paypal-mode-unknown]'));

        $listPage = $this->page('/admin/payment-methods/');
        self::assertCount(1, $listPage->filter('[data-test-paypal-mode="unknown"]'));
    }

    public function test_it_shows_the_mode_a_paypal_payment_method_has(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $gatewayConfig = $entityManager->find($this->paymentMethod::class, $this->paymentMethod->getId())->getGatewayConfig();
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['sandbox' => false]));
        $entityManager->flush();

        $editPage = $this->page(sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId()));
        self::assertSame('production', $editPage->filter('#paypal-mode')->attr('data-test-paypal-mode'));
        self::assertCount(0, $editPage->filter('[data-test-paypal-mode-unknown]'));

        $listPage = $this->page('/admin/payment-methods/');
        self::assertCount(1, $listPage->filter('[data-test-paypal-mode="production"]'));
    }

    private function page(string $uri): Crawler
    {
        $this->client->loginUser($this->admin, 'admin');
        $crawler = $this->client->request('GET', $uri);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        return $crawler;
    }
}
