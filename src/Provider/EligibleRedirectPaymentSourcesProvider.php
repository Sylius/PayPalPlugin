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

use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\PayPalPlugin\Api\CacheAuthorizeClientApiInterface;
use Sylius\PayPalPlugin\Api\FindEligibleMethodsApiInterface;
use Sylius\PayPalPlugin\Model\RedirectPaymentSource;

final readonly class EligibleRedirectPaymentSourcesProvider implements EligibleRedirectPaymentSourcesProviderInterface
{
    public function __construct(
        private CacheAuthorizeClientApiInterface $authorizeClientApi,
        private FindEligibleMethodsApiInterface $findEligibleMethodsApi,
        private PayPalFundingSourcesConfigurationProviderInterface $fundingSourcesConfigurationProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function provide(PaymentInterface $payment): array
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();
        /** @var ChannelInterface $channel */
        $channel = $order->getChannel();

        $enabled = $this->enabled($channel);
        if ([] === $enabled || null === $order->getBillingAddress()?->getCountryCode()) {
            return [];
        }

        return $this->eligible($payment, $enabled);
    }

    /**
     * @param list<RedirectPaymentSource> $enabled
     *
     * @return list<RedirectPaymentSource>
     */
    private function eligible(PaymentInterface $payment, array $enabled): array
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethod();

        try {
            $response = $this->findEligibleMethodsApi->find(
                $this->authorizeClientApi->authorize($paymentMethod),
                $payment,
                array_map(static fn (RedirectPaymentSource $case): string => $case->eligibilityCode(), $enabled),
            );
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf('Could not ask PayPal which methods the payer can use: %s', $exception->getMessage()));

            return [];
        }

        $eligibleMethods = $response['eligible_methods'] ?? null;
        if (!is_array($eligibleMethods)) {
            $this->logger->error('PayPal answered the eligibility request without any eligible methods.');

            return [];
        }

        return array_values(array_filter(
            $enabled,
            static fn (RedirectPaymentSource $case): bool => array_key_exists($case->value, $eligibleMethods),
        ));
    }

    /** @return list<RedirectPaymentSource> */
    private function enabled(ChannelInterface $channel): array
    {
        $enabled = [];

        foreach (RedirectPaymentSource::cases() as $case) {
            if ($this->isEnabled($case, $channel)) {
                $enabled[] = $case;
            }
        }

        return $enabled;
    }

    private function isEnabled(RedirectPaymentSource $paymentSource, ChannelInterface $channel): bool
    {
        return match ($paymentSource) {
            RedirectPaymentSource::Trustly => $this->fundingSourcesConfigurationProvider->isTrustlyEnabled($channel),
        };
    }
}
