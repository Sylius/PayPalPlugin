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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class PayPalConfigurationType extends AbstractType
{
    private const HIDDEN_FIELDS = [
        'merchant_id',
        'sylius_merchant_id',
        'partner_attribution_id',
        'use_authorize',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $originalData = [];

        $builder
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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use (&$originalData): void {
            $data = $event->getData();
            if (is_array($data)) {
                $originalData = $data;
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
