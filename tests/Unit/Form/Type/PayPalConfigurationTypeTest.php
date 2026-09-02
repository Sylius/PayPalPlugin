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
        // Both vaults are kept so switching back needs no re-onboarding.
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
