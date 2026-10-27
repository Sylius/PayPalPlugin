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

namespace Sylius\PayPalPlugin\Form\Type;

use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class PayPalConfigurationType extends AbstractType
{
    private const HIDDEN_FIELDS = [
        PayPalGatewayConfig::MERCHANT_ID,
        PayPalGatewayConfig::SYLIUS_MERCHANT_ID,
        PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID,
        PayPalGatewayConfig::USE_AUTHORIZE,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $originalData = [];

        $builder
            ->add(PayPalGatewayConfig::CLIENT_ID, TextType::class, ['label' => 'sylius_paypal.client_id', 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::CLIENT_SECRET, TextType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::MERCHANT_ID, HiddenType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::SYLIUS_MERCHANT_ID, HiddenType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID, HiddenType::class, ['label' => 'sylius_paypal.partner_attribution_id', 'attr' => ['readonly' => true]])
            // we need to force Sylius Payum integration to postpone creating an order, it's the easiest way
            ->add(PayPalGatewayConfig::USE_AUTHORIZE, HiddenType::class, ['data' => true, 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::REPORTS_SFTP_USERNAME, TextType::class, ['label' => 'sylius_paypal.sftp_username', 'required' => false])
            ->add(PayPalGatewayConfig::REPORTS_SFTP_PASSWORD, TextType::class, ['label' => 'sylius_paypal.sftp_password', 'required' => false])
            ->add(PayPalGatewayConfig::PAY_LATER_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.paylater_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::MESSAGING_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.messaging_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::VENMO_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.venmo_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::GOOGLE_PAY_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.google_pay_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::APPLE_PAY_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.apple_pay_enabled', 'required' => false])
            ->add(RedirectPaymentSource::Trustly->configurationKey(), CheckboxType::class, ['label' => 'sylius_paypal.trustly_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::CARD_THREE_D_SECURE_ALWAYS, CheckboxType::class, ['label' => 'sylius_paypal.card_three_d_secure_always', 'required' => false])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$originalData): void {
            $data = $event->getData();
            if (is_array($data)) {
                $originalData = $data;
                $data[PayPalGatewayConfig::PAY_LATER_ENABLED] ??= true;
                $data[PayPalGatewayConfig::MESSAGING_ENABLED] ??= true;
                $data[PayPalGatewayConfig::VENMO_ENABLED] ??= false;
                $data[PayPalGatewayConfig::GOOGLE_PAY_ENABLED] ??= false;
                $data[PayPalGatewayConfig::APPLE_PAY_ENABLED] ??= false;
                $data[RedirectPaymentSource::Trustly->configurationKey()] ??= false;
                $data[PayPalGatewayConfig::CARD_THREE_D_SECURE_ALWAYS] ??= false;
                $event->setData($data);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use (&$originalData): void {
            $submitted = $event->getData() ?? [];

            foreach (self::HIDDEN_FIELDS as $field) {
                if (
                    !array_key_exists($field, $submitted) &&
                    array_key_exists($field, $originalData)
                ) {
                    $submitted[$field] = $originalData[$field];
                }
            }

            $event->setData($submitted);
        });
    }
}
