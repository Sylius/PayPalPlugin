<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use Doctrine\Common\Collections\Collection;
use PhpSpec\ObjectBehavior;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class AvailableCountriesProviderSpec extends ObjectBehavior
{
    function let(RepositoryInterface $countryRepository, ChannelContextInterface $channelContext): void
    {
        $this->beConstructedWith($countryRepository, $channelContext);
    }

    function it_provides_available_countries_if_channel_does_not_have_any(
        CountryInterface $countryOne,
        CountryInterface $countryTwo,
        CountryInterface $countryThree,
        RepositoryInterface $countryRepository,
        ChannelContextInterface $channelContext,
        ChannelInterface $channel,
        Collection $collection
    ): void {
        $channel->getCountries()->willReturn($collection);

        $collection->toArray()->willReturn([]);
        $channelContext->getChannel()->willReturn($channel);

        $countryOne->getCode()->willReturn('PL');
        $countryTwo->getCode()->willReturn('US');
        $countryThree->getCode()->willReturn('RU');
        $countryRepository->findBy(['enabled' => true])->willReturn([$countryOne, $countryTwo, $countryThree]);

        $this->provide()->shouldReturn(['PL', 'US', 'RU']);
    }

    function it_provides_available_countries_if_channel_contains_countries(
        CountryInterface $countryOne,
        CountryInterface $countryTwo,
        RepositoryInterface $countryRepository,
        ChannelContextInterface $channelContext,
        ChannelInterface $channel,
        Collection $collection
    ): void {
        $channel->getCountries()->willReturn($collection);
        $collection->toArray()->willReturn([$countryOne, $countryTwo]);

        $channelContext->getChannel()->willReturn($channel);

        $countryOne->getCode()->willReturn('DE');
        $countryTwo->getCode()->willReturn('CN');
        $countryRepository->findBy(['enabled' => true])->shouldNotBeCalled();

        $this->provide()->shouldReturn(['DE', 'CN']);
    }
}
