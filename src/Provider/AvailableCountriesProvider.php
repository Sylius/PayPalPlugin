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

use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class AvailableCountriesProvider implements AvailableCountriesProviderInterface, ChannelAvailableCountriesProviderInterface
{
    public function __construct(
        private readonly RepositoryInterface $countryRepository,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    public function provide(): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

        return $this->provideForChannel($channel);
    }

    public function provideForChannel(ChannelInterface $channel): array
    {
        $channelCountries = $channel->getCountries()->toArray();

        if (count($channelCountries)) {
            return $this->convertToStringArray($channelCountries);
        }

        $availableCountries = $this->countryRepository->findBy(['enabled' => true]);

        return $this->convertToStringArray($availableCountries);
    }

    /** @return string[] */
    private function convertToStringArray(array $countries): array
    {
        /** @var string[] $returnCountries */
        $returnCountries = [];

        /** @var CountryInterface $country */
        foreach ($countries as $country) {
            $returnCountries[] = (string) $country->getCode();
        }

        return $returnCountries;
    }
}
