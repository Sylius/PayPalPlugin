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

namespace Sylius\PayPalPlugin\Controller;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Sylius\PayPalPlugin\Provider\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final readonly class CancelPayPalOrderAction
{
    /** @param OrderRepositoryInterface<OrderInterface>|null $orderRepository */
    public function __construct(
        private ?PaymentProviderInterface $paymentProvider,
        private ?OrderRepositoryInterface $orderRepository,
        private RequestStack $flashBagOrRequestStack,
    ) {
        if (null !== $this->paymentProvider) {
            trigger_deprecation('sylius/paypal-plugin', '1.7', sprintf('Passing an instance of %s as the first argument is deprecated and will be prohibited in 3.0', PaymentProviderInterface::class));
        }
        if (null !== $this->orderRepository) {
            trigger_deprecation('sylius/paypal-plugin', '1.7', sprintf('Passing an instance of %s as the second argument is deprecated and will be prohibited in 3.0', OrderRepositoryInterface::class));
        }
    }

    public function __invoke(Request $request): Response
    {
        FlashBagProvider::getFlashBag($this->flashBagOrRequestStack)->add('success', 'sylius_paypal.order_cancelled');

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
