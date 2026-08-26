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

use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SellerNonceProvider implements SellerNonceProviderInterface
{
    private const SESSION_KEY = 'sylius_paypal.onboarding.seller_nonce';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function generate(): string
    {
        $nonce = bin2hex(random_bytes(32));

        $this->requestStack->getSession()->set(self::SESSION_KEY, $nonce);

        return $nonce;
    }

    public function consume(): ?string
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(self::SESSION_KEY)) {
            return null;
        }

        $nonce = (string) $session->get(self::SESSION_KEY);
        $session->remove(self::SESSION_KEY);

        return $nonce;
    }
}
