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
use Sylius\PayPalPlugin\Model\Capability;
use Sylius\PayPalPlugin\Model\OnboardingScope;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class PermissionsVerificationTest extends JsonApiTestCase
{
    use InteractsWithLiveComponents;
    use MocksPayPalApiTrait;

    private const MERCHANT_INTEGRATION_PATH = 'v1/customer/partners/PTV9W69CVKNEL/merchant-integrations/MERCHANT_ID';

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

    public function test_it_stores_the_current_scope_without_a_consent_when_every_capability_is_active(): void
    {
        $this->payPalHttpClient()->addExpectation('GET', self::MERCHANT_INTEGRATION_PATH, $this->merchantIntegration([
            Capability::APPLE_PAY => 'ACTIVE',
            Capability::GOOGLE_PAY => 'ACTIVE',
        ]));

        $rendered = (string) $this->permissionsModal()->call('loadOnboardingUrl')->render();

        self::assertStringContainsString('data-test-paypal-permissions-verification="up_to_date"', $rendered);
        self::assertStringNotContainsString('data-paypal-button', $rendered);
        $this->getEntityManager()->refresh($this->paymentMethod);
        self::assertEquals(OnboardingScope::current(), $this->onboardingScope());
    }

    public function test_it_tells_which_capability_paypal_has_not_activated_yet_without_a_consent(): void
    {
        $this->payPalHttpClient()->addExpectation('GET', self::MERCHANT_INTEGRATION_PATH, $this->merchantIntegration([
            Capability::APPLE_PAY => 'ACTIVE',
            Capability::GOOGLE_PAY => 'NEED_DATA',
        ]));

        $rendered = (string) $this->permissionsModal()->call('loadOnboardingUrl')->render();

        self::assertStringContainsString('data-test-paypal-permissions-verification="awaiting_paypal"', $rendered);
        self::assertStringContainsString('GOOGLE_PAY: NEED_DATA', $rendered);
        self::assertStringNotContainsString('data-paypal-button', $rendered);
        self::assertNull($this->onboardingScope());
    }

    public function test_it_offers_the_consent_to_an_account_the_configured_partner_does_not_know(): void
    {
        $this->payPalHttpClient()->addExpectation('GET', self::MERCHANT_INTEGRATION_PATH, [
            'name' => 'RESOURCE_NOT_FOUND',
            'message' => 'The specified resource does not exist.',
            'debug_id' => 'DEBUG_ID',
        ], 404);

        $rendered = (string) $this->permissionsModal()->call('loadOnboardingUrl')->render();

        self::assertStringNotContainsString('data-test-paypal-permissions-verification', $rendered);
        self::assertStringContainsString('data-paypal-button', $rendered);
    }

    public function test_it_still_offers_the_consent_when_paypal_cannot_tell_the_permissions(): void
    {
        $this->payPalHttpClient()->addExpectation('GET', self::MERCHANT_INTEGRATION_PATH, [
            'name' => 'NOT_AUTHORIZED',
            'message' => 'Authorization failed due to insufficient permissions.',
            'debug_id' => 'DEBUG_ID',
        ], 403);

        $rendered = (string) $this->permissionsModal()->call('loadOnboardingUrl')->render();

        self::assertStringContainsString('data-test-paypal-permissions-verification="failed"', $rendered);
        self::assertStringContainsString('data-paypal-button', $rendered);
    }

    private function permissionsModal(): TestLiveComponent
    {
        $this->client->getContainer()->get('router')->getContext()->setParameter('_locale', 'en_US');

        return $this->createLiveComponent(
            'sylius_paypal:live_onboarding_modal',
            [
                'modalId' => 'confirmation-modal-update-paypal-permissions',
                'type' => 'update-paypal-permissions',
                'sandbox' => false,
                'paymentMethodId' => $this->paymentMethod->getId(),
            ],
            $this->client,
        )->actingAs($this->admin, 'admin');
    }

    /**
     * @param array<string, string> $capabilityStatuses
     *
     * @return array<string, mixed>
     */
    private function merchantIntegration(array $capabilityStatuses): array
    {
        $capabilities = [];
        foreach ($capabilityStatuses as $name => $status) {
            $capabilities[] = ['name' => $name, 'status' => $status];
        }

        return ['merchant_id' => 'MERCHANT_ID', 'payments_receivable' => true, 'primary_email_confirmed' => true, 'capabilities' => $capabilities];
    }

    private function onboardingScope(): ?OnboardingScope
    {
        $gatewayConfig = $this->paymentMethod->getGatewayConfig();
        self::assertNotNull($gatewayConfig);
        $scope = $gatewayConfig->getConfig()['onboarding_scope'] ?? null;

        return is_array($scope) ? OnboardingScope::fromArray($scope) : null;
    }
}
