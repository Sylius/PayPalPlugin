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

        self::assertTrue($form->get('paylater_enabled')->getData());
        self::assertTrue($form->get('venmo_enabled')->getData());
        self::assertTrue($form->get('messaging_enabled')->getData());
    }

    #[Test]
    public function an_unchecked_toggle_is_correctly_submitted_as_false(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        // an unchecked checkbox is simply absent from the submitted payload - not a bug to
        // special-case, this is how HTML forms work. clearMissing must stay true (the default,
        // matching a real POST submission) for that omission to actually register as "false"
        // rather than "unchanged".
        $form->submit($this->submittedFields());

        self::assertTrue($form->isValid());
        self::assertFalse($form->getData()['paylater_enabled']);
        self::assertFalse($form->getData()['venmo_enabled']);
        self::assertFalse($form->getData()['messaging_enabled']);
    }

    #[Test]
    public function a_checked_toggle_stays_true_after_being_resubmitted(): void
    {
        $form = $this->factory->create(PayPalConfigurationType::class, $this->baseConfig());

        $form->submit(array_merge($this->submittedFields(), [
            'paylater_enabled' => '1',
            'venmo_enabled' => '1',
            'messaging_enabled' => '1',
        ]));

        self::assertTrue($form->isValid());
        self::assertTrue($form->getData()['paylater_enabled']);
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
}
