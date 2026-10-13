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
    private const SESSION_KEY = 'sylius_paypal.onboarding.seller_nonce';

    private const SANDBOX_SESSION_KEY = 'sylius_paypal.onboarding.sandbox';

    public function __construct(private RequestStack $requestStack)
    {
    }

    public function generate(bool $sandbox = false): string
    {
        $nonce = bin2hex(random_bytes(32));

        $session = $this->requestStack->getSession();
        $session->set(self::SESSION_KEY, $nonce);
        $session->set(self::SANDBOX_SESSION_KEY, $sandbox);

        return $nonce;
    }

    public function get(): ?string
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(self::SESSION_KEY)) {
            return null;
        }

        return (string) $session->get(self::SESSION_KEY);
    }

    public function isSandbox(): bool
    {
        return true === $this->requestStack->getSession()->get(self::SANDBOX_SESSION_KEY, false);
    }

    public function remove(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
        $session->remove(self::SANDBOX_SESSION_KEY);
    }
}
