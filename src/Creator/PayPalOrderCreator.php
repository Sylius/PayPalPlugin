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
use Sylius\PayPalPlugin\Provider\NonceProviderInterface;
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
        private NonceProviderInterface $nonceProvider,
    ) {
    }

    public function create(
        PaymentInterface $payment,
        string $paymentSource,
        ?string $customId = null,
        ?string $requestId = null,
    ): ?PayPalPaymentDetails {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $referenceId = $this->uuidProvider->provide();
        $payerActionNonces = $this->generatePayerActionNonces($paymentSource);
        $content = $this->createOrderApi->create(
            $token,
            $payment,
            $referenceId,
            $paymentSource,
            $payerActionNonces['payer_action_return_nonce'] ?? null,
            $payerActionNonces['payer_action_cancel_nonce'] ?? null,
            $customId,
            $requestId,
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
        $details = $this->withPayerAction($details, $content, $payerActionNonces);

        $payment->setDetails($details->toArray());

        return $details;
    }

    /** @return array{payer_action_return_nonce?: string, payer_action_cancel_nonce?: string} */
    private function generatePayerActionNonces(string $paymentSource): array
    {
        if (null === RedirectPaymentSource::tryFrom($paymentSource)) {
            return [];
        }

        return [
            'payer_action_return_nonce' => $this->nonceProvider->provide(),
            'payer_action_cancel_nonce' => $this->nonceProvider->provide(),
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @param array{payer_action_return_nonce?: string, payer_action_cancel_nonce?: string} $payerActionNonces
     */
    private function withPayerAction(PayPalPaymentDetails $details, array $content, array $payerActionNonces): PayPalPaymentDetails
    {
        if (!isset($payerActionNonces['payer_action_return_nonce'], $payerActionNonces['payer_action_cancel_nonce'])) {
            return $details;
        }

        $payerActionUrl = $this->payerActionUrl($content);
        if (null === $payerActionUrl) {
            return $details;
        }

        return $details->withPayerAction(
            $payerActionUrl,
            $payerActionNonces['payer_action_return_nonce'],
            $payerActionNonces['payer_action_cancel_nonce'],
        );
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
