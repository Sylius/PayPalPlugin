<?php

declare(strict_types=1);

namespace Sylius\PayPalPlugin\Provider;

use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class CountriesProvider implements CountriesProviderInterface
{
    /** @var RepositoryInterface */
    private $countryRepository;

    public function __construct(RepositoryInterface $countryRepository)
    {
        $this->countryRepository = $countryRepository;
    }

    public function provide(): array
    {
        $availableCountries = $this->countryRepository->findBy(['enabled' => 1]);
        $countries = [];

        /** @var CountryInterface $country */
        foreach ($availableCountries as $country) {
            $countries[] = $country->getCode();
        }

        return $countries;
    }
}
