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

namespace Sylius\PayPalPlugin\PackageTracking\EventListener;

use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\PayPalPlugin\PackageTracking\Form\Extension\ShipmentShipTypeExtension;
use Sylius\PayPalPlugin\Provider\FlashBagProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class InvalidShipFormListener
{
    private const ORDER_SHIPMENT_SHIP_ROUTE = 'sylius_admin_order_shipment_ship';

    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $messages = $request->attributes->get(ShipmentShipTypeExtension::ERRORS_REQUEST_ATTRIBUTE);
        if (!is_array($messages) || [] === $messages) {
            return;
        }

        $flashBag = FlashBagProvider::getFlashBag($this->requestStack);
        foreach ($messages as $message) {
            $flashBag->add('error', (string) $message);
        }

        $event->setResponse(new RedirectResponse(
            self::ORDER_SHIPMENT_SHIP_ROUTE === $request->attributes->get('_route')
                ? $this->urlGenerator->generate('sylius_admin_order_show', ['id' => $request->attributes->get('orderId')])
                : $this->urlGenerator->generate('sylius_admin_shipment_index'),
        ));
    }
}
