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

namespace Sylius\PayPalPlugin\Checker;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\PaymentMethodRepositoryInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;

final class PayPalPaymentMethodChecker implements PayPalPaymentMethodCheckerInterface
{
    /** @param PaymentMethodRepositoryInterface<PaymentMethodInterface>&EntityRepository $paymentMethodRepository */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface&EntityRepository $paymentMethodRepository,
    ) {
    }

    public function hasPayPalPaymentMethod(): bool
    {
        $count = (int) $this->paymentMethodRepository->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->innerJoin('o.gatewayConfig', 'gatewayConfig')
            ->andWhere('gatewayConfig.factoryName = :factoryName')
            ->setParameter('factoryName', SyliusPayPalExtension::PAYPAL_FACTORY_NAME)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
