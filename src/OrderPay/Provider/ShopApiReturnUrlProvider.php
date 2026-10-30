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

namespace Sylius\PayPalPlugin\OrderPay\Provider;

use Sylius\Bundle\ApiBundle\SectionResolver\ShopApiSection;
use Sylius\Bundle\CoreBundle\OrderPay\Provider\UrlProviderInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Component\Payment\Model\PaymentRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

final readonly class ShopApiReturnUrlProvider implements UrlProviderInterface
{
    public const HASH_PLACEHOLDER = '{hash}';

    public function __construct(
        private UrlProviderInterface $decoratedUrlProvider,
        private SectionProviderInterface $sectionProvider,
        private ?string $shopApiReturnUrl,
    ) {
        if (null !== $shopApiReturnUrl) {
            Assert::contains($shopApiReturnUrl, self::HASH_PLACEHOLDER);
        }
    }

    public function getUrl(
        PaymentRequestInterface $paymentRequest,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        if (null === $this->shopApiReturnUrl || !$this->sectionProvider->getSection() instanceof ShopApiSection) {
            return $this->decoratedUrlProvider->getUrl($paymentRequest, $referenceType);
        }

        return str_replace(self::HASH_PLACEHOLDER, (string) $paymentRequest->getHash(), $this->shopApiReturnUrl);
    }
}
