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

namespace Tests\Sylius\PayPalPlugin\Unit\Form\Type;

use PHPUnit\Framework\Attributes\Test;
use Sylius\PayPalPlugin\Form\Type\PayPalConfigurationType;
use Sylius\PayPalPlugin\Manager\PayPalCredentialsManager;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PayPalConfigurationTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return [new PreloadedExtension([new PayPalConfigurationType(new PayPalCredentialsManager(), $translator)], [])];
    }

    #[Test]
    public function the_funding_source_toggles_default_to_checked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertTrue($form->get('pay_later_enabled')->getData());
        self::assertTrue($form->get('messaging_enabled')->getData());
    }

    public function test_the_google_pay_toggle_defaults_to_unchecked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertFalse($form->get('google_pay_enabled')->getData());
    }

    public function test_the_card_three_d_secure_always_toggle_defaults_to_unchecked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertFalse($form->get('card_three_d_secure_always')->getData());
    }

    public function test_the_card_three_d_secure_always_toggle_stays_true_after_being_resubmitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), ['card_three_d_secure_always' => '1']));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['card_three_d_secure_always']);
    }

    public function test_the_venmo_toggle_defaults_to_unchecked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertFalse($form->get('venmo_enabled')->getData());
    }

    public function test_the_google_pay_toggle_stays_true_after_being_resubmitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), ['google_pay_enabled' => '1']));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['google_pay_enabled']);
    }

    public function test_the_apple_pay_toggle_defaults_to_unchecked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertFalse($form->get('apple_pay_enabled')->getData());
    }

    public function test_the_apple_pay_toggle_stays_true_after_being_resubmitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), ['apple_pay_enabled' => '1']));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['apple_pay_enabled']);
    }

    public function test_the_trustly_toggle_defaults_to_unchecked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertFalse($form->get('trustly_enabled')->getData());
    }

    public function test_the_trustly_toggle_stays_true_after_being_resubmitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), ['trustly_enabled' => '1']));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['trustly_enabled']);
    }

    #[Test]
    public function an_unchecked_toggle_is_correctly_submitted_as_false(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit($this->submittedFields());

        self::assertTrue($form->isValid());
        self::assertFalse($form->getData()['pay_later_enabled']);
        self::assertFalse($form->getData()['venmo_enabled']);
        self::assertFalse($form->getData()['messaging_enabled']);
        self::assertFalse($form->getData()['google_pay_enabled']);
        self::assertFalse($form->getData()['apple_pay_enabled']);
        self::assertFalse($form->getData()['trustly_enabled']);
        self::assertFalse($form->getData()['card_three_d_secure_always']);
    }

    #[Test]
    public function a_checked_toggle_stays_true_after_being_resubmitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), [
            'pay_later_enabled' => '1',
            'venmo_enabled' => '1',
            'messaging_enabled' => '1',
        ]));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['pay_later_enabled']);
        self::assertTrue($form->getData()['venmo_enabled']);
        self::assertTrue($form->getData()['messaging_enabled']);
    }

    /** @return array<string, mixed> */
    private function baseConfig(): array
    {
        return [
            'client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET', 'merchant_id' => 'MERCHANT_ID',
            'sylius_merchant_id' => 'MERCHANT_ID', 'partner_attribution_id' => 'bn', 'use_authorize' => 1,
            'reports_sftp_username' => null, 'reports_sftp_password' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function submittedFields(): array
    {
        return [
            'client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET',
            'reports_sftp_username' => '', 'reports_sftp_password' => '',
        ];
    }

    #[Test]
    public function it_preselects_the_sandbox_option_when_the_config_is_in_sandbox_mode(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->sandboxConfig());

        self::assertTrue($form->get('sandbox_mode')->getData());
    }

    #[Test]
    public function it_preselects_the_production_option_when_the_config_is_in_production_mode(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->productionConfig());

        self::assertFalse($form->get('sandbox_mode')->getData());
    }

    #[Test]
    public function it_switches_to_sandbox_loading_the_stored_vault_when_both_modes_are_configured(): void
    {
        $config = $this->productionConfig();
        $config['sandbox_credentials'] = [
            'client_id' => 'SB', 'client_secret' => 'SBS', 'merchant_id' => 'SBM',
            'sylius_merchant_id' => 'SYLIUS_SANDBOX_MERCHANT_ID', 'partner_attribution_id' => 'bn',
        ];

        $form = $this->factory->create(PayPalConfigurationType::class, $config);
        $form->submit($this->submittedProductionFields(['sandbox_mode' => 'sandbox']));

        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertTrue($data['sandbox']);
        self::assertSame('SB', $data['client_id']);
        self::assertSame('SBM', $data['merchant_id']);
        self::assertSame('PROD', $data['production_credentials']['client_id']);
        self::assertSame('SB', $data['sandbox_credentials']['client_id']);
    }

    #[Test]
    public function it_blocks_switching_to_a_mode_that_has_no_stored_credentials(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->productionConfig());
        $form->submit($this->submittedProductionFields(['sandbox_mode' => 'sandbox']));

        self::assertFalse($form->isValid());
        self::assertFalse($form->getData()['sandbox']);
        self::assertSame('PROD', $form->getData()['client_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function productionConfig(): array
    {
        return [
            'client_id' => 'PROD', 'client_secret' => 'PRODS', 'merchant_id' => 'PRODM',
            'sylius_merchant_id' => 'PRODM', 'partner_attribution_id' => 'bn', 'use_authorize' => 1,
            'reports_sftp_username' => null, 'reports_sftp_password' => null, 'webhook_id' => 'WH', 'sandbox' => false,
            'production_credentials' => [
                'client_id' => 'PROD', 'client_secret' => 'PRODS', 'merchant_id' => 'PRODM',
                'sylius_merchant_id' => 'PRODM', 'partner_attribution_id' => 'bn', 'webhook_id' => 'WH',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sandboxConfig(): array
    {
        return [
            'client_id' => 'SB', 'client_secret' => 'SBS', 'merchant_id' => 'SBM',
            'sylius_merchant_id' => 'SYLIUS_SANDBOX_MERCHANT_ID', 'partner_attribution_id' => 'bn', 'use_authorize' => 1,
            'reports_sftp_username' => null, 'reports_sftp_password' => null, 'sandbox' => true,
            'sandbox_credentials' => [
                'client_id' => 'SB', 'client_secret' => 'SBS', 'merchant_id' => 'SBM',
                'sylius_merchant_id' => 'SYLIUS_SANDBOX_MERCHANT_ID', 'partner_attribution_id' => 'bn',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function submittedProductionFields(array $overrides): array
    {
        return array_merge([
            'client_id' => 'PROD', 'client_secret' => 'PRODS', 'merchant_id' => 'PRODM',
            'sylius_merchant_id' => 'PRODM', 'partner_attribution_id' => 'bn', 'use_authorize' => '1',
            'reports_sftp_username' => '', 'reports_sftp_password' => '',
        ], $overrides);
    }
}
