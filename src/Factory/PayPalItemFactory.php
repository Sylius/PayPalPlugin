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

namespace Sylius\PayPalPlugin\Factory;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface as CoreProductInterface;
use Sylius\Component\Product\Model\ProductInterface;
use Sylius\PayPalPlugin\Model\PayPalBillingPlan;
use Sylius\PayPalPlugin\Model\PayPalItem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PayPalItemFactory implements PayPalItemFactoryInterface
{
    private const LOCALHOST = 'localhost';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ?RequestStack $requestStack = null,
    ) {
        if (null === $this->requestStack) {
            trigger_deprecation(
                'sylius/paypal-plugin',
                '3.1',
                'Not passing an instance of %s to %s constructor is deprecated and will be required in 4.0.',
                RequestStack::class,
                self::class,
            );
        }
    }

    public function create(
        OrderItemInterface $orderItem,
        int $quantity,
        int $unitPrice,
        int $tax,
        string $currencyCode,
        string $category,
        ?PayPalBillingPlan $billingPlan = null,
    ): PayPalItem {
        $variant = $orderItem->getVariant();
        $product = $variant?->getProduct();

        return new PayPalItem(
            $this->truncateProductName($orderItem->getProductName()),
            $quantity,
            $unitPrice,
            $tax,
            $currencyCode,
            $category,
            $variant?->getCode(),
            $this->resolveDescription($product),
            $this->resolveProductUrl($product, $this->order($orderItem)),
            $billingPlan,
        );
    }

    private function resolveDescription(?ProductInterface $product): ?string
    {
        if (!$product instanceof CoreProductInterface) {
            return null;
        }

        $shortDescription = $product->getShortDescription();
        if (null === $shortDescription || '' === $shortDescription) {
            return null;
        }

        return mb_strlen($shortDescription) > 127
            ? mb_substr($shortDescription, 0, 124) . '...'
            : $shortDescription;
    }

    private function resolveProductUrl(?ProductInterface $product, ?OrderInterface $order): ?string
    {
        $slug = $product?->getSlug();
        if (null === $slug || '' === $slug) {
            return null;
        }

        if (!$this->isOutsideOfRequest()) {
            return $this->generateProductUrl(['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $parameters = ['slug' => $slug];
        $localeCode = $order?->getLocaleCode();
        if (null !== $localeCode && '' !== $localeCode) {
            $parameters['_locale'] = $localeCode;
        }

        $channelBaseUrl = $this->channelBaseUrl($order?->getChannel());
        if (null === $channelBaseUrl) {
            return $this->generateProductUrl($parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $path = $this->generateProductUrl($parameters, UrlGeneratorInterface::ABSOLUTE_PATH);

        return null === $path ? null : $channelBaseUrl . $path;
    }

    /** @param array<string, string> $parameters */
    private function generateProductUrl(array $parameters, int $referenceType): ?string
    {
        try {
            return $this->urlGenerator->generate('sylius_shop_product_show', $parameters, $referenceType);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isOutsideOfRequest(): bool
    {
        return null !== $this->requestStack && null === $this->requestStack->getMainRequest();
    }

    private function order(OrderItemInterface $orderItem): ?OrderInterface
    {
        $order = $orderItem->getOrder();

        return $order instanceof OrderInterface ? $order : null;
    }

    private function channelBaseUrl(?ChannelInterface $channel): ?string
    {
        $hostname = $channel?->getHostname();
        if (null === $hostname || '' === $hostname || self::LOCALHOST === strtolower($hostname)) {
            return null;
        }

        return sprintf('https://%s', $hostname);
    }

    private function truncateProductName(string $productName): string
    {
        return mb_strlen($productName) > 127
            ? mb_substr($productName, 0, 124) . '...'
            : $productName;
    }
}
