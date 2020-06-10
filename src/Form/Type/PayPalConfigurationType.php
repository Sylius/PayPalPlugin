<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PayPalConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client_id', TextType::class, ['label' => 'sylius.pay_pal.client_id', 'disabled' => true])
            ->add('client_secret', TextType::class, ['label' => 'sylius.pay_pal.client_secret', 'disabled' => true])
        ;
    }
}
