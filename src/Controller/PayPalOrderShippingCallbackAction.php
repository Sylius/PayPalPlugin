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
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\PayPalPlugin\Api\PayPalCallbackSignatureVerifierInterface;
use Sylius\PayPalPlugin\Exception\PaymentNotFoundException;
use Sylius\PayPalPlugin\Factory\PayPalShippingAddressFactoryInterface;
use Sylius\PayPalPlugin\Factory\PayPalShippingCallbackResponseFactoryInterface;
use Sylius\PayPalPlugin\Provider\ChannelAvailableCountriesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalShippingCallbackAmountProviderInterface;
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
        private PayPalCallbackSignatureVerifierInterface $signatureVerifier,
        private PaypalPaymentQueryInterface $paypalPaymentQuery,
        private ChannelAvailableCountriesProviderInterface $availableCountriesProvider,
        private PayPalShippingAddressFactoryInterface $shippingAddressFactory,
        private PayPalShippingOptionsResolverInterface $shippingOptionsResolver,
        private PayPalShippingCallbackResponseFactoryInterface $responseFactory,
        private PayPalShippingCallbackAmountProviderInterface $amountProvider,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->signatureVerifier->verify($request)) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        $payPalOrderId = (string) ($payload['id'] ?? '');
        /** @var array<string, mixed> $payPalShippingAddress */
        $payPalShippingAddress = (array) ($payload['shipping_address'] ?? []);
        /** @var array<int, mixed> $purchaseUnits */
        $purchaseUnits = (array) ($payload['purchase_units'] ?? []);

        $payment = $this->getPayment($payPalOrderId);
        /** @var OrderInterface|null $order */
        $order = $payment?->getOrder();
        if (null === $payment || null === $order) {
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

        $shippingAddress = $this->shippingAddressFactory->create($payPalShippingAddress);
        $shippingOptions = $this->shippingOptionsResolver->resolve($order, $shippingAddress);

        /** @var array<string, mixed> $payPalShippingOption */
        $payPalShippingOption = (array) ($payload['shipping_option'] ?? []);
        $payPalShippingOptionId = (string) ($payPalShippingOption['id'] ?? '');
        if ('' !== $payPalShippingOptionId) {
            $shippingOptions = $shippingOptions->withSelected($payPalShippingOptionId);
        }

        $selectedOption = $shippingOptions->selected();
        if (null === $selectedOption) {
            return $this->unprocessable(self::ISSUE_ADDRESS_ERROR);
        }

        /** @var array<string, mixed> $purchaseUnit */
        $purchaseUnit = (array) ($purchaseUnits[0] ?? []);

        return new JsonResponse($this->responseFactory->create(
            $payPalOrderId,
            $purchaseUnit,
            $this->amountProvider->provide($payment, $shippingAddress, $selectedOption),
            $shippingOptions,
        ));
    }

    private function getPayment(string $payPalOrderId): ?PaymentInterface
    {
        try {
            return $this->paypalPaymentQuery->getForUpdateByOrderId($payPalOrderId);
        } catch (PaymentNotFoundException) {
            return null;
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
