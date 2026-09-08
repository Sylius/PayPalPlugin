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
use Sylius\PayPalPlugin\Provider\ChannelAvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Repository\Query\PaypalPaymentQueryInterface;
use Sylius\PayPalPlugin\Resolver\PayPalShippingAddressResolverInterface;
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
        private PayPalShippingAddressResolverInterface $shippingAddressResolver,
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
            $this->shippingAddressResolver->resolve($payPalShippingAddress),
        );

        if ([] === $shippingOptions) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        /** @var array<string, mixed> $purchaseUnit */
        $purchaseUnit = (array) ($purchaseUnits[0] ?? []);
        $purchaseUnit['shipping_options'] = $shippingOptions;

        return new JsonResponse([
            'id' => $payPalOrderId,
            'purchase_units' => [$purchaseUnit],
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

    private function unprocessable(string $issue): JsonResponse
    {
        return new JsonResponse(
            ['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => $issue]]],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
