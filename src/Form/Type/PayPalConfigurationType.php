<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class PayPalConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client_id', TextType::class, ['label' => 'sylius.pay_pal.client_id', 'attr' => ['readonly' => true]])
            ->add('client_secret', TextType::class, ['label' => 'sylius.pay_pal.client_secret', 'attr' => ['readonly' => true]])
            ->add('merchant_id', HiddenType::class, ['label' => 'sylius.pay_pal.client_secret', 'attr' => ['readonly' => true]])
            ->add('sylius_merchant_id', HiddenType::class, ['label' => 'sylius.pay_pal.client_secret', 'attr' => ['readonly' => true]])
            ->add('partner_attribution_id', HiddenType::class, ['label' => 'sylius.pay_pal.partner_attribution_id', 'attr' => ['readonly' => true]])
            // we need to force Sylius Payum integration to postpone creating an order, it's the easiest way
            ->add('use_authorize', HiddenType::class, ['data' => true, 'attr' => ['readonly' => true]])
            ->add('reports_sftp_username', TextType::class, ['label' => 'sylius.pay_pal.sftp_username', 'required' => false])
            ->add('reports_sftp_password', TextType::class, ['label' => 'sylius.pay_pal.sftp_password', 'required' => false])
        ;
    }
}
