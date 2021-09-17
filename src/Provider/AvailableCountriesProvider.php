<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class AvailableCountriesProvider implements AvailableCountriesProviderInterface
{
    private RepositoryInterface $countryRepository;

    private ChannelContextInterface $channelContext;

    public function __construct(RepositoryInterface $countryRepository, ChannelContextInterface $channelContext)
    {
        $this->countryRepository = $countryRepository;
        $this->channelContext = $channelContext;
    }

    public function provide(): array
    {
        /** @var ChannelInterface $channel */
        $channel = $this->channelContext->getChannel();

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
