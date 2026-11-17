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

use Sylius\PayPalPlugin\Model\PayPalSandboxCredentials;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0. */
final class PayPalSandboxCredentialsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('clientId', TextType::class, [
                'label' => 'sylius_paypal.client_id',
            ])
            ->add('clientSecret', TextType::class, [
                'label' => 'sylius_paypal.client_secret',
            ])
            ->add('merchantId', TextType::class, [
                'label' => 'sylius_paypal.merchant_id',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PayPalSandboxCredentials::class,
            'csrf_protection' => true,
            'validation_groups' => 'sylius',
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'sylius_paypal_sandbox_credentials';
    }
}
