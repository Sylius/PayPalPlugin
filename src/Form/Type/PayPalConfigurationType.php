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
            ->add('api_key', TextType::class)
            ->add('merchant_id_in_paypal', TextType::class)
            ->add('permissions_granted', CheckboxType::class)
            ->add('account_status', TextType::class)
            ->add('consent_status', CheckboxType::class)
            ->add('product_intent_id', TextType::class)
            ->add('email_confirmed', CheckboxType::class)
            ->add('return_message', TextareaType::class)
        ;
    }
}
