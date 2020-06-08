<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PayPalConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account_status', TextType::class, ['label' => 'sylius.pay_pal.account_status'])
            ->add('api_key', TextType::class, ['label' => 'sylius.pay_pal.api_key'])
            ->add('consent_status', CheckboxType::class, ['label' => 'sylius.pay_pal.consent_status'])
            ->add('email_confirmed', CheckboxType::class, ['label' => 'sylius.pay_pal.email_confirmed'])
            ->add('merchant_id_in_paypal', TextType::class, ['label' => 'sylius.pay_pal.merchant_id_in_paypal'])
            ->add('permissions_granted', CheckboxType::class, ['label' => 'sylius.pay_pal.permissions_granted'])
            ->add('product_intent_id', TextType::class, ['label' => 'sylius.pay_pal.product_intent_id'])
            ->add('return_message', TextareaType::class, ['label' => 'sylius.pay_pal.return_message'])
        ;
    }
}
