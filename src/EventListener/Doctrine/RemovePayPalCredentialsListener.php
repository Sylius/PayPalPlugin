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

namespace Sylius\PayPalPlugin\EventListener\Doctrine;

use Doctrine\ORM\Event\PreRemoveEventArgs;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Entity\PayPalCredentials;

final readonly class RemovePayPalCredentialsListener
{
    public function preRemove(PreRemoveEventArgs $event): void
    {
        $paymentMethod = $event->getObject();
        if (
            !$paymentMethod instanceof PaymentMethodInterface ||
            $paymentMethod->getGatewayConfig()?->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME
        ) {
            return;
        }

        $entityManager = $event->getObjectManager();
        foreach ($entityManager->getRepository(PayPalCredentials::class)->findBy(['paymentMethod' => $paymentMethod]) as $payPalCredentials) {
            $entityManager->remove($payPalCredentials);
        }
    }
}
