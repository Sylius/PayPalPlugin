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

namespace Sylius\PayPalPlugin\Form\Extension;

use Sylius\Bundle\PaymentBundle\Form\Type\GatewayConfigType;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface as PayumGatewayConfigInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class GatewayConfigTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $gatewayConfig = $event->getData();
            if (
                $gatewayConfig instanceof PayumGatewayConfigInterface &&
                null === $gatewayConfig->getId() &&
                SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $gatewayConfig->getFactoryName()
            ) {
                $gatewayConfig->setUsePayum(false);
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $gatewayConfig = $event->getData();
            if (
                !$gatewayConfig instanceof PayumGatewayConfigInterface ||
                SyliusPayPalExtension::PAYPAL_FACTORY_NAME !== $gatewayConfig->getFactoryName() ||
                !$gatewayConfig->getUsePayum()
            ) {
                return;
            }

            $gatewayConfig->setConfig(array_merge($gatewayConfig->getConfig(), [PayPalGatewayConfig::USE_AUTHORIZE => true]));
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [GatewayConfigType::class];
    }
}
