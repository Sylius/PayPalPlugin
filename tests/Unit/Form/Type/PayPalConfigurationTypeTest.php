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
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class PayPalConfigurationTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [new PreloadedExtension([new PayPalConfigurationType()], [])];
    }

    #[Test]
    public function the_funding_source_toggles_default_to_checked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertTrue($form->get('pay_later_enabled')->getData());
        self::assertTrue($form->get('messaging_enabled')->getData());
    }

    public function test_the_express_checkout_toggle_defaults_to_checked_for_a_new_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        self::assertTrue($form->get('express_checkout_enabled')->getData());
    }

    public function test_the_express_checkout_toggle_stays_false_for_a_payment_method_it_was_turned_off_for(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, array_merge($this->baseConfig(), ['express_checkout_enabled' => false]));

        self::assertFalse($form->get('express_checkout_enabled')->getData());
    }

    public function test_the_capture_strategy_defaults_to_immediate_capture(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, array_diff_key($this->baseConfig(), ['use_authorize' => true]));

        self::assertFalse($form->get('use_authorize')->getData());
    }

    public function test_the_capture_strategy_reads_an_authorizing_payment_method(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, array_merge($this->baseConfig(), ['use_authorize' => true]));

        self::assertTrue($form->get('use_authorize')->getData());
    }

    public function test_the_capture_strategy_stores_authorize_and_capture(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), ['use_authorize' => 'authorize']));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['use_authorize']);
    }

    public function test_the_capture_strategy_stores_immediate_capture(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, array_merge($this->baseConfig(), ['use_authorize' => true]));

        $form->submit(array_merge($this->submittedFields(), ['use_authorize' => 'capture']));

        self::assertTrue($form->isValid());
        self::assertFalse($form->getData()['use_authorize']);
    }

    public function test_the_capture_strategy_stays_immediate_capture_when_none_is_submitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit($this->submittedFields());

        self::assertFalse($form->getData()['use_authorize']);
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
        self::assertFalse($form->getData()['express_checkout_enabled']);
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
            'express_checkout_enabled' => '1',
            'pay_later_enabled' => '1',
            'venmo_enabled' => '1',
            'messaging_enabled' => '1',
        ]));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['express_checkout_enabled']);
        self::assertTrue($form->getData()['pay_later_enabled']);
        self::assertTrue($form->getData()['venmo_enabled']);
        self::assertTrue($form->getData()['messaging_enabled']);
    }

    /** @return array<string, mixed> */
    private function baseConfig(): array
    {
        return [
            'client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET', 'merchant_id' => 'MERCHANT_ID',
            'partner_attribution_id' => 'bn', 'use_authorize' => 1,
        ];
    }

    /** @return array<string, mixed> */
    private function submittedFields(): array
    {
        return ['client_id' => 'CLIENT_ID', 'client_secret' => 'CLIENT_SECRET'];
    }

    #[Test]
    public function it_has_no_field_to_switch_the_mode(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->sandboxConfig());

        self::assertFalse($form->has('sandbox_mode'));
    }

    #[Test]
    public function it_keeps_the_mode_the_credentials_and_the_onboarding_scope_of_the_payment_method_on_submit(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->sandboxConfig());
        $form->submit(array_merge($this->submittedFields(), ['client_id' => 'OTHER', 'client_secret' => 'OTHERS']));

        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertTrue($data['sandbox']);
        self::assertSame('WH', $data['webhook_id']);
        self::assertSame('SB', $data['client_id']);
        self::assertSame('SBS', $data['client_secret']);
        self::assertSame('SBM', $data['merchant_id']);
        self::assertSame(['product' => 'ppcp'], $data['onboarding_scope']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sandboxConfig(): array
    {
        return [
            'client_id' => 'SB', 'client_secret' => 'SBS', 'merchant_id' => 'SBM',
            'partner_attribution_id' => 'bn', 'use_authorize' => 1,
            'webhook_id' => 'WH', 'sandbox' => true, 'onboarding_scope' => ['product' => 'ppcp'],
        ];
    }
}
