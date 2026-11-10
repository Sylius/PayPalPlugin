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
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Sylius\PayPalPlugin\Twig\Component\PermissionsStateComponent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class PermissionsStateTest extends JsonApiTestCase
{
    use InteractsWithLiveComponents;
    use MocksPayPalApiTrait;

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

    public function test_it_flags_a_paypal_payment_method_without_a_stored_scope_on_the_list_and_the_edit_page(): void
    {
        self::assertStringContainsString('data-test-paypal-permissions-state="unknown"', $this->page('/admin/payment-methods/'));
        self::assertStringContainsString(
            'data-test-paypal-permissions-state="unknown"',
            $this->page(sprintf('/admin/payment-methods/%d/edit', (int) $this->paymentMethod->getId())),
        );
    }

    public function test_it_flags_a_stored_scope_that_lacks_a_capability_the_onboarding_asks_for(): void
    {
        $current = OnboardingScope::current();
        $this->storeScope(new OnboardingScope($current->product(), $current->secondaryProducts(), ['APPLE_PAY'], $current->features()));

        $rendered = (string) $this->permissionsState(PermissionsStateComponent::VARIANT_ALERT)->render();

        self::assertStringContainsString('data-test-paypal-permissions-state="out_of_date"', $rendered);
        self::assertStringContainsString('GOOGLE_PAY', $rendered);
    }

    public function test_it_shows_nothing_for_a_stored_scope_that_is_up_to_date(): void
    {
        $this->storeScope(OnboardingScope::current());

        self::assertStringNotContainsString('paypal-permissions-state', (string) $this->permissionsState(PermissionsStateComponent::VARIANT_BADGE)->render());
        self::assertStringNotContainsString('paypal-permissions-state', (string) $this->permissionsState(PermissionsStateComponent::VARIANT_ALERT)->render());
    }

    private function page(string $uri): string
    {
        $this->client->loginUser($this->admin, 'admin');
        $this->client->request('GET', $uri);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        return (string) $this->client->getResponse()->getContent();
    }

    private function permissionsState(string $variant): TestLiveComponent
    {
        $this->client->getContainer()->get('router')->getContext()->setParameter('_locale', 'en_US');

        return $this->createLiveComponent(
            'sylius_paypal:permissions_state',
            ['paymentMethodId' => $this->paymentMethod->getId(), 'variant' => $variant],
            $this->client,
        )->actingAs($this->admin, 'admin');
    }

    private function storeScope(OnboardingScope $scope): void
    {
        $gatewayConfig = $this->paymentMethod->getGatewayConfig();
        self::assertNotNull($gatewayConfig);
        $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), ['onboarding_scope' => $scope->toArray()]));
        $this->getEntityManager()->flush();
    }
}
