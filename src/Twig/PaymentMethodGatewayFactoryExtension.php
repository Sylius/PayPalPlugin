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

namespace Sylius\PayPalPlugin\Twig;

use Doctrine\ORM\EntityRepository;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Repository\PaymentMethodRepositoryInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** @deprecated since Sylius/PayPalPlugin 2.2 and will be removed in Sylius/PayPalPlugin 3.0, which requires Sylius 2.2.9. */
final class PaymentMethodGatewayFactoryExtension extends AbstractExtension
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface>&EntityRepository<PaymentMethodInterface> $paymentMethodRepository */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface&EntityRepository $paymentMethodRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_has_payment_method_with_gateway_factory', [$this, 'hasPaymentMethodWithGatewayFactory']),
        ];
    }

    public function hasPaymentMethodWithGatewayFactory(string $factoryName): bool
    {
        trigger_deprecation(
            'sylius/paypal-plugin',
            '2.2',
            'Using the plugin with Sylius below 2.2.9 is deprecated, 3.0 requires Sylius 2.2.9.',
        );

        $count = $this->paymentMethodRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('factoryName', $factoryName)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) $count > 0;
    }
}
