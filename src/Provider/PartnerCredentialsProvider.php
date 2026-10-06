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

use Sylius\PayPalPlugin\Exception\PayPalPluginException;
use Sylius\PayPalPlugin\Model\PartnerCredentials;

final readonly class PartnerCredentialsProvider implements PartnerCredentialsProviderInterface
{
    public function __construct(
        private string $partnerId,
        private string $partnerClientId,
        private string $partnerLogoUrl = '',
    ) {
    }

    public function provide(): PartnerCredentials
    {
        if ('' === $this->partnerId || '' === $this->partnerClientId) {
            throw new PayPalPluginException('partner_id/partner_client_id is not configured');
        }

        return new PartnerCredentials($this->partnerId, $this->partnerClientId, $this->partnerLogoUrl);
    }
}
