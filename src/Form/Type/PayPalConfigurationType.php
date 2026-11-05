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

use Sylius\PayPalPlugin\Checker\PaymentSourceCapabilityCheckerInterface;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class PayPalConfigurationType extends AbstractType
{
    private const HIDDEN_FIELDS = [
        PayPalGatewayConfig::MERCHANT_ID,
        PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID,
    ];

    private const CAPTURE = 'capture';

    private const AUTHORIZE = 'authorize';

    private const CAPABILITY_TOGGLES = [
        PayPalGatewayConfig::GOOGLE_PAY_ENABLED => PayPalPaymentSourceProviderInterface::GOOGLE_PAY,
        PayPalGatewayConfig::APPLE_PAY_ENABLED => PayPalPaymentSourceProviderInterface::APPLE_PAY,
    ];

    public function __construct(private readonly PaymentSourceCapabilityCheckerInterface $paymentSourceCapabilityChecker)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $originalData = [];
        $togglesWithoutCapability = [];

        $builder
            ->add(PayPalGatewayConfig::USE_AUTHORIZE, ChoiceType::class, [
                'label' => 'sylius_paypal.capture_strategy',
                'expanded' => false,
                'multiple' => false,
                'choices' => [
                    'sylius_paypal.capture_strategy_capture' => false,
                    'sylius_paypal.capture_strategy_authorize' => true,
                ],
                'choice_value' => static fn (?bool $authorize): string => true === $authorize ? self::AUTHORIZE : self::CAPTURE,
            ])
            ->add(PayPalGatewayConfig::CLIENT_ID, TextType::class, ['label' => 'sylius_paypal.client_id', 'disabled' => true])
            ->add(PayPalGatewayConfig::CLIENT_SECRET, TextType::class, ['label' => 'sylius_paypal.client_secret', 'disabled' => true])
            ->add(PayPalGatewayConfig::MERCHANT_ID, HiddenType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::PARTNER_ATTRIBUTION_ID, HiddenType::class, ['label' => 'sylius_paypal.partner_attribution_id', 'attr' => ['readonly' => true]])
            ->add(PayPalGatewayConfig::REPORTS_SFTP_USERNAME, TextType::class, ['label' => 'sylius_paypal.sftp_username', 'required' => false])
            ->add(PayPalGatewayConfig::REPORTS_SFTP_PASSWORD, TextType::class, ['label' => 'sylius_paypal.sftp_password', 'required' => false])
            ->add(PayPalGatewayConfig::EXPRESS_CHECKOUT_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.express_checkout_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::PAY_LATER_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.paylater_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::MESSAGING_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.messaging_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::VENMO_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.venmo_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::GOOGLE_PAY_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.google_pay_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::APPLE_PAY_ENABLED, CheckboxType::class, ['label' => 'sylius_paypal.apple_pay_enabled', 'required' => false])
            ->add(RedirectPaymentSource::Trustly->configurationKey(), CheckboxType::class, ['label' => 'sylius_paypal.trustly_enabled', 'required' => false])
            ->add(PayPalGatewayConfig::CARD_THREE_D_SECURE_ALWAYS, CheckboxType::class, ['label' => 'sylius_paypal.card_three_d_secure_always', 'required' => false])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$originalData, &$togglesWithoutCapability): void {
            $data = $event->getData();
            if (is_array($data)) {
                $originalData = $data;
                $data[PayPalGatewayConfig::EXPRESS_CHECKOUT_ENABLED] ??= true;
                $data[PayPalGatewayConfig::PAY_LATER_ENABLED] ??= true;
                $data[PayPalGatewayConfig::MESSAGING_ENABLED] ??= true;
                $data[PayPalGatewayConfig::VENMO_ENABLED] ??= false;
                $data[PayPalGatewayConfig::GOOGLE_PAY_ENABLED] ??= false;
                $data[PayPalGatewayConfig::APPLE_PAY_ENABLED] ??= false;
                $data[RedirectPaymentSource::Trustly->configurationKey()] ??= false;
                $data[PayPalGatewayConfig::CARD_THREE_D_SECURE_ALWAYS] ??= false;
                $config = PayPalGatewayConfig::fromArray($data);
                $data[PayPalGatewayConfig::USE_AUTHORIZE] = $config->isAuthorizeEnabled();
                $event->setData($data);

                $form = $event->getForm();
                $togglesWithoutCapability = [];
                foreach (self::CAPABILITY_TOGGLES as $field => $paymentSource) {
                    if (!$this->paymentSourceCapabilityChecker->isAvailable($paymentSource, $config)) {
                        $togglesWithoutCapability[] = $field;
                        $form->add($field, CheckboxType::class, [
                            'label' => $form->get($field)->getConfig()->getOption('label'),
                            'required' => false,
                            'disabled' => true,
                            'help' => true === $data[$field] ? 'sylius_paypal.capability_not_active_turned_off_on_save' : 'sylius_paypal.capability_not_active',
                        ]);
                    }
                }
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

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use (&$togglesWithoutCapability): void {
            $config = $event->getData();
            if (!is_array($config)) {
                return;
            }

            $config[PayPalGatewayConfig::USE_AUTHORIZE] = true === ($config[PayPalGatewayConfig::USE_AUTHORIZE] ?? false);
            foreach ($togglesWithoutCapability as $field) {
                $config[$field] = false;
            }

            $event->setData($config);
        });
    }
}
