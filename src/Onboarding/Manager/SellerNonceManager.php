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

namespace Sylius\PayPalPlugin\Onboarding\Manager;

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SellerNonceManager implements SellerNonceManagerInterface
{
    private const SESSION_KEY_PREFIX = 'sylius_paypal.onboarding.seller_nonce.';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function generate(bool $sandbox): string
    {
        $nonce = bin2hex(random_bytes(32));

        $this->requestStack->getSession()->set($this->key($sandbox), $nonce);

        return $nonce;
    }

    public function get(bool $sandbox): ?string
    {
        $nonce = $this->requestStack->getSession()->get($this->key($sandbox));

        return null === $nonce ? null : (string) $nonce;
    }

    public function remove(bool $sandbox): void
    {
        $this->requestStack->getSession()->remove($this->key($sandbox));
    }

    private function key(bool $sandbox): string
    {
        return self::SESSION_KEY_PREFIX . ($sandbox ? 'sandbox' : 'production');
    }
}
