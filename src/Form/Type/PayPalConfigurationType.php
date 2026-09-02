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

use Sylius\PayPalPlugin\Manager\PayPalCredentialsManagerInterface;
use Sylius\PayPalPlugin\Model\PayPalMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PayPalConfigurationType extends AbstractType
{
    private const HIDDEN_FIELDS = [
        'merchant_id',
        'sylius_merchant_id',
        'partner_attribution_id',
        'use_authorize',
    ];

    private const SANDBOX_MODE_FIELD = 'sandbox_mode';

    public function __construct(
        private readonly PayPalCredentialsManagerInterface $credentialsManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $originalData = [];

        $builder
            ->add(self::SANDBOX_MODE_FIELD, ChoiceType::class, [
                'label' => 'sylius_paypal.mode',
                'mapped' => false,
                'expanded' => false,
                'multiple' => false,
                'choices' => [
                    'sylius_paypal.mode_production' => false,
                    'sylius_paypal.mode_sandbox' => true,
                ],
                'choice_value' => static fn (?bool $mode): string => (true === $mode ? PayPalMode::Sandbox : PayPalMode::Production)->value,
            ])
            ->add('client_id', TextType::class, ['label' => 'sylius_paypal.client_id', 'attr' => ['readonly' => true]])
            ->add('client_secret', TextType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add('merchant_id', HiddenType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add('sylius_merchant_id', HiddenType::class, ['label' => 'sylius_paypal.client_secret', 'attr' => ['readonly' => true]])
            ->add('partner_attribution_id', HiddenType::class, ['label' => 'sylius_paypal.partner_attribution_id', 'attr' => ['readonly' => true]])
            // we need to force Sylius Payum integration to postpone creating an order, it's the easiest way
            ->add('use_authorize', HiddenType::class, ['data' => true, 'attr' => ['readonly' => true]])
            ->add('reports_sftp_username', TextType::class, ['label' => 'sylius_paypal.sftp_username', 'required' => false])
            ->add('reports_sftp_password', TextType::class, ['label' => 'sylius_paypal.sftp_password', 'required' => false])
        ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use (&$originalData): void {
            $data = $event->getData();
            if (is_array($data)) {
                $originalData = $data;
                $event->getForm()->get(self::SANDBOX_MODE_FIELD)->setData((bool) ($data[PayPalCredentialsManagerInterface::MODE_KEY] ?? false));
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

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $config = $event->getData();
            if (!is_array($config)) {
                return;
            }

            $event->setData($this->applyModeSwitch($event, $config));
        });
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function applyModeSwitch(FormEvent $event, array $config): array
    {
        $currentSandbox = (bool) ($config[PayPalCredentialsManagerInterface::MODE_KEY] ?? false);
        $requestedSandbox = (bool) $event->getForm()->get(self::SANDBOX_MODE_FIELD)->getData();
        $config = $this->credentialsManager->store($config, $currentSandbox, $config);

        if ($requestedSandbox === $currentSandbox) {
            return $config;
        }

        if (!$this->credentialsManager->hasCredentials($config, $requestedSandbox)) {
            $event->getForm()->get(self::SANDBOX_MODE_FIELD)->addError(
                new FormError($this->translator->trans('sylius_paypal.mode_not_configured')),
            );

            return $config;
        }

        return $this->credentialsManager->switchTo($config, $requestedSandbox);
    }
}
