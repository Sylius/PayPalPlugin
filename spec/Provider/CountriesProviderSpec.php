<?php

declare(strict_types=1);

namespace spec\Sylius\PayPalPlugin\Provider;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class CountriesProviderSpec extends ObjectBehavior
{
    function let(RepositoryInterface $countryRepository): void
    {
        $this->beConstructedWith($countryRepository);
    }

    function it_provides_available_countries(
        CountryInterface $countryOne,
        CountryInterface $countryTwo,
        CountryInterface $countryThree,
        RepositoryInterface $countryRepository
    ): void {
        $countryOne->getCode()->willReturn('PL');
        $countryTwo->getCode()->willReturn('US');
        $countryThree->getCode()->willReturn('RU');
        $countryRepository->findBy(['enabled' => 1])->willReturn([$countryOne, $countryTwo, $countryThree]);

        $this->provide()->shouldReturn(['PL', 'US', 'RU']);
    }
}
