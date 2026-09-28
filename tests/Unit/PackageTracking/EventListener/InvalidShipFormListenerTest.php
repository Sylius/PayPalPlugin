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

namespace Tests\Sylius\PayPalPlugin\Unit\PackageTracking\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\Shipment;
use Sylius\PayPalPlugin\PackageTracking\EventListener\InvalidShipFormListener;
use Sylius\PayPalPlugin\PackageTracking\Form\Extension\ShipmentShipTypeExtension;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InvalidShipFormListenerTest extends TestCase
{
    private RequestStack $requestStack;

    private InvalidShipFormListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => $route . ([] === $parameters ? '' : '?' . http_build_query($parameters)),
        );

        $this->requestStack = new RequestStack();
        $this->listener = new InvalidShipFormListener($this->requestStack, $urlGenerator);
    }

    #[Test]
    public function it_sends_the_admin_back_to_the_order_with_the_reasons_when_shipping_from_the_order_page(): void
    {
        $request = $this->request('sylius_admin_order_shipment_ship', ['Please select a carrier.']);
        $request->attributes->set('orderId', '7');
        $event = new ResourceControllerEvent(new Shipment());

        ($this->listener)($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('sylius_admin_order_show?id=7', $response->getTargetUrl());
        self::assertSame(['Please select a carrier.'], $request->getSession()->getFlashBag()->get('error'));
    }

    #[Test]
    public function it_sends_the_admin_back_to_the_shipment_list_when_shipping_from_there(): void
    {
        $this->request('sylius_admin_shipment_ship', ['Please select a carrier.']);
        $event = new ResourceControllerEvent(new Shipment());

        ($this->listener)($event);

        self::assertSame('sylius_admin_shipment_index', $event->getResponse()?->getTargetUrl());
    }

    #[Test]
    public function it_leaves_the_request_alone_when_the_ship_form_has_no_tracking_errors(): void
    {
        $request = $this->request('sylius_admin_shipment_ship', null);
        $event = new ResourceControllerEvent(new Shipment());

        ($this->listener)($event);

        self::assertNull($event->getResponse());
        self::assertSame([], $request->getSession()->getFlashBag()->all());
    }

    /** @param list<string>|null $errors */
    private function request(string $route, ?array $errors): Request
    {
        $request = Request::create('/admin/ship', 'PUT');
        $request->attributes->set('_route', $route);
        if (null !== $errors) {
            $request->attributes->set(ShipmentShipTypeExtension::ERRORS_REQUEST_ATTRIBUTE, $errors);
        }
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);

        return $request;
    }
}
