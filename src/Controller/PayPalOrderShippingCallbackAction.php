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

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface;
use Sylius\PayPalPlugin\Provider\ChannelAvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Resolver\PayPalShippingOptionsResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PayPalOrderShippingCallbackAction
{
    private const ISSUE_ADDRESS_ERROR = 'ADDRESS_ERROR';

    private const ISSUE_COUNTRY_ERROR = 'COUNTRY_ERROR';

    public function __construct(
        private PaypalPaymentQueryInterface $paypalPaymentQuery,
        private ChannelAvailableCountriesProviderInterface $availableCountriesProvider,
        private PayPalShippingAddressFactoryInterface $shippingAddressFactory,
        private PayPalShippingOptionsResolverInterface $shippingOptionsResolver,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        $payPalOrderId = (string) ($payload['id'] ?? '');
        /** @var array<string, mixed> $payPalShippingAddress */
        $payPalShippingAddress = (array) ($payload['shipping_address'] ?? []);
        /** @var array<int, mixed> $purchaseUnits */
        $purchaseUnits = (array) ($payload['purchase_units'] ?? []);

        $order = $this->getOrder($payPalOrderId);
        if (null === $order) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        /** @var ChannelInterface|null $channel */
        $channel = $order->getChannel();
        if (null === $channel) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        $countryCode = (string) ($payPalShippingAddress['country_code'] ?? '');
        if (!in_array($countryCode, $this->availableCountriesProvider->provideForChannel($channel), true)) {
            return $this->unprocessable(self::ISSUE_COUNTRY_ERROR);
        }

        $shippingOptions = $this->shippingOptionsResolver->resolve(
            $order,
            $this->shippingAddressFactory->create($payPalShippingAddress),
        );

        if ([] === $shippingOptions) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        /** @var array<string, mixed> $purchaseUnit */
        $purchaseUnit = (array) ($purchaseUnits[0] ?? []);

        return new JsonResponse([
            'id' => $payPalOrderId,
            'purchase_units' => [$this->buildPurchaseUnit($purchaseUnit, $shippingOptions)],
        ]);
    }

    private function getOrder(string $payPalOrderId): ?OrderInterface
    {
        try {
            $payment = $this->paypalPaymentQuery->getForUpdateByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return null;
        }

        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();

        return $order;
    }

    /**
     * @param array<string, mixed> $purchaseUnit
     * @param array<int, array<string, mixed>> $shippingOptions
     *
     * @return array<string, mixed>
     */
    private function buildPurchaseUnit(array $purchaseUnit, array $shippingOptions): array
    {
        $responseUnit = [];

        if (isset($purchaseUnit['reference_id'])) {
            $responseUnit['reference_id'] = $purchaseUnit['reference_id'];
        }

        $responseUnit['amount'] = $this->withSelectedShippingCost(
            (array) ($purchaseUnit['amount'] ?? []),
            $shippingOptions,
        );
        $responseUnit['shipping_options'] = $shippingOptions;

        return $responseUnit;
    }

    /**
     * @param array<int, array<string, mixed>> $shippingOptions
     * @param array<string, mixed> $amount
     *
     * @return array<string, mixed>
     */
    private function withSelectedShippingCost(array $amount, array $shippingOptions): array
    {
        /** @var array<string, mixed>|null $selected */
        $selected = array_values(array_filter($shippingOptions, fn (array $option): bool => true === $option['selected']))[0] ?? null;

        /** @var array<string, array<string, mixed>> $breakdown */
        $breakdown = (array) ($amount['breakdown'] ?? []);
        if (null === $selected || [] === $breakdown) {
            return $amount;
        }

        $breakdown['shipping'] = (array) $selected['amount'];

        $total =
            $this->minorUnits($breakdown, 'item_total') +
            $this->minorUnits($breakdown, 'tax_total') +
            $this->minorUnits($breakdown, 'shipping') +
            $this->minorUnits($breakdown, 'handling') +
            $this->minorUnits($breakdown, 'insurance') -
            $this->minorUnits($breakdown, 'discount') -
            $this->minorUnits($breakdown, 'shipping_discount')
        ;

        $amount['breakdown'] = $breakdown;
        $amount['value'] = number_format($total / 100, 2, '.', '');

        return $amount;
    }

    /** @param array<string, array<string, mixed>> $breakdown */
    private function minorUnits(array $breakdown, string $key): int
    {
        return (int) round(((float) ($breakdown[$key]['value'] ?? 0)) * 100);
    }

    private function unprocessable(string $issue): JsonResponse
    {
        return new JsonResponse(
            ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => $issue]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
