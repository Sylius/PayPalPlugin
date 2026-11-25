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

namespace Sylius\PayPalPlugin\EventListener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Remover\CachedAccessTokenRemoverInterface;
use Webmozart\Assert\Assert;

final readonly class RemoveCachedAccessTokenListener
{
    public function __construct(private CachedAccessTokenRemoverInterface $cachedAccessTokenRemover)
    {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $paymentMethod = $event->getSubject();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        if ($paymentMethod->getGatewayConfig()?->getFactoryName() !== SyliusPayPalExtension::PAYPAL_FACTORY_NAME) {
            return;
        }

        $this->cachedAccessTokenRemover->remove($paymentMethod);
    }
}
