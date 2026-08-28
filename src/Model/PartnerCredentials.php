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

namespace Sylius\PayPalPlugin\Model;

final readonly class PartnerCredentials
{
    public function __construct(
        private string $partnerId,
        private string $partnerClientId,
    ) {
    }

    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    public function getPartnerClientId(): string
    {
        return $this->partnerClientId;
    }
}
