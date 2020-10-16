<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Form\Extension;

use Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodType;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class PaymentMethodTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            /** @var PaymentMethodInterface $data */
            $data = $event->getData();
            $form = $event->getForm();

            /** @var GatewayConfigInterface $gatewayConfig */
            $gatewayConfig = $data->getGatewayConfig();
            if ($gatewayConfig->getFactoryName() === 'sylius.pay_pal') {
                $form->add('enabled', HiddenType::class, [
                    'required' => false,
                    'label' => 'sylius.form.payment_method.enabled',
                    'data' => $data->isEnabled(),
                ]);
            }
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [PaymentMethodType::class];
    }
}
