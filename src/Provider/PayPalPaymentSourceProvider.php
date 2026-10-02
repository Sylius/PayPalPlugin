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

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\DependencyInjection\SyliusPayPalExtension;
use Sylius\PayPalPlugin\Exception\InvalidPayerDataException;
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;
use Sylius\PayPalPlugin\Model\PayPalGatewayConfig;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final class PayPalPaymentSourceProvider implements PayPalPaymentSourceProviderInterface
{
    public function provide(PaymentInterface $payment, string $paymentSource, array $experienceContext): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        return match ($paymentSource) {
            self::PAYPAL => [self::PAYPAL => ['experience_context' => $experienceContext]],
            self::GOOGLE_PAY => [self::GOOGLE_PAY => [
                'attributes' => ['verification' => ['method' => PayPalOrder::VERIFICATION_METHOD_SCA_WHEN_REQUIRED]],
            ]],
            self::APPLE_PAY => [],
            self::TRUSTLY => [self::TRUSTLY => $this->trustly($order, $experienceContext)],
            self::CARD => [self::CARD => [
                'attributes' => ['verification' => ['method' => $this->cardVerificationMethod($payment)]],
                'experience_context' => array_filter([
                    'return_url' => $experienceContext['return_url'] ?? null,
                    'cancel_url' => $experienceContext['cancel_url'] ?? null,
                ]),
            ]],
            self::VENMO => [self::VENMO => [
                'experience_context' => array_filter([
                    PayPalOrder::KEY_SHIPPING_PREFERENCE => $experienceContext[PayPalOrder::KEY_SHIPPING_PREFERENCE] ?? null,
                    'user_action' => $experienceContext['user_action'] ?? null,
                    'order_update_callback_config' => $experienceContext['order_update_callback_config'] ?? null,
                ]),
            ]],
            default => throw new UnsupportedPayPalPaymentSourceException($paymentSource),
        };
    }

    public function supports(string $paymentSource): bool
    {
        return in_array(
            $paymentSource,
            [self::PAYPAL, self::GOOGLE_PAY, self::APPLE_PAY, self::CARD, self::VENMO, ...RedirectPaymentSource::values()],
            true,
        );
    }

    /**
     * @param array<string, mixed> $experienceContext
     *
     * @return array<string, mixed>
     */
    private function trustly(OrderInterface $order, array $experienceContext): array
    {
        $billingAddress = $order->getBillingAddress();
        if (null === $billingAddress) {
            throw InvalidPayerDataException::withoutBillingAddress(self::TRUSTLY);
        }

        $countryCode = (string) $billingAddress->getCountryCode();
        if (1 !== preg_match('/^([A-Z]{2}|C2)$/', $countryCode)) {
            throw InvalidPayerDataException::withCountryCode(self::TRUSTLY, $countryCode);
        }

        $fullName = trim((string) $billingAddress->getFullName());
        if ('' === $fullName) {
            throw InvalidPayerDataException::withoutPayerName(self::TRUSTLY);
        }

        $email = (string) $order->getCustomer()?->getEmail();
        if ('' === $email) {
            throw InvalidPayerDataException::withoutPayerEmail(self::TRUSTLY);
        }

        return [
            'name' => $fullName,
            'country_code' => $countryCode,
            'email' => $email,
            'experience_context' => $this->baseExperienceContext($experienceContext),
        ];
    }

    /**
     * @param array<string, mixed> $experienceContext
     *
     * @return array<string, mixed>
     */
    private function baseExperienceContext(array $experienceContext): array
    {
        return array_intersect_key($experienceContext, array_flip(self::BASE_EXPERIENCE_CONTEXT_KEYS));
    }

    private function cardVerificationMethod(PaymentInterface $payment): string
    {
        $paymentMethod = $payment->getMethod();
        $gatewayConfig = $paymentMethod instanceof PaymentMethodInterface ? $paymentMethod->getGatewayConfig() : null;

        if (
            $gatewayConfig instanceof GatewayConfigInterface &&
            SyliusPayPalExtension::PAYPAL_FACTORY_NAME === $gatewayConfig->getFactoryName() &&
            PayPalGatewayConfig::fromGatewayConfig($gatewayConfig)->isCardThreeDSecureAlways()
        ) {
            return PayPalOrder::VERIFICATION_METHOD_SCA_ALWAYS;
        }

        return PayPalOrder::VERIFICATION_METHOD_SCA_WHEN_REQUIRED;
    }
}
