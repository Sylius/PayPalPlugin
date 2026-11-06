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
use Symfony\Component\HttpFoundation\Response;

final class UpdatePermissionsTest extends JsonApiTestCase
{
    private PaymentMethodInterface $payPalPaymentMethod;

    private PaymentMethodInterface $cashOnDeliveryPaymentMethod;

    private AdminUserInterface $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $fixtures = $this->loadFixturesFromFiles([
            'resources/shop.yaml',
            'resources/shipping.yaml',
            'resources/authorized_paypal_order.yaml',
        ]);
        $this->payPalPaymentMethod = $fixtures['paypal'];
        $this->cashOnDeliveryPaymentMethod = $fixtures['cash_on_delivery'];
        $this->admin = $fixtures['admin'];
        self::getContainer()->get('sylius_paypal.cache')->clear();
        $this->client->loginUser($this->admin, 'admin');
    }

    public function test_it_offers_to_update_the_permissions_of_a_paypal_payment_method(): void
    {
        $crawler = $this->client->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->payPalPaymentMethod->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-update-paypal-permissions][data-bs-target="#confirmation-modal-update-paypal-permissions"]'));
        self::assertSame(
            sprintf('/admin/payment-methods/%d/paypal-permissions', (int) $this->payPalPaymentMethod->getId()),
            $crawler->filter('#confirmation-modal-update-paypal-permissions')->attr('data-paypal-onboarding-complete-url'),
        );
    }

    public function test_it_does_not_offer_to_update_the_paypal_permissions_of_another_payment_method(): void
    {
        $crawler = $this->client->request('GET', sprintf('/admin/payment-methods/%d/edit', (int) $this->cashOnDeliveryPaymentMethod->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#confirmation-modal-update-paypal-permissions'));
        self::assertCount(0, $crawler->filter('[data-test-update-paypal-permissions]'));
    }

    public function test_it_sends_the_admin_back_to_the_payment_method_when_the_onboarding_session_has_expired(): void
    {
        $configBefore = $this->payPalPaymentMethod->getGatewayConfig()?->getConfig();

        $this->updatePermissions($this->payPalPaymentMethod);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame(
            ['redirectUrl' => sprintf('/admin/payment-methods/%d/edit', (int) $this->payPalPaymentMethod->getId())],
            json_decode((string) $this->client->getResponse()->getContent(), true),
        );
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->clear();
        self::assertEquals($configBefore, $entityManager->find($this->payPalPaymentMethod::class, $this->payPalPaymentMethod->getId())->getGatewayConfig()->getConfig());
    }

    public function test_it_does_not_update_the_paypal_permissions_of_another_payment_method(): void
    {
        $this->updatePermissions($this->cashOnDeliveryPaymentMethod);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function updatePermissions(PaymentMethodInterface $paymentMethod): void
    {
        $this->client->request(
            'POST',
            sprintf('/admin/payment-methods/%d/paypal-permissions', (int) $paymentMethod->getId()),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode(['authCode' => 'AUTH_CODE', 'sharedId' => 'SHARED_ID']),
        );
    }
}
