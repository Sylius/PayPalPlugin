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

namespace Sylius\PayPalPlugin\Creator;

use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Model\PayPalPaymentDetails;
use Sylius\PayPalPlugin\Model\PayPalPaymentStatus;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final readonly class PayPalOrderCreator implements PayPalOrderCreatorInterface
{
    public const PAYER_ACTION_LINK_REL = 'payer-action';

    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private CreateOrderApiInterface $createOrderApi,
        private UuidProviderInterface $uuidProvider,
        private PayPalOrderCreatedStatusesProviderInterface $orderCreatedStatusesProvider,
    ) {
    }

    public function create(
        PaymentInterface $payment,
        string $paymentSource,
        ?string $customId = null,
        ?string $requestId = null,
        ?string $returnUrl = null,
        ?string $cancelUrl = null,
    ): ?PayPalPaymentDetails {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $referenceId = $this->uuidProvider->provide();
        $content = $this->createOrderApi->create(
            $token,
            $payment,
            $referenceId,
            $paymentSource,
            $customId,
            $requestId,
            $returnUrl,
            $cancelUrl,
        );

        if (!in_array($content['status'] ?? null, $this->orderCreatedStatusesProvider->provide(), true)) {
            return null;
        }

        $details = PayPalPaymentDetails::create()
            ->withStatus(PayPalPaymentStatus::Captured)
            ->withPayPalOrderId((string) $content['id'])
            ->withReferenceId($referenceId)
            ->withAmount((int) $payment->getAmount())
            ->withPaymentSource($paymentSource)
        ;
        $details = $this->withPayerAction($details, $content, $paymentSource);

        $payment->setDetails($details->toArray());

        return $details;
    }

    /** @param array<string, mixed> $content */
    private function withPayerAction(PayPalPaymentDetails $details, array $content, string $paymentSource): PayPalPaymentDetails
    {
        $payerActionUrl = null !== RedirectPaymentSource::tryFrom($paymentSource) ? $this->payerActionUrl($content) : null;
        if (null !== $payerActionUrl) {
            $details = $details->withPayerAction($payerActionUrl);
        }

        return $details;
    }

    /** @param array<string, mixed> $content */
    private function payerActionUrl(array $content): ?string
    {
        /** @var array<array{rel?: string, href?: string}> $links */
        $links = $content['links'] ?? [];

        foreach ($links as $link) {
            if (self::PAYER_ACTION_LINK_REL === ($link['rel'] ?? null) && isset($link['href'])) {
                return (string) $link['href'];
            }
        }

        return null;
    }
}
