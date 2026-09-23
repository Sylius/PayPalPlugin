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

namespace Sylius\PayPalPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\CreateOrderApiInterface;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Sylius\PayPalPlugin\Provider\NonceProvider;
use Sylius\PayPalPlugin\Provider\NonceProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProvider;
use Sylius\PayPalPlugin\Provider\PayPalOrderCreatedStatusesProviderInterface;
use Sylius\PayPalPlugin\Provider\PayPalPaymentSourceProviderInterface;
use Sylius\PayPalPlugin\Provider\UuidProviderInterface;

final readonly class CaptureAction implements ActionInterface
{
    public const PAYER_ACTION_LINK_REL = 'payer-action';

    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private CreateOrderApiInterface $createOrderApi,
        private UuidProviderInterface $uuidProvider,
        private ?PayPalOrderCreatedStatusesProviderInterface $orderCreatedStatusesProvider = null,
        private ?NonceProviderInterface $nonceProvider = null,
    ) {
        if (null === $this->orderCreatedStatusesProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $orderCreatedStatusesProvider to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }

        if (null === $this->nonceProvider) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '2.1',
                'Not passing $nonceProvider to "%s" constructor is deprecated and will be prohibited in 3.0',
                self::class,
            );
        }
    }

    /** @param Capture $request */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getModel();
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        $token = $this->authorizeClientApi->authorize($paymentMethod);

        $referenceId = $this->uuidProvider->provide();
        $paymentSource = $this->resolvePaymentSource($payment);
        $payerActionNonce = $this->generatePayerActionNonce($paymentSource);
        $content = $this->createOrderApi->create($token, $payment, $referenceId, $paymentSource, $payerActionNonce);

        if (in_array($content['status'] ?? null, $this->getOrderCreatedStatuses(), true)) {
            $payment->setDetails([
                'status' => StatusAction::STATUS_CAPTURED,
                'paypal_order_id' => $content['id'],
                'reference_id' => $referenceId,
                'payment_amount' => $payment->getAmount(),
                'payment_source' => $paymentSource,
            ] + $this->payerActionDetails($content, $payerActionNonce));
        }
    }

    private function generatePayerActionNonce(string $paymentSource): ?string
    {
        if (null === RedirectPaymentSource::tryFrom($paymentSource)) {
            return null;
        }

        $provider = $this->nonceProvider ?? new NonceProvider();

        return $provider->provide();
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return array{payer_action_url?: string, payer_action_nonce?: string}
     */
    private function payerActionDetails(array $content, ?string $payerActionNonce): array
    {
        if (null === $payerActionNonce) {
            return [];
        }

        /** @var array<array{rel?: string, href?: string}> $links */
        $links = $content['links'] ?? [];

        foreach ($links as $link) {
            if (self::PAYER_ACTION_LINK_REL === ($link['rel'] ?? null) && isset($link['href'])) {
                return [
                    'payer_action_url' => (string) $link['href'],
                    'payer_action_nonce' => $payerActionNonce,
                ];
            }
        }

        return [];
    }

    private function resolvePaymentSource(PaymentInterface $payment): string
    {
        $paymentSource = $payment->getDetails()['payment_source'] ?? null;

        return is_string($paymentSource) ? $paymentSource : PayPalPaymentSourceProviderInterface::PAYPAL;
    }

    /** @return array<int, string> */
    private function getOrderCreatedStatuses(): array
    {
        $provider = $this->orderCreatedStatusesProvider ?? new PayPalOrderCreatedStatusesProvider();

        return $provider->provide();
    }

    public function supports($request): bool
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
