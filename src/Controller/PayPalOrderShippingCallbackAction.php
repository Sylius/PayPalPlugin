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

use Sylius\Component\Core\Factory\AddressFactoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Provider\AvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PayPal's v6 Web SDK "shortcut" flow (cart/product page buttons, which have no shipping address yet
 * at create-order time) calls this endpoint mid-flow, inside the wallet popup, whenever the buyer picks
 * or changes their shipping address - server-to-server, no browser session. It must answer with the
 * shipping options available for that address, or a 422 telling PayPal which part of the address is
 * the problem. This is deliberately a pure computation: unlike UpdatePayPalOrderAction (the older,
 * v1-SDK-era equivalent), it never persists anything onto the real order - the buyer hasn't approved
 * anything yet, and may never complete the payment at all.
 */
final readonly class PayPalOrderShippingCallbackAction
{
    private const ISSUE_ADDRESS_ERROR = 'ADDRESS_ERROR';

    private const ISSUE_COUNTRY_ERROR = 'COUNTRY_ERROR';

    /** @param AddressFactoryInterface<AddressInterface> $addressFactory */
    public function __construct(
        private PaypalPaymentQueryInterface $paypalPaymentQuery,
        private AddressFactoryInterface $addressFactory,
        private AvailableCountriesProviderInterface $availableCountriesProvider,
        private ShippingMethodsResolverInterface $shippingMethodsResolver,
        private DelegatingCalculatorInterface $shippingCalculator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($request->getContent(), true);

        $payPalOrderId = (string) ($payload['id'] ?? '');
        /** @var array<string, mixed> $shippingAddress */
        $shippingAddress = (array) ($payload['shipping_address'] ?? []);
        /** @var array<int, array<string, mixed>> $purchaseUnits */
        $purchaseUnits = (array) ($payload['purchase_units'] ?? []);

        $countryCode = (string) ($shippingAddress['country_code'] ?? '');
        if (!\in_array($countryCode, $this->availableCountriesProvider->provide(), true)) {
            return $this->unprocessable(self::ISSUE_COUNTRY_ERROR);
        }

        try {
            $payment = $this->paypalPaymentQuery->getForUpdateByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        if (null === $payment) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $shipment = $order->getShipments()->first();
        if (false === $shipment) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        $shippingOptions = $this->buildShippingOptions($order, $shipment, $shippingAddress, $countryCode);
        if ([] === $shippingOptions) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        $purchaseUnit = $purchaseUnits[0] ?? [];
        $purchaseUnit['shipping_options'] = $shippingOptions;

        return new JsonResponse([
            'id' => $payPalOrderId,
            'purchase_units' => [$purchaseUnit],
        ]);
    }

    /**
     * @param array<string, mixed> $shippingAddressPayload
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildShippingOptions(
        OrderInterface $order,
        ShipmentInterface $shipment,
        array $shippingAddressPayload,
        string $countryCode,
    ): array {
        /** @var AddressInterface $transientAddress */
        $transientAddress = $this->addressFactory->createNew();
        $transientAddress->setCity((string) ($shippingAddressPayload['admin_area_2'] ?? ''));
        $transientAddress->setProvinceCode(isset($shippingAddressPayload['admin_area_1']) ? (string) $shippingAddressPayload['admin_area_1'] : null);
        $transientAddress->setPostcode((string) ($shippingAddressPayload['postal_code'] ?? ''));
        $transientAddress->setCountryCode($countryCode);

        // Zone-based shipping method resolution reads the order's shipping address, so it has to be
        // set for getSupportedMethods() to resolve correctly - but this is never flushed, and both the
        // address and the shipment's method are restored below before this request ends.
        $originalShippingAddress = $order->getShippingAddress();
        $originalMethod = $shipment->getMethod();
        $order->setShippingAddress($transientAddress);

        try {
            $supportedMethods = $this->shippingMethodsResolver->getSupportedMethods($shipment);

            $shippingOptions = [];
            foreach ($supportedMethods as $method) {
                $shipment->setMethod($method);
                $cost = $this->shippingCalculator->calculate($shipment);

                $shippingOptions[] = [
                    'id' => (string) $method->getCode(),
                    'amount' => [
                        'currency_code' => (string) $order->getCurrencyCode(),
                        'value' => number_format($cost / 100, 2, '.', ''),
                    ],
                    'type' => 'SHIPPING',
                    'label' => (string) $method->getName(),
                    'selected' => $method === $originalMethod,
                ];
            }

            if ([] !== $shippingOptions && !\in_array(true, array_column($shippingOptions, 'selected'), true)) {
                $shippingOptions[0]['selected'] = true;
            }

            return $shippingOptions;
        } finally {
            $shipment->setMethod($originalMethod);
            $order->setShippingAddress($originalShippingAddress);
        }
    }

    private function unprocessable(string $issue): JsonResponse
    {
        return new JsonResponse(
            ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => $issue]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
