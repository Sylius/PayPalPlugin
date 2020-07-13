<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;

class OrderProvider implements OrderProviderInterface
{
    /** @var OrderRepositoryInterface */
    private $orderRepository;

    public function __construct(OrderRepositoryInterface $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    public function provideOrderById(int $id): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->find($id);

        return $order;
    }

    public function provideOrderByToken(string $token): OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->orderRepository->findOneByTokenValue($token);

        return $order;
    }
}
