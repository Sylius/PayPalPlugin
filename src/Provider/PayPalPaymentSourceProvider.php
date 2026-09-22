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

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\PayPalPlugin\Exception\UnsupportedPayPalPaymentSourceException;
use Sylius\PayPalPlugin\Model\PayPalOrder;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;
use Webmozart\Assert\Assert;

final class PayPalPaymentSourceProvider implements PayPalPaymentSourceProviderInterface
{
    public function provide(OrderInterface $order, string $paymentSource, array $experienceContext): array
    {
        return match ($paymentSource) {
            self::PAYPAL => [self::PAYPAL => ['experience_context' => $experienceContext]],
            self::GOOGLE_PAY => [self::GOOGLE_PAY => [
                'attributes' => ['verification' => ['method' => PayPalOrder::VERIFICATION_METHOD_SCA_WHEN_REQUIRED]],
            ]],
            self::TRUSTLY => [self::TRUSTLY => $this->trustly($order, $experienceContext)],
            default => throw new UnsupportedPayPalPaymentSourceException($paymentSource),
        };
    }

    public function supports(string $paymentSource): bool
    {
        return in_array(
            $paymentSource,
            [self::PAYPAL, self::GOOGLE_PAY, ...RedirectPaymentSource::values()],
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
        Assert::notNull($billingAddress, 'The PayPal order needs a billing address to be paid with Trustly.');

        $countryCode = (string) $billingAddress->getCountryCode();
        Assert::regex($countryCode, '/^([A-Z]{2}|C2)$/');

        $fullName = trim((string) $billingAddress->getFullName());
        Assert::stringNotEmpty($fullName, 'The PayPal order needs the payer name to be paid with Trustly.');

        $email = (string) $order->getCustomer()?->getEmail();
        Assert::stringNotEmpty($email, 'The PayPal order needs the payer email to be paid with Trustly.');

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
}
