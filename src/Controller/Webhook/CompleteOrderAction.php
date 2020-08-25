<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Sylius\PayPalPlugin\Provider\PayPalOrderProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompleteOrderAction
{
    /** @var PayPalOrderProviderInterface */
    private $orderProvider;

    public function __construct(
        PayPalOrderProviderInterface $orderProvider
    ) {
        $this->orderProvider = $orderProvider;
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->orderProvider->provide($request);

        return new JsonResponse(
            ['tet' => $order->getShippingAddress()->getCity()]
        );
    }
}
