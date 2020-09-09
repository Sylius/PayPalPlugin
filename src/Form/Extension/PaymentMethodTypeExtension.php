<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Form\Extension;

use Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodType;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class PaymentMethodTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event): void {
            /** @var PaymentMethodInterface $data */
            $data = $event->getData();
            $form = $event->getForm();

            if ($data->getGatewayConfig()->getFactoryName() === 'sylius.pay_pal') {
                $form->add('enabled', CheckboxType::class, [
                    'required' => false,
                    'disabled' => true,
                    'label' => 'sylius.form.payment_method.enabled',
                ]);
            }
        });
    }

    public function getExtendedTypes(): iterable
    {
        return [PaymentMethodType::class];
    }
}
