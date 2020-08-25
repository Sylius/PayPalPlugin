<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Controller\Webhook;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class CompleteOrderAction
{
    /** @var PayPalOrderProviderInterface */
    private $orderProvider;

    /** @var ObjectManager */
    private $objectManager;

    public function __construct(
        PayPalOrderProviderInterface $orderProvider,
        ObjectManager $objectManager
    ) {
        $this->orderProvider = $orderProvider;
        $this->objectManager = $objectManager;
    }

    public function __invoke(Request $request): Response
    {
        $order = $this->orderProvider->provide($request);

        $payPalAddress = $this->getAddressFromRequest($request);

        /** @var AddressInterface $orderAddress */
        $orderAddress = $order->getShippingAddress();

        $orderAddress->setCity($payPalAddress['city']);
        $orderAddress->setStreet($payPalAddress['street']);
        $orderAddress->setPostcode($payPalAddress['post_code']);
        $orderAddress->setCountryCode($payPalAddress['country_code']);

        $this->objectManager->flush();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function getAddressFromRequest(Request $request): array
    {
        $content = (array) json_decode((string) $request->getContent(false), true);
        $resource = $content['resource'];

        Assert::keyExists($resource, 'purchase_units');
        $purchaseUnits = $resource['purchase_units'][0];

        Assert::keyExists($purchaseUnits, 'shipping');
        $shipping = $purchaseUnits['shipping'];

        Assert::keyExists($shipping, 'address');
        $address = $shipping['address'];

        return [
            'city' => (string) $address['admin_area_2'],
            'street' => (string) $address['address_line_1'] . (string) $address['address_line_2'],
            'post_code' => (string) $address['postal_code'],
            'country_code' => (string) $address['country_code'],
        ];
    }
}
